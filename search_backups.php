<?php
$dbs = [
    'c:\Users\sanjay.ks\Desktop\Work_Folder\Personnel_Project\expense-tracker\db\finance.db',
    'c:\Users\sanjay.ks\Desktop\Work_Folder\Personnel_Project\expense-tracker\db\backups\finance_20260202_153557_unknown.db',
    'c:\Users\sanjay.ks\Desktop\Work_Folder\Personnel_Project\expense-tracker\db\backups\finance_20260202_154122_5f04c67.db',
    'C:\xampp\htdocs\finance-board\expense-tracker\db\finance.db'
];

foreach ($dbs as $dbPath) {
    if (!file_exists($dbPath)) continue;
    echo "INSPECTING DB: $dbPath\n";
    try {
        $pdo = new PDO("sqlite:" . $dbPath);
        $tables = ['emis', 'loans'];
        foreach ($tables as $table) {
            $stmt = $pdo->prepare("SELECT * FROM $table WHERE name LIKE '%38RGPWW1EPX%' OR person_name LIKE '%38RGPWW1EPX%'");
            $stmt->execute();
            $res = $stmt->fetchAll(PDO::FETCH_ASSOC);
            if ($res) {
                echo "FOUND IN $table:\n";
                print_r($res);
            }
        }
    } catch (Exception $e) {
        echo "Error: " . $e->getMessage() . "\n";
    }
}
