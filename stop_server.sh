#!/bin/bash

echo "Stopping Expense Tracker server..."

# Check if PID file exists
if [ -f server.pid ]; then
    SERVER_PID=$(cat server.pid)
    
    # Check if process is running
    if ps -p $SERVER_PID > /dev/null 2>&1; then
        kill $SERVER_PID
        echo "Server (PID: $SERVER_PID) stopped successfully"
        rm server.pid
    else
        echo "Server process (PID: $SERVER_PID) not found"
        echo "Cleaning up PID file..."
        rm server.pid
    fi
else
    echo "No server.pid file found"
    echo "Attempting to find and stop PHP server on port 8000..."
    
    # Try to kill by process name
    pkill -f 'php -S localhost:8000'
    
    if [ $? -eq 0 ]; then
        echo "Server stopped successfully"
    else
        echo "No running server found"
    fi
fi
