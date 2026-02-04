<?php
require_once 'config/database.php';
$stmt = $pdo->query("SELECT * FROM credit_accounts");
print_r($stmt->fetchAll(PDO::FETCH_ASSOC));
?>
