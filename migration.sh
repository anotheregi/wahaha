#!/bin/bash

# Database migration script
MIGRATION_DIR="/var/www/html/migrations"
LOG_FILE="/var/log/migration.log"

# Function to log messages
log() {
    echo "$(date): $1" >> $LOG_FILE
}

# Check if migration directory exists
if [ ! -d "$MIGRATION_DIR" ]; then
    log "Migration directory not found: $MIGRATION_DIR"
    exit 1
fi

# Run migrations in order
for migration in $(ls $MIGRATION_DIR/*.sql | sort); do
    if [ -f "$migration" ]; then
        log "Running migration: $migration"
        mysql -h ${DB_HOSTNAME:-localhost} -u ${DB_USERNAME} -p${DB_PASSWORD} ${DB_DATABASE} < $migration

        if [ $? -eq 0 ]; then
            log "Migration successful: $migration"
            # Move to completed directory
            mkdir -p $MIGRATION_DIR/completed
            mv $migration $MIGRATION_DIR/completed/
        else
            log "Migration failed: $migration"
            exit 1
        fi
    fi
done

log "All migrations completed successfully"
