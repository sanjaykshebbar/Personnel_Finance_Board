<?php
require_once 'config/database.php';

echo "SEARCHING FOR 15646 IN EXPENSES:\n";
$stmt = $pdo->prepare("SELECT * FROM expenses WHERE amount = 15646");
$stmt->execute();
print_r($stmt->fetchAll());

echo "\nSEARCHING FOR 'Cred Cash' IN EMIS:\n";
$stmt = $pdo->prepare("SELECT * FROM emis WHERE name LIKE '%Cred Cash%'");
$stmt->execute();
print_r($stmt->fetchAll());
