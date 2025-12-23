#!/bin/bash

# Health check script for the application
HEALTH_LOG="/var/log/healthcheck.log"

# Function to log health status
log_health() {
    echo "$(date): $1" >> $HEALTH_LOG
}

# Check Apache status
if pgrep -f apache2 > /dev/null; then
    log_health "Apache is running"
else
    log_health "Apache is not running"
    exit 1
fi

# Check Node.js server
if pgrep -f "node server.js" > /dev/null; then
    log_health "Node.js server is running"
else
    log_health "Node.js server is not running"
    exit 1
fi

# Check database connectivity
if mysql -h ${DB_HOSTNAME:-localhost} -u ${DB_USERNAME} -p${DB_PASSWORD} -e "SELECT 1;" ${DB_DATABASE} > /dev/null 2>&1; then
    log_health "Database connection is healthy"
else
    log_health "Database connection failed"
    exit 1
fi

# Check disk space
DISK_USAGE=$(df / | tail -1 | awk '{print $5}' | sed 's/%//')
if [ $DISK_USAGE -gt 90 ]; then
    log_health "Disk usage is high: $DISK_USAGE%"
    exit 1
else
    log_health "Disk usage is normal: $DISK_USAGE%"
fi

log_health "All health checks passed"
exit 0
