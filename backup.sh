#!/bin/bash

# Database backup script
BACKUP_DIR="/var/www/html/backups"
TIMESTAMP=$(date +"%Y%m%d_%H%M%S")
BACKUP_FILE="$BACKUP_DIR/backup_$TIMESTAMP.sql"
LOG_FILE="/var/log/backup.log"

# Function to log messages
log() {
    echo "$(date): $1" >> $LOG_FILE
}

# Create backup directory if it doesn't exist
mkdir -p $BACKUP_DIR

# Perform database backup
log "Starting database backup: $BACKUP_FILE"
mysqldump -h ${DB_HOSTNAME:-localhost} -u ${DB_USERNAME} -p${DB_PASSWORD} ${DB_DATABASE} > $BACKUP_FILE

if [ $? -eq 0 ]; then
    log "Database backup successful: $BACKUP_FILE"

    # Compress the backup
    gzip $BACKUP_FILE
    log "Backup compressed: $BACKUP_FILE.gz"

    # Clean up old backups (keep last 7 days)
    find $BACKUP_DIR -name "backup_*.sql.gz" -mtime +7 -delete
    log "Old backups cleaned up"
else
    log "Database backup failed"
    exit 1
fi

log "Backup process completed"
