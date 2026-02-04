<?php
$dbPath = 'C:/xampp/htdocs/finance-tracker/db/finance.db';
if (!file_exists($dbPath)) {
    die("DB not found at $dbPath\n");
}
$pdo = new PDO('sqlite:' . $dbPath);
echo "DB Path: " . realpath($dbPath) . "\n";
echo "--- Credit Accounts ---\n";
$stmt = $pdo->query("SELECT id, provider_name, used_amount FROM credit_accounts");
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    echo "ID: {$row['id']} | Name: {$row['provider_name']} | Initial: {$row['used_amount']}\n";
    if (strpos(strtolower($row['provider_name']), 'axis') !== false) {
        echo ">> FOUND AXIS CARD <<\n";
        if ($row['used_amount'] != 0) {
            echo ">> FIXING AXIS CARD <<\n";
            $pdo->prepare("UPDATE credit_accounts SET used_amount = 0 WHERE id = ?")->execute([$row['id']]);
            echo ">> NEW VALUE: 0 <<\n";
        }
    }
}
?>
