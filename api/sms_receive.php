<?php
// api/sms_receive.php
// Receives SMS messages forwarded from an Android SMS-forwarding app
// (MacroDroid / Tasker / similar), extracts transactions via AI, and
// queues them in ai_import_queue for review. Handles both live single-
// message forwarding and bulk backlog dumps.

set_time_limit(300);

require_once '../config/database.php';
require_once '../includes/api_auth.php';
require_once '../includes/AIParser.php';
require_once '../config/expense_categories.php';

const SMS_MAX_BATCH = 500;

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    header('Content-Type: application/json');
    die(json_encode(['error' => 'Method Not Allowed']));
}

header('Content-Type: application/json');

// 1. Auth - Bearer token against config/sms_sync_secret.txt
$rootDir = realpath(__DIR__ . '/../');
$secretFile = $rootDir . '/config/sms_sync_secret.txt';

$authHeader = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
if ($authHeader === '' && function_exists('getallheaders')) {
    $headers = getallheaders();
    $authHeader = $headers['Authorization'] ?? ($headers['authorization'] ?? '');
}
$providedToken = '';
if (preg_match('/Bearer\s+(.+)/i', $authHeader, $m)) {
    $providedToken = trim($m[1]);
}

if (!file_exists($secretFile)) {
    http_response_code(403);
    die(json_encode(['error' => 'Setup Error: config/sms_sync_secret.txt missing. Generate a token from Settings first.']));
}

if (!checkApiSecret($providedToken, $secretFile)) {
    http_response_code(401);
    die(json_encode(['error' => 'Unauthorized: invalid or missing Bearer token']));
}

// 2. Determine which user owns this data (this app is designed for single-user
// deployments; if multiple users exist, the request must specify user_id).
$body = json_decode(file_get_contents('php://input'), true);
if (!is_array($body)) {
    http_response_code(400);
    die(json_encode(['error' => 'Invalid JSON body']));
}

$users = $pdo->query("SELECT id FROM users")->fetchAll();
if (count($users) === 1) {
    $userId = $users[0]['id'];
} elseif (isset($body['user_id']) && ctype_digit((string)$body['user_id'])) {
    $userId = (int)$body['user_id'];
} else {
    http_response_code(400);
    die(json_encode(['error' => 'Multiple users exist on this server - include "user_id" in the request body.']));
}

// 3. Normalize input: accept either {messages: [...]} or a single {sender, body, timestamp}
if (isset($body['messages']) && is_array($body['messages'])) {
    $messages = $body['messages'];
} elseif (isset($body['body'])) {
    $messages = [$body];
} else {
    http_response_code(400);
    die(json_encode(['error' => 'Provide either "messages": [...] or a single {sender, body, timestamp}']));
}

if (count($messages) > SMS_MAX_BATCH) {
    http_response_code(400);
    die(json_encode(['error' => 'Too many messages in one request (max ' . SMS_MAX_BATCH . '). Split the backlog into smaller batches.']));
}

$categories = getExpenseCategories($userId);

$stats = ['received' => count($messages), 'queued' => 0, 'duplicates' => 0, 'skipped_non_transaction' => 0, 'errors' => 0];

$seenCheckStmt = $pdo->prepare("SELECT 1 FROM ai_sms_seen WHERE user_id = ? AND msg_hash = ?");
$markSeenStmt = $pdo->prepare("INSERT OR IGNORE INTO ai_sms_seen (user_id, msg_hash) VALUES (?, ?)");
$insertQueueStmt = $pdo->prepare(
    "INSERT OR IGNORE INTO ai_import_queue
     (user_id, batch_id, source, dedup_hash, raw_text, txn_type, date, amount, category, description, payment_method, target_account, confidence)
     VALUES (?, ?, 'sms', ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"
);

$batchId = 'sms_' . date('Ymd_His') . '_' . substr(bin2hex(random_bytes(4)), 0, 8);

try {
    $parser = new AIParser();
} catch (AIParserException $e) {
    http_response_code(503);
    die(json_encode(['error' => $e->getMessage()]));
}

foreach ($messages as $msg) {
    $sender = trim((string)($msg['sender'] ?? ''));
    $text = trim((string)($msg['body'] ?? ''));
    if ($text === '') { $stats['errors']++; continue; }

    $rawTimestamp = $msg['timestamp'] ?? null;
    $normalizedTs = normalizeSmsTimestamp($rawTimestamp);

    $msgHash = hash('sha256', 'sms|' . $sender . '|' . $text . '|' . $normalizedTs);

    $seenCheckStmt->execute([$userId, $msgHash]);
    if ($seenCheckStmt->fetch()) {
        $stats['duplicates']++;
        continue;
    }

    try {
        $transactions = $parser->parseText($text, 'sms', $categories);
    } catch (AIParserException $e) {
        $stats['errors']++;
        continue; // Don't mark as seen - allow retry on next sync.
    }

    if (empty($transactions)) {
        $stats['skipped_non_transaction']++;
    } else {
        foreach ($transactions as $i => $txn) {
            $rowHash = $msgHash . ':' . $i;
            $insertQueueStmt->execute([
                $userId, $batchId, $rowHash, $text,
                $txn['type'], $txn['date'], $txn['amount'], $txn['category'],
                $txn['description'], $txn['payment_method'], $txn['target_account'] ?: null, $txn['confidence'],
            ]);
            if ($insertQueueStmt->rowCount() > 0) {
                $stats['queued']++;
            } else {
                $stats['duplicates']++;
            }
        }
    }

    $markSeenStmt->execute([$userId, $msgHash]);
}

echo json_encode($stats);

function normalizeSmsTimestamp($raw) {
    if ($raw === null || $raw === '') return 0;
    if (is_numeric($raw)) return (int)$raw;
    $parsed = strtotime((string)$raw);
    return $parsed !== false ? $parsed : 0;
}
?>
