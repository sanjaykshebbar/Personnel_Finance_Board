# 💰 Self-Hosted Expense Tracker

A premium, lightweight personal finance dashboard built for high-performance and privacy. Track your income, expenses, investments, and loans in one beautiful interface.

![Dashboard Preview](https://via.placeholder.com/800x400?text=Premium+Finance+Dashboard)

## ✨ Core Features

- **📊 Dynamic Dashboard**: Real-time charts for Spending Categories, Borrowed vs Lent, and Total Savings Breakdown.
- **📱 Mobile-First Navigation**: Smooth bottom-sheet navigation for a native app-like experience on smartphones.
- **🏦 Smart Income Tracking**: Shift your budget months based on your actual salary cycle (e.g., Jan 31st salary affects February budget).
- **💸 Expense Ledger**: Robust tracking with payment method support (Bank vs Credit Card) and automatic category grouping.
- **📈 Investment Vault**: Track SIPs, fixed deposits, and recurring investments with progress tracking and variable payment logging.
- **🛡️ Document Vault**: Organize your financial documents (payslips, receipts, sanction letters) in a secure, directory-based file manager.
- **🤝 Lending Tracker**: Manage money lent and borrowed with automated settlement logic and progress bars.
- **💳 Credit Monitor**: Track card limits and real-time utilization to maintain high credit scores.
- **🔄 Universal OTA Updates**: Perform one-click updates directly from the web interface across Windows, Linux, and Docker.

## 🚀 Easy Deployment

### Option 1: Automated Installer (Linux/Raspberry Pi)
The fastest way to install on a fresh Linux server. This script installs dependencies, sets up the app, and configures everything for you.
```bash
# Download and run the installer
curl -sS https://raw.githubusercontent.com/sanjaykshebbar/Personnel_Finance_Board/master/install.sh | sudo bash
```

### Option 2: Docker (Recommended)
Perfect for any machine with Docker. Highly portable and persistent.

1. **Clone the repository**:
   ```bash
   git clone https://github.com/sanjaykshebbar/Personnel_Finance_Board.git
   cd Personnel_Finance_Board
   ```
2. **Start with Docker Compose**:
   ```bash
   docker compose up -d
   ```
3. **Access the App**: Navigate to `http://localhost:8080`.

> [!NOTE]
> Data is persisted in the local `./db` and `./uploads` folders.

## 🔄 Updating the Application

This board features a built-in **Universal Over-The-Air (OTA) Update System**.

1. Navigate to the **System Update** (🔄) menu.
2. Click **Check for Updates** to see if a newer version is available.
3. Click **Update Now** to automatically backup your database, pull the latest code, and restart the service.

> [!TIP]
> **Automatic Backups**: The system creates a database backup before every update. You can rollback to any previous version using the **Rollback** button in the update UI.

## 🛰️ Maintenance & Backup

### Backup
Your entire financial history is stored in a single file.
- **Location**: `db/finance.db`
- **Backup Command**: `cp db/finance.db /path/to/backup/finance.db_$(date +%F)`

### Restore
1. Copy your backup file back to the `db/` folder.
2. Rename it to `finance.db`.
3. Ensure the web server has write permissions: `chmod 775 db/finance.db`.

---

### Option 2: Manual (PHP + SQLite)
1. **Requirements**: PHP 8.1+, SQLite3.
2. **Setup**:
   - Ensure the `db/` and `uploads/` directories are writable.
   - Run a web server (Apache/Nginx) pointing to the root directory.
3. **Portable Server (Local dev)**:
   ```bash
   php -S localhost:8000
   ```

## 🛠️ Technical Stack
- **Backend**: Pure PHP 8.2 (No bloated frameworks)
- **Database**: SQLite3 (Serverless, ultra-portable)
- **Frontend**: Vanilla CSS + Tailwind CSS (Native Glassmorphism UI)
- **Charts**: Chart.js for data visualization
- **Docker**: Health-monitored containers for 24/7 reliability

## 📁 Project Structure
- `pages/`: Core application modules (Income, Expenses, etc.)
- `config/`: Database connection and auto-initialization
- `includes/`: Reusable UI components (Header, Footer, Nav)
- `db/`: Secure SQLite database storage
- `uploads/`: Document vault storage
- `assets/`: Custom CSS and themes

## 🤝 Contributing
Feel free to fork this project and submit pull requests for any features or bug fixes.

## 📄 License
MIT License. Free to use, modify, and host.
