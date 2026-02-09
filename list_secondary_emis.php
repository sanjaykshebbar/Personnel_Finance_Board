<?php
$dbPath = 'C:\\xampp\\htdocs\\finance-board\\expense-tracker\\db\\finance.db';
if (!file_exists($dbPath)) {
    die("Secondary DB not found at $dbPath\n");
}

try {
    $pdo = new PDO("sqlite:" . $dbPath);
    $stmt = $pdo->query("SELECT * FROM emis");
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo "EMIs in SECONDARY DB:\n";
    foreach ($rows as $row) {
        echo "ID: " . $row['id'] . " | Name: " . $row['name'] . " | EMI: " . $row['emi_amount'] . " | P: " . $row['total_amount'] . " | R: " . $row['interest_rate'] . " | T: " . $row['tenure_months'] . "\n";
    }
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
