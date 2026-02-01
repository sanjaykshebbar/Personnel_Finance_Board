#!/bin/bash

# 💰 Expense Tracker Installer
# Repository: https://github.com/sanjaykshebbar/Personnel_Finance_Board

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

echo -e "${BLUE}=======================================${NC}"
echo -e "${BLUE}   💰 Expense Tracker Installer       ${NC}"
echo -e "${BLUE}=======================================${NC}"

# Check for dependencies
echo -e "\n${BLUE}Step 1: Checking Dependencies...${NC}"
dependencies=("git" "php" "sqlite3")
for dep in "${dependencies[@]}"; do
    if ! command -v $dep &> /dev/null; then
        echo -e "${RED}Error: $dep is not installed.${NC}"
        echo -e "Please install it by running: sudo apt update && sudo apt install -y $dep"
        exit 1
    fi
done
echo -e "${GREEN}All dependencies found!${NC}"

# Clone repository
echo -e "\n${BLUE}Step 2: Cloning Repository...${NC}"
if [ -d "Personnel_Finance_Board" ]; then
    echo -e "${RED}Directory 'Personnel_Finance_Board' already exists. Skipping clone.${NC}"
else
    git clone https://github.com/sanjaykshebbar/Personnel_Finance_Board.git
fi
cd Personnel_Finance_Board || exit

# Create necessary directories
echo -e "\n${BLUE}Step 3: Setting up Directories & Permissions...${NC}"
mkdir -p db uploads
chmod -R 775 db uploads
# Attempt to set www-data as owner if running as root/sudo
if [ "$EUID" -eq 0 ]; then
    chown -R www-data:www-data db uploads
fi
echo -e "${GREEN}Permissions set.${NC}"

# Final Instructions
echo -e "\n${GREEN}=======================================${NC}"
echo -e "${GREEN}   ✅ Installation Complete!          ${NC}"
echo -e "${GREEN}=======================================${NC}"

echo -e "\n${BLUE}📍 Database Location:${NC}"
echo -e "Your data is stored in: ${BLUE}$(pwd)/db/finance.db${NC}"

echo -e "\n${BLUE}💾 Backup & Restore:${NC}"
echo -e "1. ${BLUE}Backup:${NC} Copy the 'db/finance.db' file to a safe location."
echo -e "   Command: cp db/finance.db /path/to/backup/"
echo -e "2. ${BLUE}Restore:${NC} Replace 'db/finance.db' with your backup file."
echo -e "   Command: cp /path/to/backup/finance.db db/"

echo -e "\n${BLUE}🚀 How to Start:${NC}"
echo -e "Run the following command to start the built-in server:"
echo -e "   ${BLUE}php -S 0.0.0.0:8000${NC}"
echo -e "\nOr configure Apache/Nginx to serve this directory."
echo -e "Access the app at: http://your-ip:8000"
echo -e "${BLUE}=======================================${NC}\n"
