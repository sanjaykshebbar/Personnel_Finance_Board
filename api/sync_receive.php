<?php
header('Content-Type: application/json');
require_once '../config/database.php';

// 1. Authenticate
$headers = getallheaders();
$receivedKey = $headers['X-Sync-Key'] ?? $_SERVER['HTTP_X_SYNC_KEY'] ?? '';

$secretFile = __DIR__ . '/../config/sync_secret.txt';
if (!file_exists($secretFile)) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Sync not configured on receiver']);
    exit;
}

$realKey = trim(file_get_contents($secretFile));
if ($receivedKey !== $realKey) {
    http_response_code(403);
    echo json_encode(['status' => 'error', 'message' => 'Invalid Secret Key']);
    exit;
}

// 2. Handle Handshake/Check
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    echo json_encode(['status' => 'online', 'message' => 'Ready to sync']);
    exit;
}

// 3. Handle Data Sync (POST)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!$input) {
        echo json_encode(['status' => 'error', 'message' => 'Invalid JSON']);
        exit;
    }

    try {
        $pdo->beginTransaction();

        // Sync Expenses
        if (isset($input['expenses']) && is_array($input['expenses'])) {
            $stmt = $pdo->prepare("INSERT OR REPLACE INTO expenses (id, user_id, date, category, description, amount, payment_method, is_recurring, created_at) VALUES (:id, :user_id, :date, :category, :description, :amount, :payment_method, :is_recurring, :created_at)");
            foreach ($input['expenses'] as $row) {
                // Sanitize/Validate if needed
                $stmt->execute([
                    ':id' => $row['id'],
                    ':user_id' => $row['user_id'],
                    ':date' => $row['date'],
                    ':category' => $row['category'],
                    ':description' => $row['description'],
                    ':amount' => $row['amount'],
                    ':payment_method' => $row['payment_method'],
                    ':is_recurring' => $row['is_recurring'] ?? 0,
                    ':created_at' => $row['created_at'] ?? date('Y-m-d H:i:s')
                ]);
            }
        }

        // Sync Income
        if (isset($input['income']) && is_array($input['income'])) {
            $stmt = $pdo->prepare("INSERT OR REPLACE INTO income (id, user_id, source, amount, date, description, month, total_income) VALUES (:id, :user_id, :source, :amount, :date, :description, :month, :total_income)");
            foreach ($input['income'] as $row) {
                $stmt->execute([
                    ':id' => $row['id'],
                    ':user_id' => $row['user_id'],
                    ':source' => $row['source'],
                    ':amount' => $row['amount'],
                    ':date' => $row['date'],
                    ':description' => $row['description'],
                    ':month' => $row['month'],
                    ':total_income' => $row['total_income']
                ]);
            }
        }

        $pdo->commit();
        echo json_encode(['status' => 'success', 'message' => 'Data synced successfully']);

    } catch (Exception $e) {
        $pdo->rollBack();
        http_response_code(500);
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    }
}
?>
