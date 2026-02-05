<?php
require_once '../config/database.php';
require_once '../includes/auth.php';
requireLogin();

$userId = getCurrentUserId();

// Filters from GET (same as expenses.php)
$filterMonth = $_GET['month'] ?? date('Y-m');
$filterCategory = $_GET['category'] ?? '';
$filterMethod = $_GET['method'] ?? '';

// Build Query
$query = "SELECT date, category, description, amount, payment_method, target_account FROM expenses WHERE strftime('%Y-%m', date) = ? AND user_id = ?";
$params = [$filterMonth, $userId];

if ($filterCategory) {
    $query .= " AND category = ?";
    $params[] = $filterCategory;
}
if ($filterMethod) {
    $query .= " AND payment_method = ?";
    $params[] = $filterMethod;
}

$query .= " ORDER BY date DESC, id DESC";

try {
    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    $expenses = $stmt->fetchAll();

    // Set headers for download
    $filename = "expenses_" . ($filterMonth ?: 'all') . "_" . date('Ymd_His') . ".csv";
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=' . $filename);

    $output = fopen('php://output', 'w');

    // Header row
    fputcsv($output, ['Date', 'Category', 'Description', 'Amount', 'Payment Method', 'Target Account']);

    foreach ($expenses as $row) {
        fputcsv($output, [
            $row['date'],
            $row['category'],
            $row['description'],
            $row['amount'],
            $row['payment_method'],
            $row['target_account']
        ]);
    }

    fclose($output);
    exit;

} catch (Exception $e) {
    die("Error exporting data: " . $e->getMessage());
}
