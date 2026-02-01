#!/bin/bash

# 💰 Expense Tracker Automated Installer
# Supported OS: Debian / Ubuntu / Raspberry Pi OS

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
BLUE='\033[0;34m'
NC='\033[0m' 

echo -e "${BLUE}=======================================${NC}"
echo -e "${BLUE}   💰 Expense Tracker Installer       ${NC}"
echo -e "${BLUE}=======================================${NC}"

# 1. Check for Sudo
if [ "$EUID" -ne 0 ]; then
  echo -e "${RED}Please run as root or with sudo.${NC}"
  exit 1
fi

# 2. Update and Install Dependencies
echo -e "\n${BLUE}Step 1: Installing System Dependencies...${NC}"
apt update
apt install -y apache2 php libapache2-mod-php php-sqlite3 sqlite3 git

# 3. Enable Apache Modules
echo -e "\n${BLUE}Step 2: Configuring Web Server...${NC}"
a2enmod rewrite
systemctl restart apache2

# 4. Clone and Deploy
TARGET_DIR="/var/www/html/expense-tracker"
REPO_URL="https://github.com/sanjaykshebbar/Personnel_Finance_Board.git"

echo -e "\n${BLUE}Step 3: Deploying Project to $TARGET_DIR...${NC}"

if [ -d "$TARGET_DIR" ]; then
    echo -e "${BLUE}Target directory exists. Updating...${NC}"
    cd "$TARGET_DIR" || exit
    git pull
else
    echo -e "${BLUE}Cloning repository...${NC}"
    git clone "$REPO_URL" "$TARGET_DIR"
fi

# 5. Set Permissions
echo -e "\n${BLUE}Step 4: Setting Permissions...${NC}"
cd "$TARGET_DIR" || exit
mkdir -p db uploads
chown -R www-data:www-data "$TARGET_DIR"
chmod -R 755 "$TARGET_DIR"
chmod -R 775 db uploads

# 6. Final Instructions
echo -e "\n${GREEN}=======================================${NC}"
echo -e "${GREEN}   ✅ Installation Complete!          ${NC}"
echo -e "${GREEN}=======================================${NC}"

IP_ADDR=$(hostname -I | awk '{print $1}')
echo -e "\n${BLUE}🚀 Access your Board at:${NC}"
echo -e "   http://$IP_ADDR/expense-tracker"
echo -e "\n${BLUE}📍 Project Location:${NC}"
echo -e "   $TARGET_DIR"

echo -e "\n${BLUE}💾 Database File:${NC}"
echo -e "   $TARGET_DIR/db/finance.db"
echo -e "${BLUE}=======================================${NC}\n"
