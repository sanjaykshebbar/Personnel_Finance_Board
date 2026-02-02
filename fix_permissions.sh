#!/bin/bash

echo "Fixing database permissions for Expense Tracker..."
echo ""

# Database paths
DB_DIR="./db"
DB_FILE="$DB_DIR/finance.db"

# Create database directory if it doesn't exist
if [ ! -d "$DB_DIR" ]; then
    echo "Creating database directory..."
    mkdir -p "$DB_DIR"
    echo "✓ Database directory created"
else
    echo "✓ Database directory exists"
fi

# Set directory permissions (read, write, execute for owner; read, execute for others)
chmod 755 "$DB_DIR"
echo "✓ Database directory permissions set to 755"

# Handle database file
if [ -f "$DB_FILE" ]; then
    # Set file permissions (read, write for owner; read for others)
    chmod 644 "$DB_FILE"
    echo "✓ Database file permissions set to 644"
    
    # Show file info
    echo ""
    echo "Database file info:"
    ls -lh "$DB_FILE"
else
    echo "⚠ Database file does not exist yet"
    echo "  It will be created automatically on first access"
fi

# Check if we can write to the directory
if [ -w "$DB_DIR" ]; then
    echo ""
    echo "✓ Database directory is writable"
else
    echo ""
    echo "✗ WARNING: Database directory is NOT writable"
    echo "  You may need to run: sudo chown -R $USER:$USER $DB_DIR"
fi

echo ""
echo "Permissions fix complete!"
