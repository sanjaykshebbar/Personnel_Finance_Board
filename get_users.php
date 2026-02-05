<?php
require_once 'config/database.php';
$stmt = $pdo->query("SELECT name, email FROM users");
$users = $stmt->fetchAll(PDO::FETCH_ASSOC);
foreach ($users as $u) {
    echo "Name: {$u['name']} | Email: {$u['email']}\n";
}
?>
