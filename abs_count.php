<?php
$dbPath = 'c:\\Users\\sanjay.ks\\Desktop\\Work_Folder\\Personnel_Project\\expense-tracker\\db\\finance.db';
$pdo = new PDO("sqlite:" . $dbPath);
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

echo "DB PATH: $dbPath\n";
echo "FILE SIZE: " . filesize($dbPath) . "\n";

$tables = ['users', 'income', 'expenses', 'loans', 'credit_accounts', 'emis', 'investment_plans'];
foreach ($tables as $t) {
    try {
        $count = $pdo->query("SELECT COUNT(*) FROM $t")->fetchColumn();
        echo "$t: $count\n";
        if ($count > 0) {
            $data = $pdo->query("SELECT * FROM $t LIMIT 1")->fetch();
            echo "  (Sample from $t: " . json_encode($data) . ")\n";
        }
    } catch (Exception $e) {
        echo "$t: ERROR (" . $e->getMessage() . ")\n";
    }
}
