<?php
foreach (['C:\\Users\\sanjay.ks\\Desktop\\Work_Folder\\Personnel_Project\\expense-tracker\\db\\finance.db', 'C:\\xampp\\htdocs\\finance-board\\expense-tracker\\db\\finance.db'] as $dbPath) {
    if (!file_exists($dbPath)) continue;
    echo "INSPECTING DB: $dbPath\n";
    try {
        $pdo = new PDO("sqlite:" . $dbPath);
        echo "EMIS:\n";
        $stmt = $pdo->query("SELECT * FROM emis WHERE name LIKE '%Cred Cash%' OR name LIKE '%38RGPWW1EPX%'");
        print_r($stmt->fetchAll(PDO::FETCH_ASSOC));
        
        echo "LOANS:\n";
        $stmt = $pdo->query("SELECT * FROM loans WHERE person_name LIKE '%Cred Cash%' OR person_name LIKE '%38RGPWW1EPX%'");
        print_r($stmt->fetchAll(PDO::FETCH_ASSOC));

        echo "EXPENSES with amount approx 15646:\n";
        $stmt = $pdo->query("SELECT * FROM expenses WHERE amount BETWEEN 15640 AND 15650");
        print_r($stmt->fetchAll(PDO::FETCH_ASSOC));
    } catch (Exception $e) {
        echo "Error: " . $e->getMessage() . "\n";
    }
    echo "---------------------------------\n";
}
