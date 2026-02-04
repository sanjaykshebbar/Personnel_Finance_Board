<?php
$db = 'db/finance.db';
$pdo = new PDO('sqlite:' . $db);
$stmt = $pdo->query("SELECT * FROM credit_accounts");
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
echo "Count: " . count($rows) . "\n";
print_r($rows);
?>
