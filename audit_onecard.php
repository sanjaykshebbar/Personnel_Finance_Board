<?php
require_once 'config/database.php';

echo "SYSTEM_START_DATE: " . SYSTEM_START_DATE . "\n\n";

echo "--- Credit Accounts ---\n";
$stmt = $pdo->query("SELECT * FROM credit_accounts");
print_r($stmt->fetchAll());

echo "\n--- All Expenses for OneCard ---\n";
$stmt = $pdo->prepare("SELECT * FROM expenses WHERE TRIM(LOWER(payment_method)) = 'onecard'");
$stmt->execute();
print_r($stmt->fetchAll());

echo "\n--- All EMIs for OneCard ---\n";
$stmt = $pdo->prepare("SELECT * FROM emis WHERE TRIM(LOWER(payment_method)) = 'onecard'");
$stmt->execute();
print_r($stmt->fetchAll());
?>
