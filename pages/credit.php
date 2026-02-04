<?php
require_once '../config/database.php';
require_once '../includes/auth.php';
requireLogin();

$userId = getCurrentUserId();

/**
 * AUTO-LINKER: Scan for orphaned "Credit Card Bill" entries and link them by keyword
 */
function autoLinkCreditCardBills($pdo, $userId) {
    // 1. Get all active card names for this user
    $cardStmt = $pdo->prepare("SELECT provider_name FROM credit_accounts WHERE user_id = ?");
    $cardStmt->execute([$userId]);
    $cards = $cardStmt->fetchAll(PDO::FETCH_COLUMN);
    
    if (empty($cards)) return;

    // 2. Find unlinked bill payments
    $orphanStmt = $pdo->prepare("SELECT id, description FROM expenses WHERE user_id = ? AND category = 'Credit Card Bill' AND (target_account IS NULL OR target_account = '')");
    $orphanStmt->execute([$userId]);
    $orphans = $orphanStmt->fetchAll();

    foreach ($orphans as $orphan) {
        foreach ($cards as $cardName) {
            $firstWord = explode(' ', trim($cardName))[0];
            // Match card name or first word (e.g. "Axis") in description
            if (stripos($orphan['description'], $cardName) !== false || stripos($orphan['description'], $firstWord) !== false) {
                $linkStmt = $pdo->prepare("UPDATE expenses SET target_account = ? WHERE id = ?");
                $linkStmt->execute([$cardName, $orphan['id']]);
                break; // Linked to first match
            }
        }
    }
}
autoLinkCreditCardBills($pdo, $userId);

