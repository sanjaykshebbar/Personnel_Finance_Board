<?php
require_once '../config/database.php';
require_once '../includes/auth.php';
require_once '../includes/AIParser.php';
require_once '../config/expense_categories.php';
requireLogin();

$userId = getCurrentUserId();
$pageTitle = 'AI Import';

function isPdftotextAvailable() {
    $isWindows = strtoupper(substr(PHP_OS, 0, 3)) === 'WIN';
    $check = $isWindows ? 'where pdftotext 2>&1' : 'which pdftotext 2>&1';
    $output = [];
    $returnVar = 0;
    exec($check, $output, $returnVar);
    return $returnVar === 0;
}

function queueTransactions($pdo, $userId, $batchId, $source, $rawText, array $transactions) {
    $stmt = $pdo->prepare(
        "INSERT OR IGNORE INTO ai_import_queue
         (user_id, batch_id, source, dedup_hash, raw_text, txn_type, date, amount, category, description, payment_method, target_account, confidence)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"
    );
    $baseHash = hash('sha256', $source . '|' . $rawText);
    $queued = 0;
    foreach ($transactions as $i => $txn) {
        $rowHash = $baseHash . ':' . $i;
        $stmt->execute([
            $userId, $batchId, $source, $rowHash, $rawText,
            $txn['type'], $txn['date'], $txn['amount'], $txn['category'],
            $txn['description'], $txn['payment_method'], $txn['target_account'] ?: null, $txn['confidence'],
        ]);
        if ($stmt->rowCount() > 0) $queued++;
    }
    return $queued;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $categories = getExpenseCategories($userId);

    if (isset($_POST['action']) && $_POST['action'] === 'parse_paste') {
        $rawText = trim($_POST['raw_text'] ?? '');
        if ($rawText === '') {
            $error = 'Please paste some text first.';
        } else {
            try {
                $parser = new AIParser();
                $transactions = $parser->parseText($rawText, 'paste', $categories);
                if (empty($transactions)) {
                    $error = "AI couldn't find a transaction in that text. Try including an amount and what it was for.";
                } else {
                    $batchId = 'paste_' . date('Ymd_His') . '_' . substr(bin2hex(random_bytes(4)), 0, 8);
                    queueTransactions($pdo, $userId, $batchId, 'paste', $rawText, $transactions);
                    header("Location: ai_review.php?batch=" . urlencode($batchId));
                    exit;
                }
            } catch (AIParserException $e) {
                $error = $e->getMessage();
            }
        }
    } elseif (isset($_POST['action']) && $_POST['action'] === 'parse_upload') {
        if (!isset($_FILES['statement_file']) || $_FILES['statement_file']['error'] !== UPLOAD_ERR_OK) {
            $error = 'Please choose a file to upload.';
        } else {
            $tmpPath = $_FILES['statement_file']['tmp_name'];
            $originalName = $_FILES['statement_file']['name'];
            $ext = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));

            $rawText = '';
            if ($ext === 'csv') {
                $rawText = file_get_contents($tmpPath);
            } elseif ($ext === 'pdf') {
                if (!isPdftotextAvailable()) {
                    $error = 'PDF support is unavailable on this server (poppler-utils / pdftotext not found). Try exporting your statement as CSV instead, or install poppler-utils.';
                } else {
                    $cmd = 'pdftotext -layout ' . escapeshellarg($tmpPath) . ' -';
                    $rawText = shell_exec($cmd);
                }
            } else {
                $error = 'Unsupported file type. Please upload a .csv or .pdf statement.';
            }

            if (!$error) {
                $rawText = trim((string)$rawText);
                if ($rawText === '') {
                    $error = 'Could not extract any text from that file.';
                } else {
                    try {
                        $parser = new AIParser();
                        $transactions = $parser->parseText($rawText, 'upload', $categories);
                        if (empty($transactions)) {
                            $error = "AI couldn't find any transactions in that file.";
                        } else {
                            $batchId = 'upload_' . date('Ymd_His') . '_' . substr(bin2hex(random_bytes(4)), 0, 8);
                            $queued = queueTransactions($pdo, $userId, $batchId, 'upload', $rawText, $transactions);
                            header("Location: ai_review.php?batch=" . urlencode($batchId));
                            exit;
                        }
                    } catch (AIParserException $e) {
                        $error = $e->getMessage();
                    }
                }
            }
        }
    }
}

