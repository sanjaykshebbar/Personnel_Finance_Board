<?php
$found = glob('C:/xampp/htdocs/*/db/finance.db');
if (empty($found)) {
    // Try one level deeper or higher
    $found = array_merge($found, glob('C:/xampp/htdocs/db/finance.db'));
    $found = array_merge($found, glob('C:/xampp/htdocs/*/*/db/finance.db'));
}

foreach ($found as $dbPath) {
    echo "Processing $dbPath...\n";
    $pdo = new PDO('sqlite:' . $dbPath);
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
}
?>