// Handle POST BEFORE any output
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['delete_id'])) {
        $stmt = $pdo->prepare("DELETE FROM credit_accounts WHERE id = ? AND user_id = ?");
        $stmt->execute([$_POST['delete_id'], $userId]);
        $_SESSION['flash_message'] = "Credit account deleted.";
    } elseif (isset($_POST['link_orphan'])) {
        // Link an unlinked expense to a card
        $expenseId = $_POST['expense_id'];
        $cardName = $_POST['card_name'];
        $stmt = $pdo->prepare("UPDATE expenses SET target_account = ? WHERE id = ? AND user_id = ?");
        $stmt->execute([$cardName, $expenseId, $userId]);
        $_SESSION['flash_message'] = "Transaction linked to $cardName!";
    } elseif (isset($_POST['quick_log'])) {
        // Quick Transaction / Payment / Adjustment Logic
        $cardName = $_POST['card_name'];
        $amount = $_POST['amount'];
        $desc = $_POST['description'];
        $type = $_POST['type'] ?? 'Expense'; // 'Expense', 'Payment', 'Adjustment'
        $date = date('Y-m-d');
        
        if ($type === 'Adjustment') {
            // Adjust the manual base used_amount directly
            $stmt = $pdo->prepare("UPDATE credit_accounts SET used_amount = used_amount + ? WHERE provider_name = ? AND user_id = ?");
            $stmt->execute([$amount, $cardName, $userId]);
            $_SESSION['flash_message'] = "Credit limit adjusted by ₹$amount for $cardName.";
        } elseif ($type === 'Payment') {
            $stmt = $pdo->prepare("INSERT INTO expenses (user_id, date, category, description, amount, payment_method, target_account) VALUES (?, ?, 'Credit Card Bill', ?, ?, 'Bank Account', ?)");
            $stmt->execute([$userId, $date, $desc, $amount, $cardName]);
            $_SESSION['flash_message'] = "Bill payment of ₹$amount logged to $cardName.";
        } else {
            $stmt = $pdo->prepare("INSERT INTO expenses (user_id, date, category, description, amount, payment_method) VALUES (?, ?, 'Credit Purchase', ?, ?, ?)");
            $stmt->execute([$userId, $date, $desc, $amount, $cardName]);
            $_SESSION['flash_message'] = "Expense of ₹$amount logged to $cardName.";
        }
    } elseif (isset($_POST['auto_settle'])) {
        $cardId = $_POST['card_id'];
        $balance = $_POST['balance']; // The negative balance to absorb
        $stmt = $pdo->prepare("UPDATE credit_accounts SET used_amount = used_amount + ? WHERE id = ? AND user_id = ?");
        $stmt->execute([$balance, $cardId, $userId]);
        $_SESSION['flash_message'] = "Balance settled. Absorbed " . number_format($balance, 2) . " into base.";
    } else {
        $provider = $_POST['provider_name'];
        $limit = $_POST['credit_limit'];
        $manual_used = $_POST['used_amount'] ?? 0;
        
        if (!empty($_POST['id'])) {
            // Fetch old provider name for sync
            $oldStmt = $pdo->prepare("SELECT provider_name FROM credit_accounts WHERE id = ? AND user_id = ?");
            $oldStmt->execute([$_POST['id'], $userId]);
            $oldProvider = $oldStmt->fetchColumn();

            // UPDATE existing account
            $stmt = $pdo->prepare("UPDATE credit_accounts SET provider_name=?, credit_limit=?, used_amount=? WHERE id=? AND user_id = ?");
            try {
                $pdo->beginTransaction();
                $stmt->execute([$provider, $limit, $manual_used, $_POST['id'], $userId]);
                
                // SYNC: Update expenses and EMIs if provider name changed
                if ($oldProvider && $oldProvider !== $provider) {
                    $pdo->prepare("UPDATE expenses SET payment_method = ? WHERE payment_method = ? AND user_id = ?")->execute([$provider, $oldProvider, $userId]);
                    $pdo->prepare("UPDATE expenses SET target_account = ? WHERE target_account = ? AND user_id = ?")->execute([$provider, $oldProvider, $userId]);
                    $pdo->prepare("UPDATE emis SET payment_method = ? WHERE payment_method = ? AND user_id = ?")->execute([$provider, $oldProvider, $userId]);
                }
                
                $pdo->commit();
                $_SESSION['flash_message'] = "Account details updated & synced.";
            } catch(Exception $e) { 
                $pdo->rollBack();
                $_SESSION['flash_message'] = "Error updating account: " . $e->getMessage(); 
            }
        } else {
            // INSERT new account
            $stmt = $pdo->prepare("INSERT INTO credit_accounts (user_id, provider_name, credit_limit, used_amount) VALUES (?, ?, ?, ?)");
            try { 
                $stmt->execute([$userId, $provider, $limit, $manual_used]); 
                $_SESSION['flash_message'] = "Credit account added!";
            } catch(Exception $e) { 
                $_SESSION['flash_message'] = "Error adding account: " . $e->getMessage(); 
            }
        }
    }
    header("Location: credit.php");
    exit;
}

$editRow = null;
if (isset($_GET['edit'])) {
    $stmt = $pdo->prepare("SELECT * FROM credit_accounts WHERE id = ? AND user_id = ?");
    $stmt->execute([$_GET['edit'], $userId]);
    $editRow = $stmt->fetch();
}

