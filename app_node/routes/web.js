const { phoneNumberFormatter } = require('../lib/formatter');
const fs = require('fs')
const NodeCache = require('node-cache');
const winston = require('winston');
const mysql = require('mysql');

// Initialize cache (assuming it's passed or global)
const cache = new NodeCache({ stdTTL: 600, checkperiod: 120 });

// Database connection for authentication
const db = mysql.createConnection({
    host: process.env.DB_HOSTNAME,
    user: process.env.DB_USERNAME,
    password: process.env.DB_PASSWORD,
    database: process.env.DB_DATABASE,
    connectionLimit: 10,
    acquireTimeout: 60000,
    timeout: 60000
});

db.connect((err) => {
    if (err) {
        winston.error('Database connection failed: ' + err);
        throw err;
    }
    winston.info('Database connected for authentication');
});

// Authentication middleware
const authenticate = (req, res, next) => {
    const apiKey = req.body.api_key || req.query.api_key;
    const sender = req.body.sender || req.query.sender;

    if (!apiKey || !sender) {
        winston.warn(`Authentication failed: Missing API key or sender from ${req.ip}`);
        return res.status(401).json({
            status: false,
            message: 'API key and sender required'
        });
    }

    // Check API key and sender association
    const query = 'SELECT id FROM account WHERE api_key = ?';
    db.query(query, [apiKey], (err, results) => {
        if (err) {
            winston.error('Database query error: ' + err);
            return res.status(500).json({
                status: false,
                message: 'Internal server error'
            });
        }

        if (results.length === 0) {
            winston.warn(`Authentication failed: Invalid API key from ${req.ip}`);
            return res.status(401).json({
                status: false,
                message: 'Invalid API key'
            });
        }

        const userId = results[0].id;

        // Check if sender belongs to user
        const deviceQuery = 'SELECT id FROM device WHERE nomor = ? AND pemilik = ?';
        db.query(deviceQuery, [sender, userId], (err, deviceResults) => {
            if (err) {
                winston.error('Device query error: ' + err);
                return res.status(500).json({
                    status: false,
                    message: 'Internal server error'
                });
            }

            if (deviceResults.length === 0) {
                winston.warn(`Authentication failed: Device not found for user ${userId} from ${req.ip}`);
                return res.status(401).json({
                    status: false,
                    message: 'Device not authorized for this API key'
                });
            }

            // Authentication successful
            req.userId = userId;
            req.sender = sender;
            next();
        });
    });
};

module.exports = function (app, sessionMap, startDEVICE) {

    const device = (sender) => {
        const session = sessionMap.get(parseInt(sender));
        return session ? session.chika : null;
    }

    app.get('/', function (req, res) {
        res.json({
            status: 'WhatsApp Bot Server is running',
            message: 'This is the backend server for WhatsApp automation. Access the web interface at http://localhost/wblast to manage devices.'
        });
    });

    app.post('/send-message', authenticate, async (req, res) => {
        const sender = req.body.sender;
        winston.info(`Send message request from ${req.ip} for sender ${sender}`);
        if (device(sender)) {
            const conn = device(sender)
            if (req.body.number.length > 18) {
                var number = req.body.number;
            } else {
                var number = phoneNumberFormatter(req.body.number);
            }
            const message = req.body.message;
            conn.sendMessage(number, { text: `${message}` }).then(response => {
                winston.info(`Message sent successfully to ${number}`);
                res.status(200).json({
                    status: true,
                    response: response
                });
            }).catch(err => {
                winston.error(`Failed to send message to ${number}: ${err}`);
                res.status(500).json({
                    status: false,
                    response: err
                });
            });
        } else {
            winston.warn(`Unauthorized send message attempt from ${req.ip} for sender ${sender}`);
            res.status(500).json({
                status: false,
                response: 'Please scan the QR before use this API'
            });
        }

    });

    app.post('/send-media', authenticate, async (req, res) => {
        const sender = req.body.sender;
        if (device(sender)) {
            const conn = device(sender)
            const url = req.body.url;
            const filetype = req.body.filetype;
            const filename = req.body.filename;
            const caption = req.body.caption;
            if (req.body.number.length > 18) {
                var number = req.body.number;
            } else {
                var number = phoneNumberFormatter(req.body.number);
            }

            if (filetype == 'jpg' || filetype == 'png' || filetype == 'jpeg') {
                conn.sendMessage(number, { image: { url: `${url}` }, caption: `${caption}` }).then(response => {
                    res.status(200).json({
                        status: true,
                        response: response
                    });
                }).catch(err => {
                    res.status(500).json({
                        status: false,
                        response: err
                    });
                });
            } else if (filetype == 'pdf') {
                conn.sendMessage(number, { document: { url: `${url}` }, mimetype: 'application/pdf', fileName: `${filename}` }).then(response => {
                    return res.status(200).json({
                        status: true,
                        response: response
                    });
                }).catch(err => {
                    return res.status(500).json({
                        status: false,
                        response: err
                    });
                });
            } else {
                res.status(500).json({
                    status: false,
                    response: 'Filetype tidak dikenal'
                });
            }
        } else {
            res.writeHead(401, {
                'Content-Type': 'application/json'
            });
            res.end(JSON.stringify({
                status: false,
                message: 'Please scan the QR before use the API 2'
            }));
        }
    });

    app.post('/send-button', authenticate, async (req, res) => {
        const sender = req.body.sender;
        if (device(sender)) {
            const conn = device(sender);
            const message = req.body.message;
            const footer = req.body.footer;
            const btn1 = req.body.btn1;
            const btn2 = req.body.btn2;
            if (req.body.number.length > 15) {
                var number = req.body.number;
            } else {
                var number = phoneNumberFormatter(req.body.number);
            }

            const buttons = [
                { buttonId: `${btn1}`, buttonText: { displayText: `${btn1}` }, type: 1 },
                { buttonId: `${btn2}`, buttonText: { displayText: `${btn2}` }, type: 1 }
            ]

            const buttonMessage = {
                text: `${message}`,
                footer: `${footer}`,
                buttons: buttons,
                headerType: 1
            }
            conn.sendMessage(number, buttonMessage).then(response => {
                res.status(200).json({
                    status: true,
                    response: response
                });
            }).catch(err => {
                res.status(500).json({
                    status: false,
                    response: err
                });
            });
        } else {
            res.writeHead(401, {
                'Content-Type': 'application/json'
            });
            res.end(JSON.stringify({
                status: false,
                message: 'Please scan the QR before use the API 2'
            }));
        }
    });

};