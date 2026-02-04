CREATE TABLE users (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        name TEXT NOT NULL,
        email TEXT UNIQUE NOT NULL,
        password TEXT NOT NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    );

CREATE TABLE sqlite_sequence(name,seq);

CREATE TABLE income (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        user_id INTEGER,
        month TEXT NOT NULL,
        accounting_date DATE,
        salary_income REAL DEFAULT 0,
        other_income REAL DEFAULT 0,
        total_income REAL DEFAULT 0,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
    );

CREATE TABLE expenses (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        user_id INTEGER,
        date DATE NOT NULL,
        category TEXT NOT NULL,
        description TEXT,
        amount REAL NOT NULL,
        payment_method TEXT NOT NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP, converted_to_emi INTEGER DEFAULT 0, target_account TEXT, linked_type TEXT, linked_id INTEGER,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
    );

CREATE TABLE investments (
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
    );

CREATE TABLE loans (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        user_id INTEGER,
        person_name TEXT NOT NULL,
        type TEXT CHECK(type IN ('Lent', 'Borrowed')) NOT NULL,
        amount REAL NOT NULL,
        paid_amount REAL DEFAULT 0,
        status TEXT CHECK(status IN ('Pending', 'Settled')) DEFAULT 'Pending',
        date DATE NOT NULL,
        settlement_date DATE,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP, source_institution TEXT, tenure_months INTEGER DEFAULT 0, emi_amount REAL DEFAULT 0, interest_rate REAL DEFAULT 0, paid_months INTEGER DEFAULT 0, sanction_doc TEXT, clearance_doc TEXT, loan_account_no TEXT,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
    );

CREATE TABLE credit_accounts (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        user_id INTEGER,
        provider_name TEXT NOT NULL,
        credit_limit REAL NOT NULL,
        used_amount REAL DEFAULT 0,
        opening_balance REAL DEFAULT 0,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
    );

CREATE TABLE emis (
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
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP, payment_method TEXT, expense_id INTEGER,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
    );

CREATE TABLE payslips (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        user_id INTEGER NOT NULL,
        month TEXT NOT NULL,
        file_path TEXT NOT NULL,
        uploaded_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES users(id)
    );

CREATE TABLE investment_plans (
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
    );
