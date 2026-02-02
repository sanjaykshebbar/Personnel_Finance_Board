#!/bin/bash

# Rollback Script for Finance Board Application
# This script rolls back to a previous version

set -e

# Configuration
APP_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
DB_DIR="$APP_DIR/db"
BACKUP_DIR="$DB_DIR/backups"
LOG_FILE="$APP_DIR/update.log"

# Colors
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m'

log() {
    echo -e "${GREEN}[$(date +'%Y-%m-%d %H:%M:%S')]${NC} $1" | tee -a "$LOG_FILE"
}

error() {
    echo -e "${RED}[$(date +'%Y-%m-%d %H:%M:%S')] ERROR:${NC} $1" | tee -a "$LOG_FILE"
}

log "========================================="
log "Starting Rollback Process"
log "========================================="

# Check if backup directory exists
if [ ! -d "$BACKUP_DIR" ]; then
    error "Backup directory not found: $BACKUP_DIR"
    exit 1
fi

# List available backups
log "Available backups:"
BACKUPS=($(ls -t "$BACKUP_DIR"/finance_*.db 2>/dev/null))

if [ ${#BACKUPS[@]} -eq 0 ]; then
    error "No backups found"
    exit 1
fi

# Show backups with index
for i in "${!BACKUPS[@]}"; do
    BACKUP_NAME=$(basename "${BACKUPS[$i]}")
    echo "  [$i] $BACKUP_NAME"
done

# If argument provided, use it; otherwise use the most recent
if [ -n "$1" ]; then
    BACKUP_INDEX=$1
else
    BACKUP_INDEX=0
    log "No backup specified, using most recent backup"
fi

if [ $BACKUP_INDEX -ge ${#BACKUPS[@]} ]; then
    error "Invalid backup index: $BACKUP_INDEX"
    exit 1
fi

SELECTED_BACKUP="${BACKUPS[$BACKUP_INDEX]}"
BACKUP_NAME=$(basename "$SELECTED_BACKUP")

log "Selected backup: $BACKUP_NAME"

# Extract commit hash from backup filename
if [[ $BACKUP_NAME =~ _([a-f0-9]+)\.db$ ]]; then
    TARGET_COMMIT="${BASH_REMATCH[1]}"
    log "Target commit: $TARGET_COMMIT"
else
    warning "Could not extract commit hash from backup filename"
    TARGET_COMMIT=""
fi

# Restore database
log "Restoring database from backup..."
cp "$SELECTED_BACKUP" "$DB_DIR/finance.db"
log "Database restored"

# Rollback Git if commit hash is available
if [ -n "$TARGET_COMMIT" ]; then
    cd "$APP_DIR"
    if git rev-parse --verify "$TARGET_COMMIT" >/dev/null 2>&1; then
        log "Rolling back Git to commit: $TARGET_COMMIT"
        git reset --hard "$TARGET_COMMIT"
        log "Git rollback completed"
    else
        warning "Commit $TARGET_COMMIT not found in repository"
    fi
fi

# Restart PHP server
log "Restarting PHP server..."
pkill -f "php.*8000" || true
sleep 2

cd "$APP_DIR"
nohup php -S 0.0.0.0:8000 > /dev/null 2>&1 &
log "PHP server restarted"

log "========================================="
log "Rollback completed successfully!"
log "========================================="

exit 0
