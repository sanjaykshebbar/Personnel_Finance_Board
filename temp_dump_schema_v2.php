<?php
require_once 'config/database.php';
$stmt = $pdo->query("SELECT sql FROM sqlite_master WHERE type='table'");
$output = "";
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $output .= $row['sql'] . ";\n\n";
}
file_put_contents('schema.sql', $output);
echo "Schema written to schema.sql\n";
?>
