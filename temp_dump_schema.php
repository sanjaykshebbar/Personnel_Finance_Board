<?php
require_once 'config/database.php';
$stmt = $pdo->query("SELECT sql FROM sqlite_master WHERE type='table'");
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    echo $row['sql'] . ";\n\n";
}
?>
