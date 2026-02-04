<?php
require_once 'config/database.php';
require_once 'includes/auth.php';

// Check EMIs
$stmt = $pdo->query("SELECT id, name FROM emis");
$emis = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo "--- EMIs currently in database ---\n";
foreach ($emis as $e) {
    echo "ID: " . $e['id'] . " | Name: " . $e['name'] . "\n";
}

// Check if OnePlus 15R exists
foreach ($emis as $e) {
    if (stripos($e['name'], 'OnePlus') !== false) {
        echo "\nFound OnePlus EMI! ID: " . $e['id'] . ". To delete this orphaned EMI, run this script with ?confirm=1\n";
        
        if (isset($_GET['confirm']) && $_GET['confirm'] == '1') {
            $pdo->prepare("DELETE FROM emis WHERE id = ?")->execute([$e['id']]);
            echo "DELETED EMI ID: " . $e['id'] . " successfully.\n";
        }
    }
}
?>
