<?php
$pdo = new PDO('sqlite:c:/Users/sanjay.ks/Desktop/Work_Folder/Personnel_Project/expense-tracker/db/finance.db');
$pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

echo "USERS:\n";
print_r($pdo->query('SELECT * FROM users')->fetchAll());

echo "\nEMIS:\n";
print_r($pdo->query('SELECT * FROM emis')->fetchAll());

echo "\nLOANS:\n";
print_r($pdo->query('SELECT * FROM loans')->fetchAll());
