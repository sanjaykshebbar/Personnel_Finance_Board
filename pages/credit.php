<?php
require_once '../config/database.php';
require_once '../includes/auth.php';
requireLogin();

$userId = getCurrentUserId();

// Handle POST BEFORE any output
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['delete_id'])) {
        $stmt = $pdo->prepare("DELETE FROM credit_accounts WHERE id = ? AND user_id = ?");
        $stmt->execute([$_POST['delete_id'], $userId]);
        $_SESSION['flash_message'] = "Credit account deleted.";
    } elseif (isset($_POST['quick_log'])) {
        // Quick Transaction Logic
        $cardName = $_POST['card_name'];
        $amount = $_POST['amount'];
        $desc = $_POST['description'];
        $date = date('Y-m-d');
        
        $stmt = $pdo->prepare("INSERT INTO expenses (user_id, date, category, description, amount, payment_method) VALUES (?, ?, 'Credit Purchase', ?, ?, ?)");
        $stmt->execute([$userId, $date, $desc, $amount, $cardName]);
        $_SESSION['flash_message'] = "Transaction logged to $cardName.";
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
                $_SESSION['flash_message'] = "Error updating account."; 
            }
        } else {
            // INSERT new account
            $stmt = $pdo->prepare("INSERT INTO credit_accounts (user_id, provider_name, credit_limit, used_amount) VALUES (?, ?, ?, ?)");
            try { 
                $stmt->execute([$userId, $provider, $limit, $manual_used]); 
                $_SESSION['flash_message'] = "Credit account added!";
            } catch(Exception $e) { 
                $_SESSION['flash_message'] = "Error adding account."; 
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
        $txnStmt = $pdo->prepare("
            SELECT * FROM expenses 
            WHERE user_id = ? 
            AND (
                TRIM(LOWER(payment_method)) = TRIM(LOWER(?)) -- Spent using card
                OR 
                TRIM(LOWER(target_account)) = TRIM(LOWER(?)) -- Paid TO card (explicit link)
                OR
                (
                    category = 'Credit Card Bill' 
                    AND (description LIKE ? OR description LIKE ?)
                    AND (target_account IS NULL OR target_account = '')
                )
            )
            ORDER BY date DESC
        ");
        $txnStmt->execute([$userId, $cardName, $cardName, "%$cardName%", "%$firstWord%"]);
        $historyTxns = $txnStmt->fetchAll();
    }
}

// Fetch Credit Accounts with Advanced Usage Calculation BEFORE header
// FIXED: Added TRIM(LOWER(...)) to ensure case-insensitive matching
$stmt = $pdo->prepare("
    SELECT ca.*, 
    (SELECT IFNULL(SUM(amount), 0) FROM expenses WHERE TRIM(LOWER(payment_method)) = TRIM(LOWER(ca.provider_name)) AND converted_to_emi = 0 AND user_id = ca.user_id AND date >= '" . SYSTEM_START_DATE . "') as one_time_expenses,
    (SELECT IFNULL(SUM(amount), 0) FROM expenses WHERE category = 'Credit Card Bill' AND TRIM(LOWER(target_account)) = TRIM(LOWER(ca.provider_name)) AND user_id = ca.user_id) as bill_payments,
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
    <!-- Form -->
    <div class="bg-white dark:bg-gray-800 p-6 rounded-xl shadow border border-gray-100 dark:border-gray-700 transition-colors">
        <h3 class="font-bold text-lg mb-4 text-gray-900 dark:text-white"><?php echo $editRow?'Edit Account':'Add Credit Account'; ?></h3>
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
            // Total Used = Manual Base + Charged Expenses - Payments + EMI Outstanding
            $currentUsed = $acc['used_amount'] + $acc['one_time_expenses'] - ($acc['bill_payments'] ?? 0) + $acc['emi_outstanding'];
            $limit = $acc['credit_limit'];
            $remaining = $limit - $currentUsed;
            $percent = ($limit > 0) ? ($currentUsed / $limit) * 100 : 0;
            
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
                            data-used="<?php echo $currentUsed; ?>" 
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
                    <div class="text-[10px] font-bold text-gray-400 uppercase text-center mb-1">Spent</div>
                    <div class="text-sm font-bold text-gray-900 dark:text-white text-center">₹<?php echo number_format($currentUsed); ?></div>
                </div>
                <div>
                    <div class="text-[10px] font-bold text-gray-400 uppercase text-center mb-1">Available</div>
                    <div class="text-sm font-bold text-emerald-600 dark:text-emerald-400 text-center">₹<?php echo number_format($remaining); ?></div>
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
            
            <div>
                <label class="block text-[10px] font-bold text-gray-400 uppercase mb-1">Amount (₹)</label>
                <input type="number" name="amount" required step="0.01" autofocus
                       class="w-full border-gray-100 dark:border-gray-700 bg-gray-50 dark:bg-gray-900 dark:text-white p-4 rounded-xl text-xl font-black outline-none focus:ring-2 focus:ring-brand-500 transition-all">
            </div>
            
            <div>
                <label class="block text-[10px] font-bold text-gray-400 uppercase mb-1">Description</label>
                <input type="text" name="description" placeholder="Starbucks, Gas, etc." required
                       class="w-full border-gray-100 dark:border-gray-700 bg-gray-50 dark:bg-gray-900 dark:text-white p-3 rounded-xl text-sm outline-none focus:ring-2 focus:ring-brand-500">
            </div>

            <button type="submit" class="w-full bg-gray-900 dark:bg-brand-600 text-white py-4 rounded-xl font-black text-sm hover:scale-[1.02] active:scale-[0.98] transition-all shadow-xl shadow-gray-900/10">
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
                                <div class="font-bold"><?php echo htmlspecialchars($txn['description']); ?></div>
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
    document.getElementById('quickLogModal').classList.remove('hidden');
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
