<?php
require_once '../config/database.php';
require_once '../includes/auth.php';
require_once '../config/expense_categories.php';
requireLogin();

$userId = getCurrentUserId();
$pageTitle = 'Review AI Import';

/**
 * Derive the income "month" from an accounting date, replicating the
 * >20th-of-month-shifts-to-next-month rule from pages/income.php's client JS
 * (there's no browser step in this flow, so it needs to happen server-side).
 */
function deriveIncomeMonth($accountingDate) {
    $day = (int)date('d', strtotime($accountingDate));
    $month = $accountingDate;
    if ($day > 20) {
        $month = date('Y-m-d', strtotime($accountingDate . ' +1 month'));
    }
    return date('Y-m', strtotime($month));
}

/**
 * Insert an approved queue row into expenses or income, matching the exact
 * column shapes used by pages/expenses.php and pages/income.php.
 */
function commitApprovedTransaction($pdo, $userId, array $row) {
    if ($row['txn_type'] === 'income') {
        $month = deriveIncomeMonth($row['date']);
        $stmt = $pdo->prepare(
            "INSERT INTO income (user_id, month, accounting_date, salary_income, other_income, total_income) VALUES (?, ?, ?, 0, ?, ?)"
        );
        $stmt->execute([$userId, $month, $row['date'], $row['amount'], $row['amount']]);
    } else {
        $stmt = $pdo->prepare(
            "INSERT INTO expenses (user_id, date, category, description, amount, payment_method, target_account) VALUES (?, ?, ?, ?, ?, ?, ?)"
        );
        $stmt->execute([$userId, $row['date'], $row['category'], $row['description'], $row['amount'], $row['payment_method'], $row['target_account'] ?: null]);
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'approve') {
        $id = (int)$_POST['id'];
        $row = [
            'txn_type' => $_POST['txn_type'] === 'income' ? 'income' : 'expense',
            'date' => $_POST['date'],
            'amount' => (float)$_POST['amount'],
            'category' => trim($_POST['category']) ?: 'Other',
            'description' => trim($_POST['description']),
            'payment_method' => trim($_POST['payment_method']) ?: 'Cash',
            'target_account' => trim($_POST['target_account'] ?? ''),
        ];

        $check = $pdo->prepare("SELECT id FROM ai_import_queue WHERE id = ? AND user_id = ? AND status = 'pending'");
        $check->execute([$id, $userId]);
        if ($check->fetch() && $row['amount'] > 0) {
            commitApprovedTransaction($pdo, $userId, $row);
            $upd = $pdo->prepare("UPDATE ai_import_queue SET status = 'approved', resolved_at = CURRENT_TIMESTAMP WHERE id = ? AND user_id = ?");
            $upd->execute([$id, $userId]);
            $_SESSION['flash_message'] = "Transaction approved and added.";
        }
    } elseif ($action === 'reject') {
        $id = (int)$_POST['id'];
        $stmt = $pdo->prepare("UPDATE ai_import_queue SET status = 'rejected', resolved_at = CURRENT_TIMESTAMP WHERE id = ? AND user_id = ? AND status = 'pending'");
        $stmt->execute([$id, $userId]);
        $_SESSION['flash_message'] = "Entry rejected.";
    } elseif ($action === 'approve_all') {
        $batchId = $_POST['batch_id'] ?? '';
        $stmt = $pdo->prepare("SELECT * FROM ai_import_queue WHERE user_id = ? AND batch_id = ? AND status = 'pending'");
        $stmt->execute([$userId, $batchId]);
        $rows = $stmt->fetchAll();

        if ($rows) {
            $pdo->beginTransaction();
            try {
                $upd = $pdo->prepare("UPDATE ai_import_queue SET status = 'approved', resolved_at = CURRENT_TIMESTAMP WHERE id = ?");
                foreach ($rows as $r) {
                    if ((float)$r['amount'] <= 0) continue;
                    commitApprovedTransaction($pdo, $userId, $r);
                    $upd->execute([$r['id']]);
                }
                $pdo->commit();
                $_SESSION['flash_message'] = count($rows) . " transactions approved and added.";
            } catch (Exception $e) {
                $pdo->rollBack();
                $_SESSION['flash_message'] = "Approve All failed: " . $e->getMessage();
            }
        }
    }

    $redirect = 'ai_review.php';
    if (!empty($_POST['batch_id'])) $redirect .= '?batch=' . urlencode($_POST['batch_id']);
    header("Location: " . $redirect);
    exit;
}

$batchFilter = $_GET['batch'] ?? '';
$showRejected = isset($_GET['rejected']);

