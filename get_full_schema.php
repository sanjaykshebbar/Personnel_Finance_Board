<?php
require_once 'config/database.php';
$stmt = $pdo->query("SELECT sql FROM sqlite_master WHERE type='table' AND name='emis'");
$sql = $stmt->fetchColumn();
file_put_contents('schema_output.txt', $sql);
echo "Schema written to schema_output.txt\n";
?>
