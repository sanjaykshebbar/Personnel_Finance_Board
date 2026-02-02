<?php
require_once 'config/database.php';
$stmt = $pdo->prepare("SELECT * FROM expenses WHERE date >= '2026-01-20' AND date < '2026-01-27'");
$stmt->execute();
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
echo json_encode($rows, JSON_PRETTY_PRINT);