if ($showRejected) {
    $stmt = $pdo->prepare("SELECT * FROM ai_import_queue WHERE user_id = ? AND status = 'rejected' ORDER BY resolved_at DESC LIMIT 100");
    $stmt->execute([$userId]);
} elseif ($batchFilter) {
    $stmt = $pdo->prepare("SELECT * FROM ai_import_queue WHERE user_id = ? AND batch_id = ? AND status = 'pending' ORDER BY id ASC");
    $stmt->execute([$userId, $batchFilter]);
} else {
    $stmt = $pdo->prepare("SELECT * FROM ai_import_queue WHERE user_id = ? AND status = 'pending' ORDER BY created_at DESC");
    $stmt->execute([$userId]);
}
$items = $stmt->fetchAll();

$categories = getExpenseCategories($userId);
$paymentMethods = ['Cash', 'UPI', 'Bank Transfer', 'Debit Card'];
$creditStmt = $pdo->prepare("SELECT provider_name FROM credit_accounts WHERE user_id = ?");
$creditStmt->execute([$userId]);
while ($row = $creditStmt->fetch()) {
    $paymentMethods[] = $row['provider_name'];
}

$sourceLabels = ['paste' => '📋 Pasted', 'upload' => '📄 Uploaded', 'sms' => '📱 SMS'];

require_once '../includes/header.php';
?>

