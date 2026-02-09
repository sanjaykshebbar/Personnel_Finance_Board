<?php
$dbPath = 'C:\\xampp\\htdocs\\finance-board\\expense-tracker\\db\\finance.db';
if (!file_exists($dbPath)) {
    die("Secondary DB not found at $dbPath\n");
}
$pdo = new PDO('sqlite:' . $dbPath);
$pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

echo "USERS:\n";
print_r($pdo->query('SELECT * FROM users')->fetchAll());

echo "\nEMIS:\n";
print_r($pdo->query('SELECT * FROM emis')->fetchAll());

echo "\nLOANS:\n";
print_r($pdo->query('SELECT * FROM loans')->fetchAll());
