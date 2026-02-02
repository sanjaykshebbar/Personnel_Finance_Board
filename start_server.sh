#!/bin/bash

echo "Starting Expense Tracker..."
echo "Server will run in detached mode and be accessible on local network"
echo ""

# Ensure database directory exists with proper permissions
DB_DIR="./db"
DB_FILE="$DB_DIR/finance.db"

if [ ! -d "$DB_DIR" ]; then
    echo "Creating database directory..."
    mkdir -p "$DB_DIR"
fi

# Set proper permissions for database directory
chmod 755 "$DB_DIR"

# If database file exists, ensure it's writable
if [ -f "$DB_FILE" ]; then
    chmod 644 "$DB_FILE"
    echo "Database file permissions updated"
else
    echo "Database will be created on first access"
fi

echo ""
echo "Access the site at:"
echo "  - Local: http://localhost:8000"
echo "  - Network: http://$(hostname -I | awk '{print $1}'):8000"
echo ""

# Start PHP server in detached mode using nohup, bound to all interfaces
nohup php -S 0.0.0.0:8000 > server.log 2>&1 &

# Get the process ID
SERVER_PID=$!

echo "Server started with PID: $SERVER_PID"
echo "Server output is being logged to: server.log"
echo ""
echo "To stop the server, run: kill $SERVER_PID"
echo "Or find and kill the process: pkill -f 'php -S localhost:8000'"
echo ""

# Save PID to file for easy stopping later
echo $SERVER_PID > server.pid
echo "PID saved to server.pid"
