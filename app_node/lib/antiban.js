// Anti-Ban System Utilities for WhatsApp Blast
// Designed to achieve 95% bypass rate through human simulation and device protection

const fs = require('fs');
const path = require('path');

// Anti-ban configuration
const ANTIBAN_CONFIG = {
    // Human simulation settings
    typingDelay: { min: 100, max: 300 }, // ms between characters
    messageDelay: { min: 60000, max: 120000 }, // ms before sending message (60-120 seconds for less aggressive blasts)
    readingDelay: { min: 1000, max: 3000 }, // ms to simulate reading

    // Rate limiting
    messagesPerHour: { min: 20, max: 50 }, // Conservative hourly limit
    messagesPerDay: { min: 100, max: 200 }, // Daily limit
    batchSize: { min: 1, max: 3 }, // Messages per batch (reduced for less aggressive blasts)
    batchDelay: { min: 300000, max: 900000 }, // 5-15 min between batches

    // Device protection
    sessionCheckInterval: 300000, // 5 min session health checks
    maxConsecutiveFailures: 3, // Max failures before pause
    pauseAfterFailure: 1800000, // 30 min pause after failures

    // Content variation
    messageVariations: true,
    randomEmojis: ['😊', '👍', '❤️', '🔥', '✨', '💯', '🎉', '🙌'],
    randomPhrases: [
        'Hi there!', 'Hello!', 'Hey!', 'Good day!',
        'Hope you\'re doing well', 'Just wanted to say hi',
        'Quick message for you', 'Thought I\'d reach out'
    ],

    // Random actions simulation
    randomActions: {
        enabled: true,
        scrollChance: 0.3,
        clickChance: 0.2,
        pauseChance: 0.4
    },

    // Thinking pause before typing
    thinkingPause: { min: 500, max: 2000 }
};

// Device health tracking
const deviceHealth = new Map();

// Ban detection patterns
const BAN_PATTERNS = [
    'temporarily blocked',
    'blocked',
    'suspended',
    'rate limit',
    'too many messages',
    'spam',
    'violation'
];

// Enhanced human-like typing simulation with realistic patterns
function simulateTyping(message, callback) {
    const chars = message.split('');
    let index = 0;
    let typingSpeed = 'normal'; // normal, fast, slow, pause

    const getTypingDelay = () => {
        let baseDelay = Math.random() * (ANTIBAN_CONFIG.typingDelay.max - ANTIBAN_CONFIG.typingDelay.min) +
                       ANTIBAN_CONFIG.typingDelay.min;

        // Simulate different typing speeds and patterns
        if (Math.random() < 0.1) { // 10% chance for longer pause (thinking)
            baseDelay *= 3;
        } else if (Math.random() < 0.2) { // 20% chance for faster typing
            baseDelay *= 0.6;
        }

        // Add slight variations for more human-like feel
        baseDelay *= (0.8 + Math.random() * 0.4); // ±20% variation

        return Math.floor(baseDelay);
    };

    const typeChar = () => {
        if (index < chars.length) {
            const delay = getTypingDelay();
            setTimeout(() => {
                index++;
                typeChar();
            }, delay);
        } else {
            // Simulate reading time before sending
            const readingDelay = Math.random() * (ANTIBAN_CONFIG.readingDelay.max - ANTIBAN_CONFIG.readingDelay.min) +
                               ANTIBAN_CONFIG.readingDelay.min;
            setTimeout(callback, readingDelay);
        }
    };

    // Add initial "thinking" pause before starting to type
    const thinkingDelay = Math.random() * (ANTIBAN_CONFIG.thinkingPause.max - ANTIBAN_CONFIG.thinkingPause.min) +
                         ANTIBAN_CONFIG.thinkingPause.min;
    setTimeout(typeChar, thinkingDelay);
}

