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
        
        $tables = ['emis', 'loans'];
        foreach ($tables as $table) {
            echo "Table: $table\n";
            // Search by amount (principal)
            $stmt = $pdo->prepare("SELECT * FROM $table WHERE total_amount = 15646 OR amount = 15646");
            $stmt->execute();
            $res = $stmt->fetchAll(PDO::FETCH_ASSOC);
            if ($res) {
                echo "Matches for principal 15646:\n";
                print_r($res);
            }
            
            // Search by emi amount
            $stmt = $pdo->prepare("SELECT * FROM $table WHERE emi_amount BETWEEN 1796 AND 1798");
            $stmt->execute();
            $res = $stmt->fetchAll(PDO::FETCH_ASSOC);
            if ($res) {
                echo "Matches for EMI ~1797:\n";
                print_r($res);
            }
        }
    } catch (Exception $e) {
        echo "Error: " . $e->getMessage() . "\n";
    }
}
