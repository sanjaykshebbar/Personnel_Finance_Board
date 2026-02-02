#!/bin/bash

################################################################################
# Finance Board - Linux Installation Script
# Supports: Ubuntu, Debian, Raspberry Pi OS
################################################################################

set -e  # Exit on error

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Configuration
INSTALL_DIR="/var/www/html/Personnel_Finance_Board"
REPO_URL="https://github.com/sanjaykshebbar/Personnel_Finance_Board.git"
PHP_VERSION="8.1"

# Print colored message
print_msg() {
    echo -e "${GREEN}[INFO]${NC} $1"
}

print_error() {
    echo -e "${RED}[ERROR]${NC} $1"
}

print_warning() {
    echo -e "${YELLOW}[WARNING]${NC} $1"
}

print_header() {
    echo -e "${BLUE}========================================${NC}"
    echo -e "${BLUE}$1${NC}"
    echo -e "${BLUE}========================================${NC}"
}

# Check if running as root or with sudo
check_sudo() {
    if [ "$EUID" -ne 0 ]; then 
        print_error "This script must be run with sudo privileges"
        print_msg "Please run: sudo bash install.sh"
        exit 1
    fi
}

# Detect OS
detect_os() {
    if [ -f /etc/os-release ]; then
        . /etc/os-release
        OS=$ID
        VER=$VERSION_ID
        print_msg "Detected OS: $PRETTY_NAME"
    else
        print_error "Cannot detect OS"
        exit 1
    fi
}

# Update package list
update_packages() {
    print_header "Updating Package List"
    apt-get update -qq
    print_msg "Package list updated"
}

