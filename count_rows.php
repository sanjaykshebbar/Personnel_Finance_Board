<?php
require_once 'config/database.php';
$tables = ['users', 'income', 'expenses', 'loans', 'credit_accounts', 'emis', 'investment_plans'];
foreach ($tables as $t) {
    try {
        $count = $pdo->query("SELECT COUNT(*) FROM $t")->fetchColumn();
        echo "$t: $count\n";
    } catch (Exception $e) {
        echo "$t: ERROR (" . $e->getMessage() . ")\n";
    }
}
