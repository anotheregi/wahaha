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

module.exports = function (db, sessionMap, fs, startDEVICE) {
    cron.schedule('* * * * *', function () {
        console.log('cronjob berjalan')
        let sqlde = `SELECT device.*, account.id as id_account, account.username, account.expired,account.status FROM device INNER JOIN account ON device.pemilik = account.id`;
        db.query(sqlde, function (err, results) {
            results.forEach(async de => {
                var sekarang = new Date().getTime();
                const myDate = new Date(de.expired)
                const itstime = myDate.getTime()
                if (de.status != 'expired') {
                    if (de.expired != null) {
                        if (sekarang >= itstime) {
                            db.query("UPDATE `account` SET `status` = 'expired' WHERE `account`.`id` = " + de.id_account, function (err, result) {
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

                    let sql = `SELECT * FROM pesan WHERE status='MENUNGGU JADWAL' OR status='GAGAL' AND sender = ${de.nomor} LIMIT 1`;
                    const velixs = sessionMap.get(parseInt(de.nomor)).chika
                    db.query(sql, async function (err, result) {
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
                                            db.query(`UPDATE pesan SET status = 'TERKIRIM' where id = ${d.id}`)
                                            break
                                        case "Text & Media":
                                            let filename = d.media.split('/')[d.media.split('/').length - 1];
                                            let filetype = filename.split('.')[1]
                                            if (filetype == 'jpg' || filetype == 'png' || filetype == 'jpeg') {
                                                await velixs.sendMessage(number, { image: { url: `${d.media}` }, caption: `${d.pesan}` });
                                                db.query(`UPDATE pesan SET status = 'TERKIRIM' where id = ${d.id}`)
                                            } else if (filetype == 'pdf') {
                                                await velixs.sendMessage(number, { document: { url: `${d.media}` }, mimetype: 'application/pdf', fileName: `${d.pesan}` });
                                                db.query(`UPDATE pesan SET status = 'TERKIRIM' where id = ${d.id}`)
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
                                            db.query(`UPDATE pesan SET status = 'TERKIRIM' where id = ${d.id}`)
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
                                            db.query(`UPDATE pesan SET status = 'TERKIRIM' where id = ${d.id}`)
                                            break
                                    }
                                    tracker.count++;
                                    batchCount++;
                                    // Random delay 60-120s
                                    await delay(60000, 120000);
                                    // Random break after every 10 messages (5-15 min)
                                    if (batchCount % 10 === 0) {
                                        console.log(`Taking a break for device ${de.nomor}`);
                                        await delay(300000, 900000); // 5-15 min
                                    }
                                } catch (err) {
                                    db.query(`UPDATE pesan SET status = 'GAGAL' where id = ${d.id}`)
                                }
                            }
                        }
                    });
                    // Enhanced Anti-Ban Blast Processing
                    let sql2 = `SELECT * FROM blast WHERE sender = ${de.nomor} AND status != 'terkirim' ORDER BY id ASC`;
                    db.query(sql2, async function (err, resultss) {
                        if (resultss && resultss.length > 0) {
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
                                    db.query(`UPDATE blast SET status = 'terkirim' where id = ${msg.id}`);

                                    console.log(`[ANTI-BAN] ✓ Message sent successfully to ${recipient} (Device: ${de.nomor})`);
                                    return { success: true };

                                } catch (error) {
                                    const isBan = antiban.detectBan(error);
                                    console.log(`[ANTI-BAN] ✗ Failed to send to ${recipient} (Device: ${de.nomor}): ${error.message}${isBan ? ' [BAN DETECTED]' : ''}`);

                                    const msg = messages.find(m => m.recipient === recipient);
                                    db.query(`UPDATE blast SET status = 'gagal' where id = ${msg.id}`);

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
        let sqlde = `SELECT *  FROM device`;
        db.query(sqlde, function (err, results) {
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

    // Anti-ban session health monitoring
    cron.schedule('*/5 * * * *', function () { // Every 5 minutes
        console.log('[ANTI-BAN] Running session health checks...');
        let sqlde = `SELECT * FROM device`;
        db.query(sqlde, function (err, results) {
            results.forEach(async de => {
                const deviceId = de.nomor;
                const sessionPath = `./app_node/session/device-${deviceId}.json`;

                const healthCheck = antiban.checkSessionHealth(deviceId, sessionPath);
                const deviceHealth = antiban.getDeviceHealth(deviceId);

                if (!healthCheck.healthy) {
                    console.log(`[ANTI-BAN] Device ${deviceId} health issue: ${healthCheck.reason}`);

                    // Force session restart for unhealthy devices
                    if (sessionMap.has(parseInt(deviceId))) {
                        console.log(`[ANTI-BAN] Restarting unhealthy session for device ${deviceId}`);
                        const chi = sessionMap.get(parseInt(deviceId));
                        chi.chika.logout();
                        sessionMap.delete(parseInt(deviceId));
                    }

                    // Wait a bit before restarting
                    setTimeout(() => {
                        if (fs.existsSync(sessionPath)) {
                            startDEVICE(parseInt(deviceId));
                            console.log(`[ANTI-BAN] Restarted session for device ${deviceId}`);
                        }
                    }, 10000); // 10 second delay
                }

                // Log device health stats
                if (deviceHealth.totalSent + deviceHealth.totalFailed > 0) {
                    const successRate = ((deviceHealth.totalSent / (deviceHealth.totalSent + deviceHealth.totalFailed)) * 100).toFixed(1);
                    console.log(`[ANTI-BAN] Device ${deviceId} stats: ${deviceHealth.totalSent} sent, ${deviceHealth.totalFailed} failed (${successRate}% success)`);
                }
            });
        });
    });
};