# Install dependencies
install_dependencies() {
    print_header "Installing Dependencies"
    
    # Try to detect installed PHP version first, otherwise fallback to default
    if command -v php >/dev/null 2>&1; then
        DETECTED_PHP=$(php -r 'echo PHP_MAJOR_VERSION.".".PHP_MINOR_VERSION;')
        print_msg "Detected existing PHP version: $DETECTED_PHP"
        PHP_VERSION=$DETECTED_PHP
    fi

    PACKAGES=(
        "git"
        "sqlite3"
        "curl"
        "unzip"
        "php-sqlite3"
        "php-mbstring"
        "php-xml"
        "php-curl"
        "php-zip"
        "php${PHP_VERSION}"
        "php${PHP_VERSION}-sqlite3"
        "php${PHP_VERSION}-mbstring"
        "php${PHP_VERSION}-xml"
        "php${PHP_VERSION}-curl"
        "php${PHP_VERSION}-zip"
    )
    
    print_msg "Checking and installing required packages..."
    
    # Build a list of missing packages
    MISSING_PACKAGES=()
    for package in "${PACKAGES[@]}"; do
        if ! dpkg -l | grep -q "^ii  $package "; then
            MISSING_PACKAGES+=("$package")
        fi
    done

    if [ ${#MISSING_PACKAGES[@]} -eq 0 ]; then
        print_msg "All dependencies are already installed"
    else
        print_msg "Installing: ${MISSING_PACKAGES[*]}"
        apt-get install -y -qq "${MISSING_PACKAGES[@]}" || {
            print_warning "Batch installation failed, trying individual packages..."
            for pkg in "${MISSING_PACKAGES[@]}"; do
                print_msg "Installing $pkg..."
                apt-get install -y -qq "$pkg" || print_warning "Couldn't install $pkg (may be optional or handled by another package)"
            done
        }
    fi
    
    print_msg "Dependency check completed"
}

# Clone or update repository
setup_repository() {
    print_header "Setting Up Repository"
    
    if [ -d "$INSTALL_DIR" ]; then
        print_warning "Directory $INSTALL_DIR already exists"
        read -p "Do you want to remove it and reinstall? (y/N): " -n 1 -r
        echo
        if [[ $REPLY =~ ^[Yy]$ ]]; then
            print_msg "Removing existing directory..."
            rm -rf "$INSTALL_DIR"
        else
            print_msg "Updating existing installation..."
            cd "$INSTALL_DIR"
            git pull origin main || git pull origin master
            return
        fi
    fi
    
    print_msg "Cloning repository from $REPO_URL"
    mkdir -p "$(dirname "$INSTALL_DIR")"
    git clone "$REPO_URL" "$INSTALL_DIR"
    
    cd "$INSTALL_DIR"
    print_msg "Repository cloned successfully"
}

# Set up database
setup_database() {
    print_header "Setting Up Database"
    
    mkdir -p "$INSTALL_DIR/db"
    mkdir -p "$INSTALL_DIR/db/backups"
    
    # Set permissions
    chown -R www-data:www-data "$INSTALL_DIR/db"
    chmod -R 755 "$INSTALL_DIR/db"
    
    print_msg "Database directory created"
}

# Set file permissions
set_permissions() {
    print_header "Setting File Permissions"
    
    # Set ownership
    chown -R www-data:www-data "$INSTALL_DIR"
    
    # Set directory permissions
    find "$INSTALL_DIR" -type d -exec chmod 755 {} \;
    
    # Set file permissions
    find "$INSTALL_DIR" -type f -exec chmod 644 {} \;
    
    # Make scripts executable
    if [ -d "$INSTALL_DIR/scripts" ]; then
        chmod +x "$INSTALL_DIR/scripts"/*.sh 2>/dev/null || true
    fi
    
    print_msg "Permissions set correctly"
}

# Configure sudoers for www-data (for OTA updates)
configure_sudoers() {
    print_header "Configuring Sudo Access for Updates"
    
    SUDOERS_FILE="/etc/sudoers.d/finance-board-updates"
    
    cat > "$SUDOERS_FILE" << 'EOF'
# Allow www-data to run git commands for OTA updates
www-data ALL=(ALL) NOPASSWD: /usr/bin/git pull *
www-data ALL=(ALL) NOPASSWD: /usr/bin/git fetch *
www-data ALL=(ALL) NOPASSWD: /usr/bin/git reset *
www-data ALL=(ALL) NOPASSWD: /usr/bin/git stash *
www-data ALL=(ALL) NOPASSWD: /usr/bin/git rev-parse *
www-data ALL=(ALL) NOPASSWD: /usr/bin/git rev-list *
EOF
    
    chmod 440 "$SUDOERS_FILE"
    print_msg "Sudo access configured for OTA updates"
}

# Create systemd service
create_service() {
    print_header "Creating Systemd Service"
    
    SERVICE_FILE="/etc/systemd/system/finance-board.service"
    
    cat > "$SERVICE_FILE" << EOF
[Unit]
Description=Finance Board PHP Server
After=network.target

[Service]
Type=simple
User=www-data
Group=www-data
WorkingDirectory=$INSTALL_DIR
ExecStart=/usr/bin/php -S 0.0.0.0:8000 -t $INSTALL_DIR
Restart=always
RestartSec=3

[Install]
WantedBy=multi-user.target
EOF
    
    systemctl daemon-reload
    systemctl enable finance-board.service
    
    print_msg "Systemd service created and enabled"
}

# Start the service
start_service() {
    print_header "Starting Finance Board"
    
    systemctl start finance-board.service
    
    sleep 2
    
    if systemctl is-active --quiet finance-board.service; then
        print_msg "Finance Board is running!"
    else
        print_error "Failed to start Finance Board"
        systemctl status finance-board.service
        exit 1
    fi
}

# Get server IP
get_server_ip() {
    IP=$(hostname -I | awk '{print $1}')
    if [ -z "$IP" ]; then
        IP="localhost"
    fi
    echo "$IP"
}

# Print completion message
print_completion() {
    print_header "Installation Complete!"
    
    IP=$(get_server_ip)
    
    echo ""
    echo -e "${GREEN}✅ Finance Board has been installed successfully!${NC}"
    echo ""
    echo -e "${BLUE}Access your application at:${NC}"
    echo -e "  ${GREEN}http://$IP:8000${NC}"
    echo -e "  ${GREEN}http://localhost:8000${NC} (if accessing locally)"
    echo ""
    echo -e "${BLUE}Useful commands:${NC}"
    echo -e "  ${YELLOW}sudo systemctl status finance-board${NC}  - Check service status"
    echo -e "  ${YELLOW}sudo systemctl restart finance-board${NC} - Restart service"
    echo -e "  ${YELLOW}sudo systemctl stop finance-board${NC}    - Stop service"
    echo -e "  ${YELLOW}sudo journalctl -u finance-board -f${NC}  - View logs"
    echo ""
    echo -e "${BLUE}Installation directory:${NC} $INSTALL_DIR"
    echo ""
    echo -e "${YELLOW}Note:${NC} For OTA updates, use the System Update page in the web interface"
    echo ""
}

# Main installation flow
main() {
    print_header "Finance Board - Linux Installer"
    
    check_sudo
    detect_os
    update_packages
    install_dependencies
    setup_repository
    setup_database
    set_permissions
    configure_sudoers
    create_service
    start_service
    print_completion
}

# Run main function
main
