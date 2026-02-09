<?php
$dbPath = 'C:\\xampp\\htdocs\\finance-board\\expense-tracker\\db\\finance.db';
if (!file_exists($dbPath)) {
    die("Secondary DB not found at $dbPath\n");
}

try {
    $pdo = new PDO("sqlite:" . $dbPath);
    echo "SEARCHING SECONDARY DB:\n";
    
    echo "\nSEARCHING FOR 15646 IN EXPENSES:\n";
    $stmt = $pdo->prepare("SELECT * FROM expenses WHERE amount = 15646");
    $stmt->execute();
    print_r($stmt->fetchAll());

    echo "\nSEARCHING FOR 'Cred Cash' IN EMIS:\n";
    $stmt = $pdo->prepare("SELECT * FROM emis WHERE name LIKE '%Cred Cash%'");
    $stmt->execute();
    print_r($stmt->fetchAll());
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
