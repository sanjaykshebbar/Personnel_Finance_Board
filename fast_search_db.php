<?php
$dbPaths = [
    'c:\Users\sanjay.ks\Desktop\Work_Folder\Personnel_Project\expense-tracker\db\finance.db',
    'C:\xampp\htdocs\finance-board\expense-tracker\db\finance.db'
];

foreach ($dbPaths as $dbPath) {
    if (!file_exists($dbPath)) continue;
    echo "INSPECTING DB: $dbPath\n";
    try {
        $pdo = new PDO("sqlite:" . $dbPath);
        
        echo "Searching EMIS table...\n";
        $stmt = $pdo->prepare("SELECT * FROM emis WHERE total_amount BETWEEN 15640 AND 15650 OR emi_amount BETWEEN 1790 AND 1800");
        $stmt->execute();
        $res = $stmt->fetchAll(PDO::FETCH_ASSOC);
        if ($res) {
            print_r($res);
        }
        
        echo "Searching LOANS table...\n";
        $stmt = $pdo->prepare("SELECT * FROM loans WHERE amount BETWEEN 15640 AND 15650 OR emi_amount BETWEEN 1790 AND 1800");
        $stmt->execute();
        $res = $stmt->fetchAll(PDO::FETCH_ASSOC);
        if ($res) {
            print_r($res);
        }
    } catch (Exception $e) {
        echo "Error: " . $e->getMessage() . "\n";
    }
}