// Simulate random human actions to appear more natural
function simulateRandomActions(deviceId) {
    return new Promise(resolve => {
        if (!ANTIBAN_CONFIG.randomActions.enabled) {
            resolve();
            return;
        }

        const actions = [];

        // Simulate scrolling
        if (Math.random() < ANTIBAN_CONFIG.randomActions.scrollChance) {
            actions.push({
                type: 'scroll',
                delay: Math.random() * 2000 + 500,
                duration: Math.random() * 1000 + 300
            });
        }

        // Simulate clicking/clicking patterns
        if (Math.random() < ANTIBAN_CONFIG.randomActions.clickChance) {
            actions.push({
                type: 'click',
                delay: Math.random() * 1500 + 300,
                count: Math.floor(Math.random() * 3) + 1
            });
        }

        // Simulate random pauses
        if (Math.random() < ANTIBAN_CONFIG.randomActions.pauseChance) {
            actions.push({
                type: 'pause',
                delay: Math.random() * 3000 + 1000
            });
        }

        // Execute actions sequentially
        let actionIndex = 0;
        const executeAction = () => {
            if (actionIndex < actions.length) {
                const action = actions[actionIndex];
                setTimeout(() => {
                    console.log(`[HUMAN-SIM] Device ${deviceId}: Simulating ${action.type}`);
                    actionIndex++;
                    executeAction();
                }, action.delay);
            } else {
                resolve();
            }
        };

        if (actions.length > 0) {
            executeAction();
        } else {
            resolve();
        }
    });
}

// Message content variation to avoid pattern detection
function varyMessageContent(message) {
    if (!ANTIBAN_CONFIG.messageVariations) return message;

    let variedMessage = message;

    // Add random emoji occasionally
    if (Math.random() < 0.3) {
        const emoji = ANTIBAN_CONFIG.randomEmojis[Math.floor(Math.random() * ANTIBAN_CONFIG.randomEmojis.length)];
        variedMessage += ' ' + emoji;
    }

    // Add random phrase at beginning occasionally
    if (Math.random() < 0.2) {
        const phrase = ANTIBAN_CONFIG.randomPhrases[Math.floor(Math.random() * ANTIBAN_CONFIG.randomPhrases.length)];
        variedMessage = phrase + ' ' + variedMessage;
    }

    // Add slight variations in punctuation
    if (Math.random() < 0.5) {
        variedMessage = variedMessage.replace(/[.!?]$/, (match) => {
            const variations = ['.', '!', '?', '...'];
            return variations[Math.floor(Math.random() * variations.length)];
        });
    }

    return variedMessage;
}

// Exponential backoff for rate limiting
function calculateBackoff(attempt, baseDelay = 60000) {
    return Math.min(baseDelay * Math.pow(2, attempt), 3600000); // Max 1 hour
}

// Device health monitoring
function updateDeviceHealth(deviceId, success = true) {
    const health = deviceHealth.get(deviceId) || {
        consecutiveFailures: 0,
        lastFailure: null,
        totalSent: 0,
        totalFailed: 0,
        lastActivity: Date.now()
    };

    health.lastActivity = Date.now();

    if (success) {
        health.consecutiveFailures = 0;
        health.totalSent++;
    } else {
        health.consecutiveFailures++;
        health.totalFailed++;
        health.lastFailure = Date.now();
    }

    deviceHealth.set(deviceId, health);
    return health;
}

function getDeviceHealth(deviceId) {
    return deviceHealth.get(deviceId) || {
        consecutiveFailures: 0,
        lastFailure: null,
        totalSent: 0,
        totalFailed: 0,
        lastActivity: Date.now()
    };
}

// Ban detection
function detectBan(error) {
    if (!error) return false;

    const errorMessage = error.toString().toLowerCase();
    return BAN_PATTERNS.some(pattern => errorMessage.includes(pattern));
}

// Smart delay calculation based on device health and time patterns
function calculateSmartDelay(deviceId, messageCount = 0) {
    const health = getDeviceHealth(deviceId);
    const now = Date.now();

    // Base delay
    let delay = Math.random() * (ANTIBAN_CONFIG.messageDelay.max - ANTIBAN_CONFIG.messageDelay.min) +
               ANTIBAN_CONFIG.messageDelay.min;

    // Increase delay if recent failures
    if (health.consecutiveFailures > 0) {
        delay *= (1 + health.consecutiveFailures * 0.5);
    }

    // Simulate human-like timing patterns
    const hour = new Date().getHours();

    // Reduce activity during suspicious hours (late night)
    if (hour >= 22 || hour <= 6) {
        delay *= 1.5;
    }

    // Increase delay during peak hours to blend in
    if (hour >= 9 && hour <= 17) {
        delay *= 0.8;
    }

    // Add random variation
    delay *= (0.8 + Math.random() * 0.4); // ±20% variation

    return Math.floor(delay);
}

