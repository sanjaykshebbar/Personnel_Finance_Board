<?php
// config/database.php

$dbPath = __DIR__ . '/../db/finance.db';
define('SYSTEM_START_DATE', '2026-02-01');

try {
    // Create (connect to) SQLite database in file
    $pdo = new PDO("sqlite:" . $dbPath);
    // Set errormode to exception
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

    // Enable foreign keys
    $pdo->exec("PRAGMA foreign_keys = ON;");

} catch(PDOException $e) {
    die("Connection failed: " . $e->getMessage());
}

/**
 * Initialize Database Schema
 */
function initDb($pdo) {
    // 0. Users Table
    $queries[] = "CREATE TABLE IF NOT EXISTS users (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        name TEXT NOT NULL,
        email TEXT UNIQUE NOT NULL,
        password TEXT NOT NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    )";

    // 1. Income Table
    $queries[] = "CREATE TABLE IF NOT EXISTS income (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        user_id INTEGER,
        month TEXT NOT NULL,
        accounting_date DATE,
        salary_income REAL DEFAULT 0,
        other_income REAL DEFAULT 0,
        total_income REAL DEFAULT 0,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
    )";

    // 2. Expenses Table
    $queries[] = "CREATE TABLE IF NOT EXISTS expenses (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        user_id INTEGER,
        date DATE NOT NULL,
        category TEXT NOT NULL,
        description TEXT,
        amount REAL NOT NULL,
        payment_method TEXT NOT NULL,
        target_account TEXT,
        linked_type TEXT, -- 'LOAN' or 'EMI'
        linked_id INTEGER,  -- The ID of the Loan or EMI record
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
    )";

    // 3. Investments Table
    $queries[] = "CREATE TABLE IF NOT EXISTS investments (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        user_id INTEGER,
        investment_name TEXT NOT NULL,
        frequency TEXT CHECK(frequency IN ('Monthly', 'Quarterly', 'Yearly')) NOT NULL,
        amount REAL NOT NULL,
        status TEXT CHECK(status IN ('Paid', 'Pending')) DEFAULT 'Pending',
        due_date DATE,
        plan_id INTEGER,
        expense_id INTEGER,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
    )";

    // 4. Loans (Lending/Borrowing) Table
    $queries[] = "CREATE TABLE IF NOT EXISTS loans (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        user_id INTEGER,
        person_name TEXT NOT NULL,
        type TEXT CHECK(type IN ('Lent', 'Borrowed')) NOT NULL,
        amount REAL NOT NULL,
        paid_amount REAL DEFAULT 0, -- FIXED: Added missing column
        status TEXT CHECK(status IN ('Pending', 'Settled')) DEFAULT 'Pending',
        date DATE NOT NULL,
        settlement_date DATE,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
    )";

    // 5. Credit Accounts Table
    $queries[] = "CREATE TABLE IF NOT EXISTS credit_accounts (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        user_id INTEGER,
        provider_name TEXT NOT NULL,
        credit_limit REAL NOT NULL,
        used_amount REAL DEFAULT 0,
        opening_balance REAL DEFAULT 0,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
    )";

    // 6. EMIs Table
    $queries[] = "CREATE TABLE IF NOT EXISTS emis (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        user_id INTEGER,
        name TEXT NOT NULL,
        total_amount REAL NOT NULL,
        interest_rate REAL DEFAULT 0,
        tenure_months INTEGER NOT NULL,
        emi_amount REAL NOT NULL,
        paid_months INTEGER DEFAULT 0,
        initial_paid_installments INTEGER DEFAULT 0,
        start_date DATE NOT NULL,
        status TEXT CHECK(status IN ('Active', 'Closed', 'Completed')) DEFAULT 'Active',
        expense_id INTEGER, -- The parent expense this EMI was converted from
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
    )";
    
    // Payslips Table (Vault)
    $queries[] = "CREATE TABLE IF NOT EXISTS payslips (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        user_id INTEGER NOT NULL,
        month TEXT NOT NULL,
        file_path TEXT NOT NULL,
        uploaded_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES users(id)
    )";

    // 7. Investment Plans Table
    $queries[] = "CREATE TABLE IF NOT EXISTS investment_plans (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        user_id INTEGER,
        name TEXT NOT NULL,
        category TEXT DEFAULT 'Other',
        type TEXT CHECK(type IN ('Monthly', 'Quarterly', 'Yearly')) NOT NULL,
        amount REAL NOT NULL,
        tenure_months INTEGER,
        paid_count INTEGER DEFAULT 0,
        start_date DATE NOT NULL,
        status TEXT CHECK(status IN ('Active', 'Closed', 'Completed')) DEFAULT 'Active',
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
    )";

    // Execute creation queries
    foreach ($queries as $q) {
        $pdo->exec($q);
    }

    // Migration: Add columns if they don't exist
    $tables = ['income', 'expenses', 'investments', 'loans', 'credit_accounts', 'emis', 'investment_plans'];
    foreach ($tables as $table) {
        // Check if columns exist
        $cols = $pdo->query("PRAGMA table_info($table)")->fetchAll();
        $existingCols = array_column($cols, 'name');
        
        if (!in_array('user_id', $existingCols)) {
            try { $pdo->exec("ALTER TABLE $table ADD COLUMN user_id INTEGER REFERENCES users(id) ON DELETE CASCADE"); } catch (Exception $e) {}
        }
        
        if ($table === 'income' && !in_array('accounting_date', $existingCols)) {
            try { $pdo->exec("ALTER TABLE income ADD COLUMN accounting_date DATE"); } catch (Exception $e) {}
        }

        if ($table === 'investment_plans' && !in_array('category', $existingCols)) {
            try { $pdo->exec("ALTER TABLE investment_plans ADD COLUMN category TEXT DEFAULT 'Other'"); } catch (Exception $e) {}
        }

        if ($table === 'investments') {
            if (!in_array('plan_id', $existingCols)) {
                try { $pdo->exec("ALTER TABLE investments ADD COLUMN plan_id INTEGER"); } catch (Exception $e) {}
            }
            if (!in_array('expense_id', $existingCols)) {
                try { $pdo->exec("ALTER TABLE investments ADD COLUMN expense_id INTEGER"); } catch (Exception $e) {}
            }
        }

        if ($table === 'expenses') {
            if (!in_array('converted_to_emi', $existingCols)) {
                try { $pdo->exec("ALTER TABLE expenses ADD COLUMN converted_to_emi INTEGER DEFAULT 0"); } catch (Exception $e) {}
            }
            if (!in_array('target_account', $existingCols)) {
                try { $pdo->exec("ALTER TABLE expenses ADD COLUMN target_account TEXT"); } catch (Exception $e) {}
            }
            if (!in_array('linked_type', $existingCols)) {
                try { $pdo->exec("ALTER TABLE expenses ADD COLUMN linked_type TEXT"); } catch (Exception $e) {}
            }
            if (!in_array('linked_id', $existingCols)) {
                try { $pdo->exec("ALTER TABLE expenses ADD COLUMN linked_id INTEGER"); } catch (Exception $e) {}
            }
        }

        if ($table === 'emis') {
            if (!in_array('payment_method', $existingCols)) {
                try { $pdo->exec("ALTER TABLE emis ADD COLUMN payment_method TEXT"); } catch (Exception $e) {}
            }
            if (!in_array('expense_id', $existingCols)) {
                try { $pdo->exec("ALTER TABLE emis ADD COLUMN expense_id INTEGER"); } catch (Exception $e) {}
            }
        }

        if ($table === 'loans') {
            if (!in_array('source_institution', $existingCols)) {
                try { $pdo->exec("ALTER TABLE loans ADD COLUMN source_institution TEXT"); } catch (Exception $e) {}
            }
            if (!in_array('tenure_months', $existingCols)) {
                try { $pdo->exec("ALTER TABLE loans ADD COLUMN tenure_months INTEGER DEFAULT 0"); } catch (Exception $e) {}
            }
            if (!in_array('paid_amount', $existingCols)) {
                try { $pdo->exec("ALTER TABLE loans ADD COLUMN paid_amount REAL DEFAULT 0"); } catch (Exception $e) {}
            }
            if (!in_array('emi_amount', $existingCols)) {
                try { $pdo->exec("ALTER TABLE loans ADD COLUMN emi_amount REAL DEFAULT 0"); } catch (Exception $e) {}
            }
            if (!in_array('interest_rate', $existingCols)) {
                try { $pdo->exec("ALTER TABLE loans ADD COLUMN interest_rate REAL DEFAULT 0"); } catch (Exception $e) {}
            }
            if (!in_array('paid_months', $existingCols)) {
                try { $pdo->exec("ALTER TABLE loans ADD COLUMN paid_months INTEGER DEFAULT 0"); } catch (Exception $e) {}
            }
            if (!in_array('sanction_doc', $existingCols)) {
                try { $pdo->exec("ALTER TABLE loans ADD COLUMN sanction_doc TEXT"); } catch (Exception $e) {}
            }
            if (!in_array('clearance_doc', $existingCols)) {
                try { $pdo->exec("ALTER TABLE loans ADD COLUMN clearance_doc TEXT"); } catch (Exception $e) {}
            }
            if (!in_array('loan_account_no', $existingCols)) {
                try { $pdo->exec("ALTER TABLE loans ADD COLUMN loan_account_no TEXT"); } catch (Exception $e) {}
            }
        }

        if ($table === 'credit_accounts') {
            if (!in_array('opening_balance', $existingCols)) {
                try { $pdo->exec("ALTER TABLE credit_accounts ADD COLUMN opening_balance REAL DEFAULT 0"); } catch (Exception $e) {}
            }
        }

        if ($table === 'emis') {
            if (!in_array('initial_paid_installments', $existingCols)) {
                try { $pdo->exec("ALTER TABLE emis ADD COLUMN initial_paid_installments INTEGER DEFAULT 0"); } catch (Exception $e) {}
            }
        }
    }

    // 8. DATA FIX: Reset negative used_amount (often caused by over-restoring during EMI conversions)
    try {
        $pdo->exec("UPDATE credit_accounts SET used_amount = 0 WHERE used_amount < 0");
    } catch (Exception $e) {}
}

// Auto-run init on include (for simplicity in this self-contained app)
initDb($pdo);
?>
