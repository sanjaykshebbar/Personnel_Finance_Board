<?php
require_once 'config/database.php';
$stmt = $pdo->query("SELECT * FROM emis");
$rows = $stmt->fetchAll();
foreach ($rows as $row) {
    echo "ID: " . $row['id'] . " | Name: " . $row['name'] . " | EMI: " . $row['emi_amount'] . " | P: " . $row['total_amount'] . " | R: " . $row['interest_rate'] . " | T: " . $row['tenure_months'] . "\n";
    
    // Manual check
    $p = $row['total_amount'];
    $r_ann = $row['interest_rate'];
    $n = $row['tenure_months'];
    $r = ($r_ann / 100) / 12;
    if ($r > 0) {
        $expected = ($p * $r * pow(1 + $r, $n)) / (pow(1 + $r, $n) - 1);
    } else {
        $expected = $p / $n;
    }
    echo "  Expected: " . $expected . " (Interest Total: " . ($expected * $n - $p) . ")\n";
}