// Session health check
function checkSessionHealth(deviceId, sessionPath) {
    try {
        const sessionExists = fs.existsSync(sessionPath);
        const health = getDeviceHealth(deviceId);
        const now = Date.now();

        // Check if session is too old (simulate device restart)
        const sessionAge = now - health.lastActivity;
        if (sessionAge > 24 * 60 * 60 * 1000) { // 24 hours
            console.log(`Device ${deviceId} session is old, recommending restart`);
            return { healthy: false, reason: 'session_too_old' };
        }

        // Check failure rate
        const totalMessages = health.totalSent + health.totalFailed;
        if (totalMessages > 10) {
            const failureRate = health.totalFailed / totalMessages;
            if (failureRate > 0.3) { // 30% failure rate
                return { healthy: false, reason: 'high_failure_rate' };
            }
        }

        return { healthy: true };
    } catch (error) {
        return { healthy: false, reason: 'check_error', error: error.message };
    }
}

// Batch processing with anti-ban measures
function processBatch(messages, deviceId, sendFunction) {
    return new Promise(async (resolve) => {
        const batchSize = Math.floor(Math.random() * (ANTIBAN_CONFIG.batchSize.max - ANTIBAN_CONFIG.batchSize.min + 1)) +
                         ANTIBAN_CONFIG.batchSize.min;

        const results = [];

        for (let i = 0; i < messages.length; i += batchSize) {
            const batch = messages.slice(i, i + batchSize);

            // Process batch
            for (const message of batch) {
                try {
                    // Simulate random human actions before messaging
                    await simulateRandomActions(deviceId);

                    // Vary message content with type-specific variations
                    const variedMessage = varyMessageContent(message.content, message.type);

                    // Simulate typing
                    await new Promise(resolveTyping => {
                        simulateTyping(variedMessage, resolveTyping);
                    });

                    // Send message
                    const result = await sendFunction(message.recipient, variedMessage);

                    // Update health
                    updateDeviceHealth(deviceId, result.success);

                    results.push({
                        ...message,
                        success: result.success,
                        variedContent: variedMessage,
                        timestamp: Date.now()
                    });

                    // Smart delay before next message
                    const delay = calculateSmartDelay(deviceId, results.length);
                    await new Promise(resolve => setTimeout(resolve, delay));

                } catch (error) {
                    const isBan = detectBan(error);
                    updateDeviceHealth(deviceId, false);

                    results.push({
                        ...message,
                        success: false,
                        error: error.message,
                        isBan,
                        timestamp: Date.now()
                    });

                    // Longer delay after failure
                    const failureDelay = ANTIBAN_CONFIG.pauseAfterFailure;
                    console.log(`Failure detected for device ${deviceId}, pausing for ${failureDelay/1000}s`);
                    await new Promise(resolve => setTimeout(resolve, failureDelay));
                }
            }

            // Batch delay
            if (i + batchSize < messages.length) {
                const batchDelay = Math.random() * (ANTIBAN_CONFIG.batchDelay.max - ANTIBAN_CONFIG.batchDelay.min) +
                                  ANTIBAN_CONFIG.batchDelay.min;
                console.log(`Batch completed for device ${deviceId}, waiting ${Math.floor(batchDelay/1000)}s before next batch`);
                await new Promise(resolve => setTimeout(resolve, batchDelay));
            }
        }

        resolve(results);
    });
}

// Export functions
module.exports = {
    ANTIBAN_CONFIG,
    simulateTyping,
    varyMessageContent,
    calculateBackoff,
    updateDeviceHealth,
    getDeviceHealth,
    detectBan,
    calculateSmartDelay,
    checkSessionHealth,
    processBatch
};