$aiReady = isAiConfigured();

// Pending review count for the badge
$pendingCount = $pdo->prepare("SELECT COUNT(*) AS c FROM ai_import_queue WHERE user_id = ? AND status = 'pending'");
$pendingCount->execute([$userId]);
$pendingCount = (int)($pendingCount->fetch()['c'] ?? 0);

require_once '../includes/header.php';
?>

<div class="max-w-3xl mx-auto space-y-6">

    <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
        <div>
            <h1 class="text-2xl font-bold text-gray-900 dark:text-white flex items-center gap-2">
                <span>✨</span> AI Import
            </h1>
            <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">Paste a note or upload a statement — AI will extract the transactions for you to review.</p>
        </div>
        <?php if ($pendingCount > 0): ?>
            <a href="ai_review.php" class="inline-flex items-center gap-2 bg-amber-100 text-amber-800 dark:bg-amber-900/40 dark:text-amber-300 px-4 py-2 rounded-xl text-sm font-bold hover:bg-amber-200 transition">
                📋 <?php echo $pendingCount; ?> Pending Review
            </a>
        <?php endif; ?>
    </div>

    <?php if (!$aiReady): ?>
        <div class="bg-red-50 border border-red-200 text-red-800 dark:bg-red-900/20 dark:border-red-900/40 dark:text-red-300 px-4 py-3 rounded-xl text-sm">
            AI features aren't configured yet. Set <code class="font-mono">ANTHROPIC_API_KEY</code> in your <code class="font-mono">.env</code> file to enable AI Import.
        </div>
    <?php endif; ?>

    <?php if ($error): ?>
        <div class="bg-red-50 border border-red-200 text-red-800 dark:bg-red-900/20 dark:border-red-900/40 dark:text-red-300 px-4 py-3 rounded-xl text-sm">
            <?php echo htmlspecialchars($error); ?>
        </div>
    <?php endif; ?>

    <!-- Paste Text -->
    <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-lg border border-gray-100 dark:border-gray-700 p-6">
        <h2 class="font-bold text-gray-900 dark:text-white mb-2">Paste Text</h2>
        <p class="text-xs text-gray-500 dark:text-gray-400 mb-4">A bank SMS, an email alert, or a quick note like "1200 groceries big bazaar yesterday, paid via HDFC card".</p>
        <form method="POST" class="space-y-4">
            <input type="hidden" name="action" value="parse_paste">
            <textarea name="raw_text" rows="5" required <?php echo $aiReady ? '' : 'disabled'; ?>
                placeholder="Paste your text here..."
                class="w-full p-3 bg-gray-50 dark:bg-gray-900 border border-gray-200 dark:border-gray-700 rounded-xl text-sm focus:ring-2 focus:ring-brand-500 outline-none"></textarea>
            <button type="submit" <?php echo $aiReady ? '' : 'disabled'; ?>
                class="bg-brand-600 hover:bg-brand-700 disabled:opacity-50 text-white font-bold py-2.5 px-6 rounded-xl shadow-lg shadow-brand-500/20 transition active:scale-95">
                Extract Transaction
            </button>
        </form>
    </div>

    <!-- Upload File -->
    <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-lg border border-gray-100 dark:border-gray-700 p-6">
        <h2 class="font-bold text-gray-900 dark:text-white mb-2">Upload Statement</h2>
        <p class="text-xs text-gray-500 dark:text-gray-400 mb-4">Upload a CSV or PDF bank statement. AI will read through it and extract every transaction.</p>
        <form method="POST" enctype="multipart/form-data" class="space-y-4">
            <input type="hidden" name="action" value="parse_upload">
            <input type="file" name="statement_file" accept=".csv,.pdf" required <?php echo $aiReady ? '' : 'disabled'; ?>
                class="block w-full text-xs text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-full file:border-0 file:text-xs file:font-semibold file:bg-brand-50 file:text-brand-700 hover:file:bg-brand-100 cursor-pointer">
            <button type="submit" <?php echo $aiReady ? '' : 'disabled'; ?>
                class="bg-brand-600 hover:bg-brand-700 disabled:opacity-50 text-white font-bold py-2.5 px-6 rounded-xl shadow-lg shadow-brand-500/20 transition active:scale-95">
                Extract Transactions
            </button>
        </form>
    </div>

</div>

<?php require_once '../includes/footer.php'; ?>
