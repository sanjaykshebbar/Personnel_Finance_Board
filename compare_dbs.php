<?php
$dbs = [
    'C:/Users/sanjay.ks/Desktop/Work_Folder/Personnel_Project/expense-tracker/db/finance.db',
    'C:/xampp/htdocs/finance-board/expense-tracker/db/finance.db'
];

foreach ($dbs as $db) {
    echo "--- Checking $db ---\n";
    if (!file_exists($db)) {
        echo "NOT FOUND\n";
        continue;
    }
    $pdo = new PDO('sqlite:' . $db);
    $tables = ['credit_accounts', 'expenses', 'emis'];
    foreach ($tables as $t) {
        $count = $pdo->query("SELECT count(*) FROM $t")->fetchColumn();
        echo "Table $t: $count rows\n";
    }
    
    $axis = $pdo->query("SELECT * FROM credit_accounts WHERE provider_name LIKE '%Axis%'")->fetch(PDO::FETCH_ASSOC);
    if ($axis) {
        echo "AXIS CARD FOUND: {$axis['provider_name']} | Initial: {$axis['used_amount']}\n";
    } else {
        echo "AXIS CARD NOT FOUND\n";
    }
    echo "\n";
}
?>
