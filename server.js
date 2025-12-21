const { default: makeWASocket, useMultiFileAuthState, DisconnectReason, fetchLatestBaileysVersion, generateForwardMessageContent, prepareWAMessageMedia, generateWAMessageFromContent, generateMessageID, downloadContentFromMessage, jidDecode, proto } = require("@whiskeysockets/baileys")
const pino = require('pino')
const { Boom } = require('@hapi/boom')
const fs = require('fs')
const chalk = require('chalk')
require('dotenv').config()
const express = require('express')
const socket = require("socket.io");
const { toDataURL } = require('qrcode')
const mysql = require('mysql');
const request = require('request');
const { smsg } = require('./app_node/lib/myf')

const app = express()
const host = process.env.HOST
const port = parseInt(process.env.PORT)
app.use(express.urlencoded({ extended: true }))
app.use(express.json())
const ser = app.listen(port, host, () => {
    console.log(`Server is listening on http://${host}:${port}`)
})
const io = socket(ser);

const db = mysql.createConnection({
    host: process.env.DB_HOSTNAME,
    user: process.env.DB_USERNAME,
    password: process.env.DB_PASSWORD,
    database: process.env.DB_DATABASE
});

db.connect((err) => {
    if (err) throw err;
    console.log('Mysql Connected...');
});

const sessionMap = new Map()

// Device fingerprint randomization for anti-detection
function generateDeviceFingerprint(deviceId) {
    const browsers = [
        ['Chrome', 'Chrome', '120.0.0.0'],
        ['Firefox', 'Firefox', '121.0'],
        ['Safari', 'Safari', '17.0'],
        ['Edge', 'Edge', '120.0.0.0'],
        ['Opera', 'Opera', '106.0.0.0']
    ];

    const platforms = [
        'Windows NT 10.0; Win64; x64',
        'Macintosh; Intel Mac OS X 10_15_7',
        'X11; Linux x86_64',
        'Windows NT 11.0; Win64; x64',
        'Macintosh; Intel Mac OS X 10_14_6'
    ];

    const mobilePlatforms = [
        'iPhone; CPU iPhone OS 17_0 like Mac OS X',
        'Android 13; SM-G998B',
        'Android 12; Pixel 6',
        'iPhone; CPU iPhone OS 16_6 like Mac OS X'
    ];

    // Use device ID as seed for consistent but varied fingerprints
    const seed = parseInt(deviceId) % 100;
    const isMobile = seed % 4 === 0; // 25% chance of mobile device

    const browserIndex = seed % browsers.length;
    const platformIndex = isMobile ?
        (seed % mobilePlatforms.length) :
        (seed % platforms.length);

    const [browserName, browserType, version] = browsers[browserIndex];
    const platform = isMobile ? mobilePlatforms[platformIndex] : platforms[platformIndex];

    // Generate unique user agent
    const userAgent = isMobile ?
        `Mozilla/5.0 (${platform}) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.0 Mobile/15E148 Safari/604.1` :
        `Mozilla/5.0 (${platform}) AppleWebKit/537.36 (KHTML, like Gecko) ${browserName}/${version} Safari/537.36`;

    return {
        browser: [browserName, browserType, version],
        userAgent: userAgent,
        platform: platform,
        isMobile: isMobile
    };
}

