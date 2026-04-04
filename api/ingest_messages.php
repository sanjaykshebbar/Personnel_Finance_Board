<?php
require_once '../config/database.php';
require_once '../includes/auth.php';
require_once '../includes/classification.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$userId = getCurrentUserId();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method Not Allowed']);
    exit;
}

$input = file_get_contents('php://input');
$data = json_decode($input, true);

if (!$data) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid JSON']);
    exit;
}

$messages = isset($data[0]) ? $data : [$data];

$inserted = 0;
$staged = 0;

foreach ($messages as $msg) {
    $sender = $msg['sender'] ?? 'Unknown';
    $text = $msg['message_text'] ?? $msg['text'] ?? '';
    $ts = isset($msg['timestamp']) ? date('Y-m-d H:i:s', strtotime($msg['timestamp'])) : date('Y-m-d H:i:s');
    
    if (empty($text)) continue;

    $category = ClassificationService::classify($text, $sender);
    $isStaged = 0;

    // Automatic Staging Logic
    $autoStageCats = ['Bank_Transactions', 'Credit_Card', 'Loan_EMI'];
    $amount = 0;
    
    if (in_array($category, $autoStageCats)) {
        // Simple regex to find amount
        if (preg_match('/(?:Rs|INR|₹)\s?(\d+(?:\.\d+)?)/i', $text, $matches) || 
            preg_match('/(\d+(?:\.\d+)?)\s?(?:debited|credited)/i', $text, $matches)) {
            $amount = floatval($matches[1]);
        }
    }

    try {
        $pdo->beginTransaction();
        
        // 1. Insert message
        $stmt = $pdo->prepare("INSERT INTO messages (user_id, sender, message_text, category, timestamp, is_staged) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->execute([$userId, $sender, $text, $category, $ts, ($amount > 0 ? 1 : 0)]);
        $messageId = $pdo->lastInsertId();
        
        // 2. If it's a payment, add to quick_entries
        if ($amount > 0) {
            $qStmt = $pdo->prepare("INSERT INTO quick_entries (user_id, amount, description, payment_method, date, message_id, category) VALUES (?, ?, ?, ?, ?, ?, ?)");
            $desc = "$sender: " . mb_strimwidth($text, 0, 50, "...");
            $method = ($category === 'Credit_Card') ? 'Credit Card' : 'UPI'; // Smart guestimate
            $qStmt->execute([$userId, $amount, $desc, $method, date('Y-m-d', strtotime($ts)), $messageId, $category]);
            $staged++;
        }

        $pdo->commit();
        $inserted++;
    } catch (PDOException $e) {
        $pdo->rollBack();
        continue;
    }
}

echo json_encode([
    'status' => 'success', 
    'inserted' => $inserted,
    'staged' => $staged
]);
