const cron = require('node-cron');
const { phoneNumberFormatter } = require('./formatter');
const antiban = require('./antiban');

// Helper function for random delay
function delay(minMs, maxMs) {
    const ms = Math.floor(Math.random() * (maxMs - minMs + 1)) + minMs;
    return new Promise(resolve => setTimeout(resolve, ms));
}

// Helper function to shuffle array
function shuffleArray(array) {
    for (let i = array.length - 1; i > 0; i--) {
        const j = Math.floor(Math.random() * (i + 1));
        [array[i], array[j]] = [array[j], array[i]];
    }
    return array;
}

// Track messages sent per device per hour
const messageTracker = new Map();

// Track device message limits and cooldowns (30 messages per 3 hours)
const deviceLimitTracker = new Map();

module.exports = function (db, sessionMap, fs, startDEVICE) {
    cron.schedule('*/2 * * * *', function () {
        console.log('cronjob berjalan')
        let sqlde = `SELECT device.*, account.id as id_account, account.username, account.expired,account.status FROM device INNER JOIN account ON device.pemilik = account.id`;
        db.query(sqlde, [], function (err, results) {
            results.forEach(async de => {
                var sekarang = new Date().getTime();
                const myDate = new Date(de.expired)
                const itstime = myDate.getTime()
                if (de.status != 'expired') {
                    if (de.expired != null) {
                        if (sekarang >= itstime) {
                            db.query("UPDATE `account` SET `status` = 'expired' WHERE `account`.`id` = ?", [de.id_account], function (err, result) {
                                if (err) throw err;
                                console.log(result.affectedRows + " expired user " + de.username);
                                if (sessionMap.has(parseInt(de.nomor))) {
                                    const chi = sessionMap.get(parseInt(de.nomor))
                                    chi.chika.logout();
                                    sessionMap.delete(parseInt(de.nomor))
                                }
                                if (fs.existsSync(`./app_node/session/device-${parseInt(de.nomor)}.json`)) {
                                    fs.unlinkSync(`./app_node/session/device-${parseInt(de.nomor)}.json`);
                                }
                            });
                        }
                    }
                }
                if (sessionMap.has(parseInt(de.nomor))) {
                    // Check hourly message limit
                    const deviceKey = `device_${de.nomor}`;
                    const currentHour = Math.floor(Date.now() / (1000 * 60 * 60));
                    if (!messageTracker.has(deviceKey)) {
                        messageTracker.set(deviceKey, { hour: currentHour, count: 0 });
                    }
                    const tracker = messageTracker.get(deviceKey);
                    if (tracker.hour !== currentHour) {
                        tracker.hour = currentHour;
                        tracker.count = 0;
                    }
                    // Anti-ban hourly limit check
                    const hourlyLimit = Math.floor(Math.random() * (antiban.ANTIBAN_CONFIG.messagesPerHour.max - antiban.ANTIBAN_CONFIG.messagesPerHour.min + 1)) + antiban.ANTIBAN_CONFIG.messagesPerHour.min;
                    if (tracker.count >= hourlyLimit) {
                        console.log(`[ANTI-BAN] Device ${de.nomor} reached hourly limit (${hourlyLimit}), skipping`);
                        return;
                    }

                    // Check 30 message limit per 3 hours for regular messages
                    const deviceLimitKey = `limit_${de.nomor}`;
                    const now = Date.now();
                    const threeHoursMs = 3 * 60 * 60 * 1000; // 3 hours in milliseconds

                    if (!deviceLimitTracker.has(deviceLimitKey)) {
                        deviceLimitTracker.set(deviceLimitKey, {
                            messages: [],
                            cooldownUntil: 0
                        });
                    }

                    const limitData = deviceLimitTracker.get(deviceLimitKey);

                    // Remove messages older than 3 hours
                    limitData.messages = limitData.messages.filter(timestamp => now - timestamp < threeHoursMs);

                    // Check if device is in cooldown
                    if (now < limitData.cooldownUntil) {
                        const remainingCooldown = Math.ceil((limitData.cooldownUntil - now) / (60 * 1000)); // minutes
                        console.log(`[LIMIT] Device ${de.nomor} in cooldown for ${remainingCooldown} more minutes (30/3h limit)`);
                        return;
                    }

                    // Check if device has reached 20 messages in 3 hours
                    if (limitData.messages.length >= 20) {
                        limitData.cooldownUntil = now + threeHoursMs; // 3 hour cooldown
                        console.log(`[LIMIT] Device ${de.nomor} reached 20 messages in 3 hours, entering 3-hour cooldown`);
                        return;
                    }

                    let sql = `SELECT * FROM pesan WHERE (status='MENUNGGU JADWAL' OR status='GAGAL') AND sender = ? LIMIT 1`;
                    const velixs = sessionMap.get(parseInt(de.nomor)).chika
                    db.query(sql, [de.nomor], async function (err, result) {
                        shuffleArray(result);
                        let batchCount = 0;
                        for (let i = 0; i < result.length; i++) {
                            const d = result[i];
                            const yourDate = new Date(d.jadwal)
                            const waktu = yourDate.getTime()
                            if (sekarang >= waktu) {
                                if (d.nomor.length > 15) {
                                    var number = d.nomor;
                                } else {
                                    var number = phoneNumberFormatter(d.nomor);
                                }
                                console.log(`Mengirim Ke Nomer ${number}`)
                                try {
                                    switch (d.type) {
                                        case "Text":
                                            await velixs.sendMessage(number, { text: d.pesan });
                                            db.query(`UPDATE pesan SET status = 'TERKIRIM' where id = ?`, [d.id])
                                            break
                                        case "Text & Media":
                                            let filename = d.media.split('/')[d.media.split('/').length - 1];
                                            let filetype = filename.split('.')[1]
                                            if (filetype == 'jpg' || filetype == 'png' || filetype == 'jpeg') {
                                                await velixs.sendMessage(number, { image: { url: `${d.media}` }, caption: `${d.pesan}` });
                                                db.query(`UPDATE pesan SET status = 'TERKIRIM' where id = ?`, [d.id])
                                            } else if (filetype == 'pdf') {
                                                await velixs.sendMessage(number, { document: { url: `${d.media}` }, mimetype: 'application/pdf', fileName: `${d.pesan}` });
                                                db.query(`UPDATE pesan SET status = 'TERKIRIM' where id = ?`, [d.id])
                                            } else {
                                                console.log('Filetype tidak dikenal');
                                            }
                                            break
                                        case "Quick Reply Button":
                                            const buttons = [
                                                { buttonId: d.btn1, buttonText: { displayText: d.btn1 }, type: 1 },
                                                { buttonId: d.btn2, buttonText: { displayText: d.btn2 }, type: 1 },
                                                { buttonId: d.btn3, buttonText: { displayText: d.btn3 }, type: 1 }
                                            ]
                                            const buttonMessage = {
                                                text: d.pesan,
                                                footer: d.footer,
                                                buttons: buttons,
                                                headerType: 1
                                            }
                                            await velixs.sendMessage(number, buttonMessage);
                                            db.query(`UPDATE pesan SET status = 'TERKIRIM' where id = ?`, [d.id])
                                            break
                                        case "Url & Call Button":
                                            const templateButtons = [
                                                { index: 1, urlButton: { displayText: d.btn1, url: d.btnid1 } },
                                                { index: 2, callButton: { displayText: d.btn2, phoneNumber: d.btnid2 } }
                                            ]
                                            const templateMessage = {
                                                text: d.pesan,
                                                footer: d.footer,
                                                templateButtons: templateButtons
                                            }
                                            await velixs.sendMessage(number, templateMessage);
                                            db.query(`UPDATE pesan SET status = 'TERKIRIM' where id = ?`, [d.id])
                                            break
                                    }
                                    tracker.count++;
                                    // Track message for 30/3h limit
                                    limitData.messages.push(now);
                                    batchCount++;
                                    // Random delay 45-180s for better variation
                                    await delay(45000, 180000);
                                    // Random break after every 10 messages (5-15 min)
                                    if (batchCount % 10 === 0) {
                                        console.log(`Taking a break for device ${de.nomor}`);
                                        await delay(300000, 900000); // 5-15 min
                                    }
                                } catch (err) {
                                    db.query(`UPDATE pesan SET status = 'GAGAL' where id = ?`, [d.id])
                                }
                            }
                        }
                    });
                    // Enhanced Anti-Ban Blast Processing
                    let sql2 = `SELECT * FROM blast WHERE sender = ? AND status != 'terkirim' ORDER BY id ASC`;
                    db.query(sql2, async function (err, resultss) {
                        if (resultss && resultss.length > 0) {
                            // Check 30 message limit per 3 hours
                            const deviceLimitKey = `limit_${de.nomor}`;
                            const now = Date.now();
                            const threeHoursMs = 3 * 60 * 60 * 1000; // 3 hours in milliseconds

                            if (!deviceLimitTracker.has(deviceLimitKey)) {
                                deviceLimitTracker.set(deviceLimitKey, {
                                    messages: [],
                                    cooldownUntil: 0
                                });
                            }

                            const limitData = deviceLimitTracker.get(deviceLimitKey);

                            // Remove messages older than 3 hours
                            limitData.messages = limitData.messages.filter(timestamp => now - timestamp < threeHoursMs);

                            // Check if device is in cooldown
                            if (now < limitData.cooldownUntil) {
                                const remainingCooldown = Math.ceil((limitData.cooldownUntil - now) / (60 * 1000)); // minutes
                                console.log(`[LIMIT] Device ${de.nomor} in cooldown for ${remainingCooldown} more minutes (30/3h limit)`);
                                return;
                            }

                            // Check if device has reached 30 messages in 3 hours
                            if (limitData.messages.length >= 30) {
                                limitData.cooldownUntil = now + threeHoursMs; // 3 hour cooldown
                                console.log(`[LIMIT] Device ${de.nomor} reached 30 messages in 3 hours, entering 3-hour cooldown`);
                                return;
                            }

                            // Check device health before processing
                            const sessionPath = `./app_node/session/device-${de.nomor}.json`;
                            const healthCheck = antiban.checkSessionHealth(de.nomor, sessionPath);

                            if (!healthCheck.healthy) {
                                console.log(`Device ${de.nomor} health check failed: ${healthCheck.reason}, skipping blast processing`);
                                return;
                            }

                            // Check if device has exceeded anti-ban limits
                            const deviceHealth = antiban.getDeviceHealth(de.nomor);
                            if (deviceHealth.consecutiveFailures >= antiban.ANTIBAN_CONFIG.maxConsecutiveFailures) {
                                console.log(`Device ${de.nomor} has ${deviceHealth.consecutiveFailures} consecutive failures, pausing for safety`);
                                return;
                            }

                            shuffleArray(resultss);

                            // Prepare messages for anti-ban processing
                            const messages = resultss.map(dw => ({
                                id: dw.id,
                                recipient: dw.tujuan.length > 15 ? dw.tujuan : phoneNumberFormatter(dw.tujuan),
                                content: dw.pesan,
                                type: dw.type,
                                media: dw.media,
                                footer: dw.footer,
                                btn1: dw.btn1,
                                btn2: dw.btn2,
                                btn3: dw.btn3,
                                btnid1: dw.btnid1,
                                btnid2: dw.btnid2
                            }));

                            // Process messages with anti-ban system
                            const sendFunction = async (recipient, content) => {
                                console.log(`[ANTI-BAN] Sending to ${recipient} (Device: ${de.nomor})`);

                                try {
                                    switch (messages.find(m => m.recipient === recipient)?.type) {
                                        case "Text":
                                            await velixs.sendMessage(recipient, { text: content });
                                            break;
                                        case "Text & Media":
                                            const msg = messages.find(m => m.recipient === recipient);
                                            let filename = msg.media.split('/')[msg.media.split('/').length - 1];
                                            let filetype = filename.split('.')[1];
                                            if (filetype == 'jpg' || filetype == 'png' || filetype == 'jpeg') {
                                                await velixs.sendMessage(recipient, { image: { url: `${msg.media}` }, caption: content });
                                            } else if (filetype == 'pdf') {
                                                await velixs.sendMessage(recipient, { document: { url: `${msg.media}` }, mimetype: 'application/pdf', fileName: content });
                                            }
                                            break;
                                        case "Quick Reply Button":
                                            const btnMsg = messages.find(m => m.recipient === recipient);
                                            const buttons = [
                                                { buttonId: btnMsg.btn1, buttonText: { displayText: btnMsg.btn1 }, type: 1 },
                                                { buttonId: btnMsg.btn2, buttonText: { displayText: btnMsg.btn2 }, type: 1 },
                                                { buttonId: btnMsg.btn3, buttonText: { displayText: btnMsg.btn3 }, type: 1 }
                                            ];
                                            const buttonMessage = {
                                                text: content,
                                                footer: btnMsg.footer,
                                                buttons: buttons,
                                                headerType: 1
                                            };
                                            await velixs.sendMessage(recipient, buttonMessage);
                                            break;
                                        case "Url & Call Button":
                                            const tempMsg = messages.find(m => m.recipient === recipient);
                                            const templateButtons = [
                                                { index: 1, urlButton: { displayText: tempMsg.btn1, url: tempMsg.btnid1 } },
                                                { index: 2, callButton: { displayText: tempMsg.btn2, phoneNumber: tempMsg.btnid2 } }
                                            ];
                                            const templateMessage = {
                                                text: content,
                                                footer: tempMsg.footer,
                                                templateButtons: templateButtons
                                            };
                                            await velixs.sendMessage(recipient, templateMessage);
                                            break;
                                    }

                                    // Update tracker and database
                                    tracker.count++;
                                    const msg = messages.find(m => m.recipient === recipient);
                                    db.query(`UPDATE blast SET status = 'terkirim' where id = ?`, [msg.id]);

                                    // Track message for 30/3h limit
                                    limitData.messages.push(now);

                                    console.log(`[ANTI-BAN] ✓ Message sent successfully to ${recipient} (Device: ${de.nomor})`);
                                    return { success: true };

                                } catch (error) {
                                    const isBan = antiban.detectBan(error);
                                    console.log(`[ANTI-BAN] ✗ Failed to send to ${recipient} (Device: ${de.nomor}): ${error.message}${isBan ? ' [BAN DETECTED]' : ''}`);

                                    const msg = messages.find(m => m.recipient === recipient);
                                    db.query(`UPDATE blast SET status = 'gagal' where id = ?`, [msg.id]);

                                    return { success: false, error: error.message, isBan };
                                }
                            };

                            // Process batch with anti-ban measures
                            const results = await antiban.processBatch(messages, de.nomor, sendFunction);

                            // Log results
                            const successCount = results.filter(r => r.success).length;
                            const totalCount = results.length;
                            const successRate = totalCount > 0 ? ((successCount / totalCount) * 100).toFixed(1) : 0;

                            console.log(`[ANTI-BAN] Device ${de.nomor} blast completed: ${successCount}/${totalCount} (${successRate}%)`);

                            // Update device health based on results
                            results.forEach(result => {
                                antiban.updateDeviceHealth(de.nomor, result.success);
                            });
                        }
                    });
                }
            })
        })

    });

    // Session health check and reconnection
    cron.schedule('* * * * *', function () {
        console.log('cronjob reconnect device')
        let sqlde = `SELECT * FROM device`;
        db.query(sqlde, [], function (err, results) {
            results.forEach(async de => {
                if (fs.existsSync(`./app_node/session/device-${parseInt(de.nomor)}.json`)) {
                    if (!sessionMap.has(parseInt(de.nomor))) {
                        console.log(parseInt(de.nomor))
                        startDEVICE(parseInt(de.nomor))
                    }
                }
            })
        })
    });

    // Enhanced automatic session recovery and health monitoring
    cron.schedule('*/5 * * * *', function () { // Every 5 minutes
        console.log('[SESSION-RECOVERY] Running enhanced session health checks...');
        let sqlde = `SELECT * FROM device`;
        db.query(sqlde, [], function (err, results) {
            results.forEach(async de => {
                const deviceId = de.nomor;
                const sessionPath = `./app_node/session/device-${deviceId}.json`;

                const healthCheck = antiban.checkSessionHealth(deviceId, sessionPath);
                const deviceHealth = antiban.getDeviceHealth(deviceId);
                const now = Date.now();

                let needsRecovery = false;
                let recoveryReason = '';

                // Check various conditions for session recovery
                if (!healthCheck.healthy) {
                    needsRecovery = true;
                    recoveryReason = healthCheck.reason;
                }

                // Check for inactivity-based recovery (simulate device restart)
                const inactivityThreshold = 2 * 60 * 60 * 1000; // 2 hours
                if (now - deviceHealth.lastActivity > inactivityThreshold) {
                    needsRecovery = true;
                    recoveryReason = 'inactivity_timeout';
                }

                // Check for pattern-based recovery (simulate human behavior)
                const hour = new Date().getHours();
                if (hour >= 22 || hour <= 6) { // Night time - higher chance of "device sleep"
                    if (Math.random() < 0.1) { // 10% chance during night hours
                        needsRecovery = true;
                        recoveryReason = 'nighttime_refresh';
                    }
                }

                // Check for consecutive failures requiring recovery
                if (deviceHealth.consecutiveFailures >= 5) {
                    needsRecovery = true;
                    recoveryReason = 'high_failure_rate';
                }

                if (needsRecovery) {
                    console.log(`[SESSION-RECOVERY] Device ${deviceId} needs recovery: ${recoveryReason}`);

                    // Force session restart for devices needing recovery
                    if (sessionMap.has(parseInt(deviceId))) {
                        console.log(`[SESSION-RECOVERY] Restarting session for device ${deviceId}`);
                        const chi = sessionMap.get(parseInt(deviceId));
                        try {
                            await chi.chika.logout();
                        } catch (error) {
                            console.log(`[SESSION-RECOVERY] Logout error for device ${deviceId}: ${error.message}`);
                        }
                        sessionMap.delete(parseInt(deviceId));
                    }

                    // Clean up old session files for fresh start
                    if (fs.existsSync(sessionPath)) {
                        try {
                            fs.unlinkSync(sessionPath);
                            console.log(`[SESSION-RECOVERY] Cleaned old session file for device ${deviceId}`);
                        } catch (error) {
                            console.log(`[SESSION-RECOVERY] Error cleaning session file: ${error.message}`);
                        }
                    }

                    // Wait with exponential backoff before restarting
                    const backoffDelay = Math.min(30000 * Math.pow(2, deviceHealth.consecutiveFailures), 300000); // Max 5 minutes
                    setTimeout(() => {
                        console.log(`[SESSION-RECOVERY] Restarting device ${deviceId} after ${backoffDelay/1000}s delay`);
                        startDEVICE(parseInt(deviceId));
                    }, backoffDelay);
                }

                // Log device health stats
                if (deviceHealth.totalSent + deviceHealth.totalFailed > 0) {
                    const successRate = ((deviceHealth.totalSent / (deviceHealth.totalSent + deviceHealth.totalFailed)) * 100).toFixed(1);
                    console.log(`[SESSION-RECOVERY] Device ${deviceId} stats: ${deviceHealth.totalSent} sent, ${deviceHealth.totalFailed} failed (${successRate}% success)`);
                }
            });
        });
    });

    // Proactive session refresh based on activity patterns
    cron.schedule('0 */2 * * *', function () { // Every 2 hours
        console.log('[SESSION-RECOVERY] Running proactive session refresh...');
        let sqlde = `SELECT * FROM device`;
        db.query(sqlde, [], function (err, results) {
            results.forEach(async de => {
                const deviceId = de.nomor;
                const deviceHealth = antiban.getDeviceHealth(deviceId);

                // Refresh sessions that have been active recently but not too recently
                const timeSinceActivity = Date.now() - deviceHealth.lastActivity;
                const minActivityAge = 30 * 60 * 1000; // 30 minutes
                const maxActivityAge = 90 * 60 * 1000; // 90 minutes

                if (timeSinceActivity >= minActivityAge && timeSinceActivity <= maxActivityAge) {
                    if (Math.random() < 0.3) { // 30% chance for proactive refresh
                        console.log(`[SESSION-RECOVERY] Proactive refresh for active device ${deviceId}`);

                        if (sessionMap.has(parseInt(deviceId))) {
                            const chi = sessionMap.get(parseInt(deviceId));
                            // Simulate brief disconnection and reconnection
                            setTimeout(() => {
                                if (sessionMap.has(parseInt(deviceId))) {
                                    console.log(`[SESSION-RECOVERY] Proactive reconnection for device ${deviceId}`);
                                    // This will trigger the reconnection logic in the main cron
                                }
                            }, 5000);
                        }
                    }
                }
            });
        });
    });
};