async function startDEVICE(idevice) {
    const { state, saveCreds } = await useMultiFileAuthState(`./app_node/session/device-${idevice}`)

    // Generate unique fingerprint for this device
    const fingerprint = generateDeviceFingerprint(idevice);

    const chika = makeWASocket({
        logger: pino({ level: 'silent' }),
        browser: fingerprint.browser,
        auth: state,
        printQRInTerminal: false,
        qrTimeout: 60000,
        connectTimeoutMs: 60000,
        keepAliveIntervalMs: 30000,
        emitOwnEvents: false,
        fireInitQueries: true,
        generateHighQualityLinkPreview: true,
    syncFullHistory: false,
        markOnlineOnConnect: true,
        // Additional fingerprint randomization
        userAgent: fingerprint.userAgent,
        platform: fingerprint.platform
    })
    chika.decodeJid = (jid) => {
        if (!jid) return jid
        if (/:\d+@/gi.test(jid)) {
            let decode = jidDecode(jid) || {}
            return decode.user && decode.server && decode.user + '@' + decode.server || jid
        } else return jid
    }
    chika.ev.on('messages.upsert', async chatUpdate => {
        try {
            mek = chatUpdate.messages[0]
            if (!mek.message) return
            // mek.message = (Object.keys(mek.message)[0] === 'ephemeralMessage') ? mek.message.ephemeralMessage.message : mek.message
            if (mek.key && mek.key.remoteJid === 'status@broadcast') return
            if (mek.key.id.startsWith('BAE5') && mek.key.id.length === 16) return
            m = smsg(chika, mek, null)
            require("./app_node/lib/handler")(chika, chatUpdate, db, m)
        } catch (err) {
            console.log(err)
        }
    })
    chika.ev.on('connection.update', async (update) => {
        const { connection, lastDisconnect, qr } = update
        if (connection === 'open') {
            sessionMap.set(idevice, { chika })
            io.emit('message', {
                id: idevice,
                text: 'Whatsapp is ready!'
            });
            io.emit('authenticated', {
                id: idevice,
                data: chika.user
            })
        }
        if (connection === 'close') {
            sessionMap.delete(parseInt(idevice))
            const logoutsessi = (reason) => {
                if (reason !== DisconnectReason.loggedOut) {
                    chika.logout();
                }
                const sessionDir = `./app_node/session/device-${idevice}`;
                if (fs.existsSync(sessionDir)) {
                    fs.rmSync(sessionDir, { recursive: true, force: true });
                }
            }
            let reason = new Boom(lastDisconnect?.error)?.output.statusCode
            if (reason === DisconnectReason.badSession) { console.log(`Bad Session File, Please Delete Session and Scan Again`); logoutsessi(reason); }
            else if (reason === DisconnectReason.connectionClosed) { console.log("Connection closed, reconnecting...."); startDEVICE(idevice); }
            else if (reason === DisconnectReason.connectionLost) { console.log("Connection Lost from Server, reconnecting..."); startDEVICE(idevice); }
            else if (reason === DisconnectReason.connectionReplaced) { console.log("Connection Replaced, Another New Session Opened, Please Close Current Session First"); logoutsessi(reason); }
            else if (reason === DisconnectReason.loggedOut) { console.log(`Device Logged Out, Please Scan Again And Run.`); logoutsessi(reason); }
            else if (reason === DisconnectReason.restartRequired) { console.log("Restart Required, Restarting..."); startDEVICE(idevice) }
            else if (reason === DisconnectReason.timedOut) { console.log("Connection TimedOut, Reconnecting..."); startDEVICE(idevice); }
            else chika.end(`Unknown DisconnectReason: ${reason}|${connection}`)
        }
        if (update.qr) {
            const url = await toDataURL(qr)
            try {
                io.emit('qr', {
                    id: idevice,
                    src: url
                });
                io.emit('message', {
                    id: idevice,
                    text: 'QR Code received, scan please!'
                });
            } catch {
                io.emit('message', {
                    id: idevice,
                    text: 'QR Error, please refresh page!'
                });
                logoutDEVICE(parseInt(idevice))
            }
        }
        console.log('Connected...', update)
    })
    chika.ev.on('creds.update', saveCreds)
    chika.ev.on('contacts.upsert', async (m) => {
        console.log(m)
        request({
            url: process.env.BASE_WEB + '/app/api/callback',
            method: "POST",
            json: {
                "id": idevice,
                "data": m
            }
        })
    })

    return chika
}

const logoutDEVICE = (idevice) => {
    const chi = sessionMap.get(parseInt(idevice))
    if (chi && chi.chika) {
        chi.chika.logout();
    }
    const sessionDir = `./app_node/session/device-${idevice}`;
    if (fs.existsSync(sessionDir)) {
        fs.rmSync(sessionDir, { recursive: true, force: true });
    }
    sessionMap.delete(parseInt(idevice))
}

io.on('connection', function (socket) {
    socket.on('create-session', function (data) {
        if (sessionMap.has(parseInt(data.id))) {
            console.log('get session: ' + data.id);
            const conn = sessionMap.get(parseInt(data.id)).chika
            io.emit('message', {
                id: data.id,
                text: 'Whatsapp is ready!'
            });
            io.emit('authenticated', {
                id: data.id,
                data: conn.user
            })
        } else {
            console.log('Create session: ' + data.id);
            startDEVICE(parseInt(data.id));
        }
    });
    socket.on('logout', async function (data) {
        const sessionDir = `./app_node/session/device-${data.id}`;
        if (fs.existsSync(sessionDir)) {
            socket.emit('isdelete', {
                id: data.id,
                text: '<h2 class="text-center text-info mt-4">Logout Success, Lets Scan Again<h2>'
            })
            logoutDEVICE(parseInt(data.id))
        } else {
            socket.emit('isdelete', {
                id: data.id,
                text: '<h2 class="text-center text-danger mt-4">You are have not Login yet!<h2>'
            })
        }
    })
});

require('./app_node/routes/web')(app, sessionMap, startDEVICE)
require('./app_node/lib/cron')(db, sessionMap, fs, startDEVICE)

let file = require.resolve(__filename)
fs.watchFile(file, () => {
    fs.unwatchFile(file)
    console.log(chalk.redBright(`Update ${__filename}`))
    delete require.cache[file]
    require(file)
})