// History View Logic
$historyCard = null;
$historyTxns = [];
if (isset($_GET['history_view'])) {
    $stmt = $pdo->prepare("SELECT * FROM credit_accounts WHERE id = ? AND user_id = ?");
    $stmt->execute([$_GET['history_view'], $userId]);
    $historyCard = $stmt->fetch();

    if ($historyCard) {
        $cardName = $historyCard['provider_name'];
        $firstWord = explode(' ', trim($cardName))[0];
        
        // EXPLICIT History Search (Using target_account and payment_method)
        // Broadened to include unlinked 'Credit Card Bill' entries as a fallback
        $txnStmt = $pdo->prepare("
            SELECT *, 
            CASE 
                WHEN category = 'Credit Card Bill' AND (target_account IS NULL OR target_account = '') AND (description NOT LIKE ? AND description NOT LIKE ?) THEN 1
                ELSE 0
            END as is_unlinked_fallback
            FROM expenses 
            WHERE user_id = ? 
            AND (
                TRIM(LOWER(payment_method)) = TRIM(LOWER(?)) -- Spent using card
                OR 
                TRIM(LOWER(target_account)) = TRIM(LOWER(?)) -- Paid TO card (explicit link)
                OR
                (
                    category = 'Credit Card Bill' 
                    AND (description LIKE ? OR description LIKE ? OR (target_account IS NULL OR target_account = ''))
                )
            )
            ORDER BY date DESC
        ");
        $txnStmt->execute(["%$cardName%", "%$firstWord%", $userId, $cardName, $cardName, "%$cardName%", "%$firstWord%"]);
        $historyTxns = $txnStmt->fetchAll();
    }
}

// Fetch Credit Accounts with Advanced Usage Calculation BEFORE header
// FIXED: Added TRIM(LOWER(...)) to ensure case-insensitive matching
$stmt = $pdo->prepare("
    SELECT ca.*, 
    (SELECT IFNULL(SUM(amount), 0) FROM expenses WHERE TRIM(LOWER(payment_method)) = TRIM(LOWER(ca.provider_name)) AND converted_to_emi = 0 AND user_id = ca.user_id AND date >= '\" . SYSTEM_START_DATE . \"') as one_time_expenses,
    (SELECT IFNULL(SUM(amount), 0) FROM expenses WHERE category = 'Credit Card Bill' AND TRIM(LOWER(target_account)) = TRIM(LOWER(ca.provider_name)) AND user_id = ca.user_id AND date >= '\" . SYSTEM_START_DATE . \"') as bill_payments,
    (SELECT IFNULL(SUM(total_amount - (emi_amount * paid_months)), 0) FROM emis WHERE TRIM(LOWER(payment_method)) = TRIM(LOWER(ca.provider_name)) AND user_id = ca.user_id AND status = 'Active') as emi_outstanding
    FROM credit_accounts ca 
    WHERE ca.user_id = ?
");
$stmt->execute([$userId]);
$accounts = $stmt->fetchAll();

// NOW load header (which outputs HTML)
$pageTitle = 'Credit Usage';
require_once '../includes/header.php';
?>

<div class="space-y-6">
    <!-- Header & Form -->
    <div class="bg-white dark:bg-gray-800 p-6 rounded-xl shadow border border-gray-100 dark:border-gray-700 transition-colors">
        <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 mb-6">
            <div>
                <h3 class="font-bold text-lg text-gray-900 dark:text-white"><?php echo $editRow?'Edit Account':'Add Credit Account'; ?></h3>
                <p class="text-xs text-gray-400">Manage your cards and limits.</p>
            </div>
            <div class="px-3 py-1.5 bg-brand-50 dark:bg-brand-900/30 border border-brand-100 dark:border-brand-800 rounded-lg">
                <p class="text-[10px] font-black text-brand-600 dark:text-brand-400 uppercase tracking-widest">Active Accounting Start: <?php echo date('d M Y', strtotime(SYSTEM_START_DATE)); ?></p>
            </div>
        </div>
        
        <form method="POST" action="credit.php" class="grid grid-cols-1 md:grid-cols-4 gap-4 items-end">
            <input type="hidden" name="id" value="<?php echo $editRow['id']??''; ?>">
            <div>
                <label class="block text-[10px] font-bold text-gray-400 uppercase mb-1">Provider / Card Name</label>
                <input type="text" name="provider_name" placeholder="Axis Ace, OneCard..." required 
                       value="<?php echo $editRow['provider_name']??''; ?>" class="w-full border-gray-200 dark:border-gray-600 dark:bg-gray-900 dark:text-white p-2 rounded text-sm focus:ring-brand-500">
            </div>
            <div>
                <label class="block text-[10px] font-bold text-gray-400 uppercase mb-1">Credit Limit (₹)</label>
                <input type="number" step="0.01" name="credit_limit" required 
                       value="<?php echo $editRow['credit_limit']??''; ?>" class="w-full border-gray-200 dark:border-gray-600 dark:bg-gray-900 dark:text-white p-2 rounded text-sm focus:ring-brand-500">
            </div>
            <div>
                <label class="block text-[10px] font-bold text-gray-400 uppercase mb-1">
                    Manual Base used (₹)
                    <span class="text-[8px] text-gray-400 normal-case">· Adjust starting debt</span>
                </label>
                <input type="number" step="0.01" name="used_amount" 
                       value="<?php echo $editRow['used_amount']??0; ?>" 
                       class="w-full border-gray-200 dark:border-gray-600 dark:bg-gray-900 dark:text-white p-2 rounded text-sm focus:ring-brand-500">
            </div>
            <div>
                <button class="bg-brand-600 text-white w-full py-2.5 rounded-lg font-bold hover:bg-brand-700 transition shadow-md">
                    <?php echo $editRow?'Update Account':'Save Card'; ?>
                </button>
            </div>
        </form>
    </div>

    <!-- Cards Grid -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
        <?php foreach ($accounts as $acc): 
            // FIXED: Separation of Balance and Total Debt for clarity
            // 1. Card Balance = Previous Manual Base + New Expenses - Bill Payments
            $cardBalance = $acc['used_amount'] + $acc['one_time_expenses'] - ($acc['bill_payments'] ?? 0);
            
            // 2. Total Utilized/Liability = Card Balance + Future EMI Principal
            $totalLiability = $cardBalance + $acc['emi_outstanding'];
            
            $limit = $acc['credit_limit'];
            $remaining = $limit - $totalLiability;
            $percent = ($limit > 0) ? ($totalLiability / $limit) * 100 : 0;
            
            // UI Color Logic
            $statusColor = $percent > 85 ? 'text-red-600 dark:text-red-400' : ($percent > 60 ? 'text-amber-600 dark:text-amber-400' : 'text-emerald-600 dark:text-emerald-400');
            $chartColor = $percent > 85 ? '#ef4444' : ($percent > 60 ? '#f59e0b' : '#10b981');
        ?>
        <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-sm border border-gray-100 dark:border-gray-700 p-6 flex flex-col transition-all hover:shadow-md">
            <div class="flex justify-between items-start mb-6">
                <div>
                    <h3 class="text-lg font-bold text-gray-900 dark:text-white"><?php echo htmlspecialchars($acc['provider_name']); ?></h3>
                    <p class="text-[10px] text-gray-400 font-bold uppercase tracking-wider">Credit card Profile</p>
                </div>
                <div class="flex space-x-2">
                    <button onclick="openQuickLog('<?php echo htmlspecialchars($acc['provider_name']); ?>')" class="p-1.5 bg-brand-50 dark:bg-brand-900/30 text-brand-600 rounded-md hover:bg-brand-100 transition text-[9px] font-black uppercase" title="Log Expense">+ Log</button>
                    <a href="?history_view=<?php echo $acc['id']; ?>" class="p-1.5 bg-blue-50 dark:bg-blue-900/30 text-blue-600 rounded-md hover:bg-blue-100 transition text-[9px] font-black uppercase" title="View History">📜</a>
                    <a href="?edit=<?php echo $acc['id']; ?>" class="p-1.5 bg-gray-50 dark:bg-gray-900 rounded-md hover:bg-brand-50 dark:hover:bg-brand-900/30 transition">✏️</a>
                    <form method="POST" onsubmit="return confirm('Delete card?')" class="inline">
                        <input type="hidden" name="delete_id" value="<?php echo $acc['id']; ?>">
                        <button class="p-1.5 bg-gray-50 dark:bg-gray-900 rounded-md hover:bg-red-50 dark:hover:bg-red-900/30 transition">🗑️</button>
                    </form>
                </div>
            </div>

            <div class="flex items-center space-x-6 mb-6">
                <!-- Pie Chart Container -->
                <div class="w-20 h-20 flex-shrink-0">
                    <canvas id="credit-chart-<?php echo $acc['id']; ?>" class="credit-chart" 
                            data-used="<?php echo $totalLiability; ?>" 
                            data-rem="<?php echo max(0, $remaining); ?>"
                            data-color="<?php echo $chartColor; ?>"></canvas>
                </div>
                
                <div class="flex-grow">
                    <div class="text-[10px] font-bold text-gray-400 uppercase mb-1">Utilization</div>
                    <div class="text-2xl font-black <?php echo $statusColor; ?>">
                        <?php echo round($percent, 1); ?>%
                    </div>
                    <div class="text-xs text-gray-500 dark:text-gray-400 mt-1">
                        Limit: ₹<?php echo number_format($limit); ?>
                    </div>
                </div>
            </div>

            <div class="grid grid-cols-2 gap-4 pt-4 border-t border-gray-100 dark:border-gray-700">
                <div>
                    <div class="text-[10px] font-bold text-gray-400 uppercase text-center mb-1">Card Balance</div>
                    <div class="text-sm font-bold text-gray-900 dark:text-white text-center">₹<?php echo number_format($cardBalance, 2); ?></div>
                    <p class="text-[8px] text-gray-400 text-center leading-tight">Exp - Paid</p>
                    <?php if($cardBalance < 0): ?>
                        <form method="POST" class="mt-2 text-center">
                            <input type="hidden" name="auto_settle" value="1">
                            <input type="hidden" name="card_id" value="<?php echo $acc['id']; ?>">
                            <input type="hidden" name="balance" value="<?php echo $cardBalance; ?>">
                            <button type="submit" class="text-[8px] font-black text-brand-600 hover:text-brand-700 underline uppercase tracking-tighter" title="Absorb this negative balance into the card's Initial Base (used amount).">Settle & Zero Out</button>
                        </form>
                    <?php endif; ?>
                </div>
                <div>
                    <div class="text-[10px] font-bold text-gray-400 uppercase text-center mb-1">Total Utilized</div>
                    <div class="text-sm font-bold text-brand-600 dark:text-brand-400 text-center">₹<?php echo number_format($totalLiability, 2); ?></div>
                    <p class="text-[8px] text-gray-400 text-center leading-tight">Incl. EMI Principal</p>
                </div>
            </div>
            
            <!-- Breakdown info -->
            <div class="mt-4 pt-2 flex justify-between text-[8px] font-bold text-gray-300 dark:text-gray-600 uppercase">
                <span>Initial: ₹<?php echo number_format($acc['used_amount']); ?></span>
                <span>Exp: ₹<?php echo number_format($acc['one_time_expenses']); ?></span>
                <span>Paid: ₹<?php echo number_format($acc['bill_payments']); ?></span>
                <span>EMI: ₹<?php echo number_format($acc['emi_outstanding']); ?></span>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
</div>

<!-- Quick Log Modal -->
<div id="quickLogModal" class="fixed inset-0 bg-black/50 backdrop-blur-sm z-50 hidden flex items-center justify-center p-4">
    <div class="bg-white dark:bg-gray-800 rounded-2xl w-full max-w-sm overflow-hidden shadow-2xl">
        <div class="p-6 border-b border-gray-100 dark:border-gray-700 flex justify-between items-center">
            <div>
                <h3 class="font-black text-gray-900 dark:text-white">Quick Transaction</h3>
                <p id="quickLogCardName" class="text-[10px] text-brand-600 font-bold uppercase"></p>
            </div>
            <button onclick="closeQuickLog()" class="text-gray-400 hover:text-gray-600">✕</button>
        </div>
        <form method="POST" class="p-6 space-y-4">
            <input type="hidden" name="quick_log" value="1">
            <input type="hidden" name="card_name" id="modalCardName">
            
            <div class="flex p-1 bg-gray-100 dark:bg-gray-900 rounded-xl space-x-1">
                <button type="button" onclick="setQuickLogType('Expense')" id="btnTypeExpense" 
                        class="flex-1 py-1 text-[10px] font-bold uppercase rounded-lg transition-all bg-white dark:bg-brand-600 text-brand-600 dark:text-white shadow-sm">
                    Expense
                </button>
                <button type="button" onclick="setQuickLogType('Payment')" id="btnTypePayment" 
                        class="flex-1 py-1 text-[10px] font-bold uppercase rounded-lg transition-all text-gray-500 hover:text-gray-700">
                    Payment
                </button>
                <button type="button" onclick="setQuickLogType('Adjustment')" id="btnTypeAdjustment" 
                        class="flex-1 py-1 text-[10px] font-bold uppercase rounded-lg transition-all text-gray-500 hover:text-gray-700" title="Add to Opening Balance / Manual Base">
                    Adj
                </button>
                <input type="hidden" name="type" id="modalLogType" value="Expense">
            </div>

            <div>
                <label class="block text-[10px] font-bold text-gray-400 uppercase mb-1">Amount (₹)</label>
                <input type="number" name="amount" required step="0.01" autofocus id="modalAmount"
                       class="w-full border-gray-100 dark:border-gray-700 bg-gray-50 dark:bg-gray-900 dark:text-white p-4 rounded-xl text-xl font-black outline-none focus:ring-2 focus:ring-brand-500 transition-all">
            </div>
            
            <div>
                <label class="block text-[10px] font-bold text-gray-400 uppercase mb-1">Description / Memo</label>
                <input type="text" name="description" id="modalDesc" placeholder="Lunch, Fuel, Amazon..."
                       class="w-full border-gray-100 dark:border-gray-700 bg-gray-50 dark:bg-gray-900 dark:text-white p-3 rounded-xl text-sm outline-none focus:ring-2 focus:ring-brand-500">
                <div id="modalNote" class="mt-2 p-2 bg-amber-50 dark:bg-amber-900/20 text-amber-600 dark:text-amber-400 text-[9px] font-bold rounded-lg border border-amber-200 dark:border-amber-800 hidden"></div>
            </div>

            <button type="submit" id="modalSubmitBtn" class="w-full bg-gray-900 dark:bg-brand-600 text-white py-4 rounded-xl font-black text-sm hover:scale-[1.02] active:scale-[0.98] transition-all shadow-xl shadow-gray-900/10">
                Log Purchase →
            </button>
        </form>
    </div>
</div>

<!-- History Modal -->
<?php if ($historyCard): ?>
<div id="historyModal" class="fixed inset-0 bg-black/50 backdrop-blur-sm z-50 flex items-center justify-center p-4">
    <div class="bg-white dark:bg-gray-800 rounded-2xl w-full max-w-2xl overflow-hidden shadow-2xl h-[80vh] flex flex-col transition-colors">
        <div class="p-6 border-b border-gray-100 dark:border-gray-700 flex justify-between items-center">
            <div>
                <h3 class="font-black text-lg text-gray-900 dark:text-white"><?php echo htmlspecialchars($historyCard['provider_name']); ?> History</h3>
                <p class="text-[10px] text-gray-500 font-bold uppercase">Transactions & Payments</p>
            </div>
            <a href="credit.php" class="text-gray-400 hover:text-gray-600 text-xl font-bold">✕</a>
        </div>
        
        <div class="flex-grow overflow-y-auto p-0">
            <?php if (empty($historyTxns)): ?>
                <div class="flex flex-col items-center justify-center h-full text-gray-400">
                    <p class="text-4xl mb-2">📜</p>
                    <p class="text-sm font-bold">No transactions found.</p>
                </div>
            <?php else: ?>
                <table class="min-w-full divide-y divide-gray-100 dark:divide-gray-700">
                    <thead class="bg-gray-50 dark:bg-gray-900 sticky top-0">
                        <tr>
                            <th class="px-6 py-3 text-left text-[10px] font-bold text-gray-500 uppercase tracking-wider">Date</th>
                            <th class="px-6 py-3 text-left text-[10px] font-bold text-gray-500 uppercase tracking-wider">Description</th>
                            <th class="px-6 py-3 text-right text-[10px] font-bold text-gray-500 uppercase tracking-wider">Amount</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-50 dark:divide-gray-700">
                        <?php foreach ($historyTxns as $txn): 
                            $isBillPayment = ($txn['category'] === 'Credit Card Bill' || trim(strtolower($txn['target_account'] ?? '')) === trim(strtolower($historyCard['provider_name'])));
                        ?>
                        <tr class="hover:bg-gray-50 dark:hover:bg-gray-700/50 transition-colors">
                            <td class="px-6 py-4 whitespace-nowrap text-xs text-gray-500 font-medium">
                                <?php echo date('d M Y', strtotime($txn['date'])); ?>
                            </td>
                            <td class="px-6 py-4 text-sm text-gray-900 dark:text-white">
                                <div class="font-bold">
                                    <?php echo htmlspecialchars($txn['description']); ?>
                                    <?php if(!empty($txn['is_unlinked_fallback'])): ?>
                                        <span class="ml-2 px-1.5 py-0.5 bg-amber-100 dark:bg-amber-900/30 text-amber-600 dark:text-amber-400 text-[8px] font-black uppercase rounded border border-amber-200 dark:border-amber-800" title="This bill payment is not explicitly linked to this card but is being shown as a fallback.">Unlinked</span>
                                        <form method="POST" class="inline ml-1" onsubmit="return confirm('Link this payment to <?php echo htmlspecialchars($historyCard['provider_name']); ?>?');">
                                            <input type="hidden" name="link_orphan" value="1">
                                            <input type="hidden" name="expense_id" value="<?php echo $txn['id']; ?>">
                                            <input type="hidden" name="card_name" value="<?php echo htmlspecialchars($historyCard['provider_name']); ?>">
                                            <input type="hidden" name="return_id" value="<?php echo $historyCard['id']; ?>">
                                            <button type="submit" class="text-[9px] font-black text-brand-600 hover:text-brand-700 underline uppercase tracking-tighter">Link Now</button>
                                        </form>
                                    <?php elseif($txn['category'] === 'Credit Card Bill'): ?>
                                        <span class="ml-2 px-1.5 py-0.5 bg-brand-100 dark:bg-brand-900/30 text-brand-600 dark:text-brand-400 text-[8px] font-black uppercase rounded border border-brand-200 dark:border-brand-800" title="This payment is automatically linked! Perfect.">Auto-linked ✅</span>
                                    <?php endif; ?>
                                </div>
                                <div class="text-[10px] text-gray-400"><?php echo htmlspecialchars($txn['category']); ?></div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-right">
                                <?php if ($isBillPayment): ?>
                                    <span class="text-emerald-600 font-bold text-sm bg-emerald-50 dark:bg-emerald-900/20 px-2 py-1 rounded inline-flex items-center">
                                        ↓ ₹<?php echo number_format($txn['amount'], 2); ?>
                                    </span>
                                <?php else: ?>
                                    <span class="text-gray-900 dark:text-white font-bold text-sm">
                                        ₹<?php echo number_format($txn['amount'], 2); ?>
                                    </span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
        
        <div class="p-4 border-t border-gray-100 dark:border-gray-700 bg-gray-50 dark:bg-gray-900 text-center">
            <span class="text-[10px] text-gray-400 font-bold uppercase">Note: History links are now explicit via 'Target Account'.</span>
        </div>
    </div>
</div>
<?php endif; ?>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
function openQuickLog(card) {
    document.getElementById('quickLogCardName').innerText = card;
    document.getElementById('modalCardName').value = card;
    setQuickLogType('Expense'); // Reset to default
    document.getElementById('quickLogModal').classList.remove('hidden');
}
function setQuickLogType(type) {
    const btnExp = document.getElementById('btnTypeExpense');
    const btnPay = document.getElementById('btnTypePayment');
    const btnAdj = document.getElementById('btnTypeAdjustment');
    const input = document.getElementById('modalLogType');
    const submitBtn = document.getElementById('modalSubmitBtn');
    const descInput = document.getElementById('modalDesc');
    const cardName = document.getElementById('modalCardName').value;
    const note = document.getElementById('modalNote');

    input.value = type;
    note.classList.add('hidden');

    if (type === 'Adjustment') {
        btnAdj.className = "flex-1 py-2 text-[10px] font-bold uppercase rounded-lg transition-all bg-white dark:bg-amber-600 text-amber-600 dark:text-white shadow-sm";
        btnExp.className = "flex-1 py-2 text-[10px] font-bold uppercase rounded-lg transition-all text-gray-500 hover:text-gray-700";
        btnPay.className = "flex-1 py-2 text-[10px] font-bold uppercase rounded-lg transition-all text-gray-500 hover:text-gray-700";
        submitBtn.innerText = "Apply Adjustment →";
        submitBtn.className = submitBtn.className.replace('bg-brand-600', 'bg-amber-600').replace('bg-emerald-600', 'bg-amber-600');
        descInput.value = "Tally Adjustment / Opening Balance";
        note.classList.remove('hidden');
        note.innerText = "Adjustment updates the card's base limit usage without affecting your dashboard expenses.";
    } else if (type === 'Payment') {
        btnPay.className = "flex-1 py-2 text-[10px] font-bold uppercase rounded-lg transition-all bg-white dark:bg-emerald-600 text-emerald-600 dark:text-white shadow-sm";
        btnExp.className = "flex-1 py-2 text-[10px] font-bold uppercase rounded-lg transition-all text-gray-500 hover:text-gray-700";
        btnAdj.className = "flex-1 py-2 text-[10px] font-bold uppercase rounded-lg transition-all text-gray-500 hover:text-gray-700";
        submitBtn.innerText = "Log Payment →";
        submitBtn.className = submitBtn.className.replace('bg-brand-600', 'bg-emerald-600').replace('bg-amber-600', 'bg-emerald-600');
        descInput.value = "Bill Payment: " + cardName;
    } else {
        btnExp.className = "flex-1 py-2 text-[10px] font-bold uppercase rounded-lg transition-all bg-white dark:bg-brand-600 text-brand-600 dark:text-white shadow-sm";
        btnPay.className = "flex-1 py-2 text-[10px] font-bold uppercase rounded-lg transition-all text-gray-500 hover:text-gray-700";
        btnAdj.className = "flex-1 py-2 text-[10px] font-bold uppercase rounded-lg transition-all text-gray-500 hover:text-gray-700";
        submitBtn.innerText = "Log Purchase →";
        submitBtn.className = submitBtn.className.replace('bg-emerald-600', 'bg-brand-600').replace('bg-amber-600', 'bg-brand-600');
        descInput.value = "";
    }
}
function closeQuickLog() {
    document.getElementById('quickLogModal').classList.add('hidden');
}

document.querySelectorAll('.credit-chart').forEach(canvas => {
    const used = parseFloat(canvas.dataset.used);
    const rem = parseFloat(canvas.dataset.rem);
    const color = canvas.dataset.color;
    
    new Chart(canvas, {
        type: 'doughnut',
        data: {
            datasets: [{
                data: [used, rem],
                backgroundColor: [color, document.documentElement.classList.contains('dark') ? '#374151' : '#f3f4f6'],
                borderWidth: 0,
                hoverOffset: 0
            }]
        },
        options: {
            cutout: '75%',
            responsive: true,
            maintainAspectRatio: true,
            plugins: { tooltip: { enabled: false }, legend: { display: false } }
        }
    });
});
</script>
<?php require_once '../includes/footer.php'; ?>