<div class="max-w-4xl mx-auto space-y-6">

    <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
        <div>
            <h1 class="text-2xl font-bold text-gray-900 dark:text-white flex items-center gap-2">
                <span>📋</span> Review AI Import
            </h1>
            <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">
                <?php echo $showRejected ? 'Previously rejected entries.' : 'Check these before they land in your ledger.'; ?>
            </p>
        </div>
        <div class="flex gap-3">
            <a href="ai_import.php" class="text-sm font-bold text-brand-600 hover:text-brand-700">← Back to Import</a>
            <a href="ai_review.php<?php echo $showRejected ? '' : '?rejected=1'; ?>" class="text-sm font-bold text-gray-500 hover:text-gray-700">
                <?php echo $showRejected ? 'View Pending' : 'View Rejected'; ?>
            </a>
        </div>
    </div>

    <?php if ($batchFilter && !$showRejected && count($items) > 0): ?>
        <form method="POST">
            <input type="hidden" name="action" value="approve_all">
            <input type="hidden" name="batch_id" value="<?php echo htmlspecialchars($batchFilter); ?>">
            <button type="submit" class="w-full bg-emerald-600 hover:bg-emerald-700 text-white font-bold py-3 rounded-xl shadow-lg shadow-emerald-500/20 transition active:scale-95">
                ✅ Approve All (<?php echo count($items); ?>)
            </button>
        </form>
    <?php endif; ?>

    <?php if (empty($items)): ?>
        <div class="text-center py-16 text-gray-400 italic text-sm bg-white dark:bg-gray-800 rounded-2xl border border-gray-100 dark:border-gray-700">
            <?php echo $showRejected ? 'No rejected entries.' : 'Nothing pending review. 🎉'; ?>
        </div>
    <?php else: ?>
        <div class="space-y-4">
            <?php foreach ($items as $item): ?>
                <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-sm border border-gray-100 dark:border-gray-700 p-5">
                    <div class="flex items-center justify-between mb-3">
                        <span class="text-[10px] font-bold uppercase tracking-widest text-gray-400"><?php echo $sourceLabels[$item['source']] ?? $item['source']; ?></span>
                        <?php if ($item['confidence'] !== null): ?>
                            <span class="text-[10px] font-bold uppercase tracking-widest <?php echo $item['confidence'] >= 0.7 ? 'text-emerald-500' : 'text-amber-500'; ?>">
                                <?php echo round($item['confidence'] * 100); ?>% confident
                            </span>
                        <?php endif; ?>
                    </div>
                    <p class="text-xs text-gray-400 italic mb-4 truncate" title="<?php echo htmlspecialchars($item['raw_text']); ?>">"<?php echo htmlspecialchars(mb_substr($item['raw_text'], 0, 140)); ?>"</p>

                    <?php if ($showRejected): ?>
                        <div class="grid grid-cols-2 md:grid-cols-4 gap-3 text-sm">
                            <div><span class="text-gray-400 text-xs block">Type</span><?php echo ucfirst($item['txn_type']); ?></div>
                            <div><span class="text-gray-400 text-xs block">Date</span><?php echo htmlspecialchars($item['date']); ?></div>
                            <div><span class="text-gray-400 text-xs block">Amount</span>₹<?php echo number_format($item['amount'], 2); ?></div>
                            <div><span class="text-gray-400 text-xs block">Category</span><?php echo htmlspecialchars($item['category']); ?></div>
                        </div>
                    <?php else: ?>
                        <form method="POST" class="grid grid-cols-2 md:grid-cols-6 gap-3 items-end">
                            <input type="hidden" name="id" value="<?php echo $item['id']; ?>">
                            <input type="hidden" name="batch_id" value="<?php echo htmlspecialchars($item['batch_id']); ?>">

                            <div class="col-span-1">
                                <label class="block text-[10px] font-bold text-gray-400 uppercase mb-1">Type</label>
                                <select name="txn_type" class="w-full text-sm p-2 bg-gray-50 dark:bg-gray-900 border-none rounded-lg focus:ring-2 focus:ring-brand-500">
                                    <option value="expense" <?php echo $item['txn_type'] === 'expense' ? 'selected' : ''; ?>>Expense</option>
                                    <option value="income" <?php echo $item['txn_type'] === 'income' ? 'selected' : ''; ?>>Income</option>
                                </select>
                            </div>
                            <div class="col-span-1">
                                <label class="block text-[10px] font-bold text-gray-400 uppercase mb-1">Date</label>
                                <input type="date" name="date" value="<?php echo htmlspecialchars($item['date']); ?>" required class="w-full text-sm p-2 bg-gray-50 dark:bg-gray-900 border-none rounded-lg focus:ring-2 focus:ring-brand-500">
                            </div>
                            <div class="col-span-1">
                                <label class="block text-[10px] font-bold text-gray-400 uppercase mb-1">Amount</label>
                                <input type="number" step="0.01" name="amount" value="<?php echo htmlspecialchars($item['amount']); ?>" required class="w-full text-sm p-2 bg-gray-50 dark:bg-gray-900 border-none rounded-lg focus:ring-2 focus:ring-brand-500">
                            </div>
                            <div class="col-span-1">
                                <label class="block text-[10px] font-bold text-gray-400 uppercase mb-1">Category</label>
                                <input list="cat-list-<?php echo $item['id']; ?>" name="category" value="<?php echo htmlspecialchars($item['category']); ?>" class="w-full text-sm p-2 bg-gray-50 dark:bg-gray-900 border-none rounded-lg focus:ring-2 focus:ring-brand-500">
                                <datalist id="cat-list-<?php echo $item['id']; ?>">
                                    <?php foreach ($categories as $c): ?><option value="<?php echo htmlspecialchars($c); ?>"><?php endforeach; ?>
                                </datalist>
                            </div>
                            <div class="col-span-2 md:col-span-1">
                                <label class="block text-[10px] font-bold text-gray-400 uppercase mb-1">Method</label>
                                <select name="payment_method" class="w-full text-sm p-2 bg-gray-50 dark:bg-gray-900 border-none rounded-lg focus:ring-2 focus:ring-brand-500">
                                    <?php foreach ($paymentMethods as $pm): ?>
                                        <option value="<?php echo htmlspecialchars($pm); ?>" <?php echo strcasecmp($pm, $item['payment_method']) === 0 ? 'selected' : ''; ?>><?php echo htmlspecialchars($pm); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-span-2 md:col-span-6">
                                <label class="block text-[10px] font-bold text-gray-400 uppercase mb-1">Description</label>
                                <input type="text" name="description" value="<?php echo htmlspecialchars($item['description']); ?>" class="w-full text-sm p-2 bg-gray-50 dark:bg-gray-900 border-none rounded-lg focus:ring-2 focus:ring-brand-500">
                                <input type="hidden" name="target_account" value="<?php echo htmlspecialchars($item['target_account'] ?? ''); ?>">
                            </div>

                            <div class="col-span-2 md:col-span-6 flex gap-3 pt-1">
                                <button type="submit" name="action" value="approve" class="flex-1 bg-brand-600 hover:bg-brand-700 text-white font-bold py-2 rounded-lg text-sm transition active:scale-95">
                                    ✅ Approve
                                </button>
                                <button type="submit" name="action" value="reject" formnovalidate class="flex-1 bg-gray-100 dark:bg-gray-700 hover:bg-gray-200 text-gray-700 dark:text-gray-200 font-bold py-2 rounded-lg text-sm transition active:scale-95">
                                    ✕ Reject
                                </button>
                            </div>
                        </form>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

</div>

<?php require_once '../includes/footer.php'; ?>
