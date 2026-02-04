#!/bin/bash

# OTA Update Script for Finance Board Application
# This script updates the application from Git repository

set -e  # Exit on error

# Configuration
APP_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
DB_DIR="$APP_DIR/db"
BACKUP_DIR="$DB_DIR/backups"
LOG_FILE="$APP_DIR/update.log"
VERSION_FILE="$APP_DIR/config/version.php"

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

# Logging function
log() {
    echo -e "${GREEN}[$(date +'%Y-%m-%d %H:%M:%S')]${NC} $1" | tee -a "$LOG_FILE"
}

error() {
    echo -e "${RED}[$(date +'%Y-%m-%d %H:%M:%S')] ERROR:${NC} $1" | tee -a "$LOG_FILE"
}

warning() {
    echo -e "${YELLOW}[$(date +'%Y-%m-%d %H:%M:%S')] WARNING:${NC} $1" | tee -a "$LOG_FILE"
}

# Create backup directory if it doesn't exist
mkdir -p "$BACKUP_DIR"

log "========================================="
log "Starting OTA Update Process"
log "========================================="

# Step 1: Check if Git is installed
if ! command -v git &> /dev/null; then
    error "Git is not installed. Please install git first."
    exit 1
fi

# Step 2: Check if we're in a Git repository
cd "$APP_DIR"
if [ ! -d ".git" ]; then
    error "This directory is not a Git repository."
    error "Please initialize Git or clone from a repository."
    exit 1
fi

# Step 3: Get current version/commit
CURRENT_COMMIT=$(git rev-parse --short HEAD)
log "Current commit: $CURRENT_COMMIT"

# Step 4: Backup database
TIMESTAMP=$(date +%Y%m%d_%H%M%S)
BACKUP_FILE="$BACKUP_DIR/finance_${TIMESTAMP}_${CURRENT_COMMIT}.db"

if [ -f "$DB_DIR/finance.db" ]; then
    log "Backing up database to: $BACKUP_FILE"
    cp "$DB_DIR/finance.db" "$BACKUP_FILE"
    log "Database backup completed"
else
    warning "Database file not found, skipping backup"
fi

# Step 5: Stash any local changes
log "Stashing local changes..."
git stash push -m "Auto-stash before update at $TIMESTAMP"

# Step 6: Fetch latest changes
log "Fetching latest changes from remote..."
git fetch origin

# Step 7: Check if updates are available
LOCAL=$(git rev-parse HEAD)
REMOTE=$(git rev-parse origin/$(git rev-parse --abbrev-ref HEAD))

if [ "$LOCAL" = "$REMOTE" ]; then
    log "Already up to date. No updates available."
    exit 0
fi

# Step 8: Pull latest changes
log "Pulling latest changes..."
if git pull origin $(git rev-parse --abbrev-ref HEAD); then
    NEW_COMMIT=$(git rev-parse --short HEAD)
    log "Successfully updated to commit: $NEW_COMMIT"
else
    error "Failed to pull changes. Rolling back..."
    git reset --hard "$CURRENT_COMMIT"
    exit 1
fi

# Step 9: Run database migrations if migration script exists
if [ -f "$APP_DIR/scripts/migrate.sh" ]; then
    log "Running database migrations..."
    bash "$APP_DIR/scripts/migrate.sh"
else
    log "No migration script found, skipping migrations"
fi

# Step 10: Update version file
if [ -f "$VERSION_FILE" ]; then
    log "Updating version file..."
    sed -i "s/LAST_UPDATE_COMMIT = '.*'/LAST_UPDATE_COMMIT = '$NEW_COMMIT'/" "$VERSION_FILE"
    sed -i "s/LAST_UPDATE_DATE = '.*'/LAST_UPDATE_DATE = '$(date +'%Y-%m-%d %H:%M:%S')'/" "$VERSION_FILE"
fi

# Step 11: Restart PHP server if running
log "Checking for running PHP server..."
if pgrep -f "php.*8000" > /dev/null; then
    log "Stopping PHP server..."
    pkill -f "php.*8000" || true
    sleep 2
    
    log "Starting PHP server..."
    cd "$APP_DIR"
    nohup php -S 0.0.0.0:8000 > /dev/null 2>&1 &
    log "PHP server restarted"
else
    log "PHP server not running, skipping restart"
fi

# Step 12: Clean up old backups (keep last 10)
log "Cleaning up old backups..."
cd "$BACKUP_DIR"
ls -t finance_*.db 2>/dev/null | tail -n +11 | xargs -r rm
log "Cleanup completed"

log "========================================="
log "Update completed successfully!"
log "Previous commit: $CURRENT_COMMIT"
log "New commit: $NEW_COMMIT"
log "========================================="

exit 0
