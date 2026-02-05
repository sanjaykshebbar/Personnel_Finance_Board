<?php
require_once '../config/database.php';
require_once '../includes/auth.php';
requireLogin();

$userId = getCurrentUserId();
$pageTitle = 'Quick Update';

// Handle Actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action']) && $_POST['action'] === 'add') {
        // ADD NEW ENTRY
        $amount = $_POST['amount'];
        $desc = $_POST['description'];
        $method = $_POST['payment_method'];
        $date = $_POST['date'];
        
        $stmt = $pdo->prepare("INSERT INTO quick_entries (user_id, amount, description, payment_method, date) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute([$userId, $amount, $desc, $method, $date]);
        $_SESSION['flash_message'] = "Draft entry added.";
        
    } elseif (isset($_POST['action']) && $_POST['action'] === 'sync_all') {
        // SYNC ALL ENTRIES
        $stmt = $pdo->prepare("SELECT * FROM quick_entries WHERE user_id = ?");
        $stmt->execute([$userId]);
        $entries = $stmt->fetchAll();
        
        if ($entries) {
            $pdo->beginTransaction();
            try {
                $insStmt = $pdo->prepare("INSERT INTO expenses (user_id, date, category, description, amount, payment_method) VALUES (?, ?, 'Other', ?, ?, ?)");
                $delStmt = $pdo->prepare("DELETE FROM quick_entries WHERE id = ?");
                
                foreach ($entries as $e) {
                    // Defaulting category to 'Other' for quick add, user can change later if needed or we can add category dropdown
                    $insStmt->execute([$userId, $e['date'], $e['description'], $e['amount'], $e['payment_method']]);
                    $delStmt->execute([$e['id']]);
                }
                
                $pdo->commit();
                $_SESSION['flash_message'] = count($entries) . " entries synced successfully! 🚀";
            } catch (Exception $e) {
                $pdo->rollBack();
                $_SESSION['flash_message'] = "Sync failed: " . $e->getMessage();
            }
        } else {
            $_SESSION['flash_message'] = "No entries to sync.";
        }
    } elseif (isset($_POST['delete_id'])) {
        // DELETE PENDING ENTRY
        $stmt = $pdo->prepare("DELETE FROM quick_entries WHERE id = ? AND user_id = ?");
        $stmt->execute([$_POST['delete_id'], $userId]);
        $_SESSION['flash_message'] = "Draft deleted.";
    }
    
    header("Location: quick_add.php");
    exit;
}

require_once '../includes/header.php';

// Fetch Pending Entries
$stmt = $pdo->prepare("SELECT * FROM quick_entries WHERE user_id = ? ORDER BY date DESC, id DESC");
$stmt->execute([$userId]);
$pendingEntries = $stmt->fetchAll();

// Fetch Payment Methods
$paymentMethods = ['UPI', 'Cash', 'Bank Account'];
$creditStmt = $pdo->prepare("SELECT provider_name FROM credit_accounts WHERE user_id = ?");
$creditStmt->execute([$userId]);
while ($row = $creditStmt->fetch()) {
    $paymentMethods[] = $row['provider_name'];
}
?>

<div class="max-w-md mx-auto space-y-6">
    
    <!-- Quick Add Form -->
    <div class="bg-white dark:bg-gray-800 p-6 rounded-2xl shadow-lg border border-gray-100 dark:border-gray-700">
        <h2 class="text-xl font-bold text-gray-800 dark:text-white mb-4 flex items-center gap-2">
            <span>⚡</span> Quick Update
        </h2>
        
        <form method="POST" class="space-y-4">
            <input type="hidden" name="action" value="add">
            
            <div>
                <label class="block text-xs font-bold text-gray-500 uppercase mb-1">Amount</label>
                <div class="relative">
                    <span class="absolute left-3 top-3 text-gray-400 font-bold">₹</span>
                    <input type="number" step="0.01" name="amount" required autofocus 
                           class="w-full pl-8 p-3 bg-gray-50 dark:bg-gray-900 border-none rounded-xl text-lg font-black focus:ring-2 focus:ring-brand-500">
                </div>
            </div>
            
            <div>
                <label class="block text-xs font-bold text-gray-500 uppercase mb-1">Description</label>
                <input type="text" name="description" placeholder="Lunch, Taxi, Coffee..." 
                       class="w-full p-3 bg-gray-50 dark:bg-gray-900 border-none rounded-xl text-sm font-medium focus:ring-2 focus:ring-brand-500">
            </div>
            
            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs font-bold text-gray-500 uppercase mb-1">Method</label>
                    <select name="payment_method" class="w-full p-3 bg-gray-50 dark:bg-gray-900 border-none rounded-xl text-sm font-bold focus:ring-2 focus:ring-brand-500">
                        <?php foreach($paymentMethods as $pm): ?>
                            <option value="<?php echo htmlspecialchars($pm); ?>"><?php echo htmlspecialchars($pm); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-bold text-gray-500 uppercase mb-1">Date</label>
                    <input type="date" name="date" value="<?php echo date('Y-m-d'); ?>" required 
                           class="w-full p-3 bg-gray-50 dark:bg-gray-900 border-none rounded-xl text-sm font-bold focus:ring-2 focus:ring-brand-500">
                </div>
            </div>
            
            <button type="submit" class="w-full bg-brand-600 hover:bg-brand-700 text-white font-bold py-3 rounded-xl shadow-lg shadow-brand-500/30 transition transform active:scale-95">
                Add Draft Entry 📝
            </button>
        </form>
    </div>

    <!-- Actions & Stats -->
    <?php if (count($pendingEntries) > 0): ?>
        <div class="bg-indigo-50 dark:bg-indigo-900/20 p-4 rounded-xl border border-indigo-100 dark:border-indigo-900/40 flex items-center justify-between">
            <div>
                <p class="text-xs font-bold text-indigo-600 dark:text-indigo-400 uppercase">Pending Sync</p>
                <p class="text-xl font-black text-indigo-900 dark:text-indigo-200"><?php echo count($pendingEntries); ?> Items</p>
            </div>
            <form method="POST">
                <input type="hidden" name="action" value="sync_all">
                <button type="submit" class="bg-indigo-600 hover:bg-indigo-700 text-white px-6 py-2 rounded-lg font-bold shadow-md transition">
                    Sync All 🚀
                </button>
            </form>
        </div>
        
        <!-- Pending List -->
        <div class="space-y-3">
            <?php foreach($pendingEntries as $entry): ?>
                <div class="bg-white dark:bg-gray-800 p-4 rounded-xl shadow-sm border border-gray-100 dark:border-gray-700 flex justify-between items-center group">
                    <div>
                        <p class="font-bold text-gray-800 dark:text-white"><?php echo htmlspecialchars($entry['description'] ?: 'Unspecified'); ?></p>
                        <p class="text-xs text-gray-500">
                            <?php echo $entry['date']; ?> • <span class="text-brand-600 font-semibold"><?php echo $entry['payment_method']; ?></span>
                        </p>
                    </div>
                    <div class="flex items-center gap-4">
                        <span class="font-black text-gray-900 dark:text-white">₹<?php echo number_format($entry['amount'], 2); ?></span>
                        <form method="POST" onsubmit="return confirm('Delete this draft?');">
                            <input type="hidden" name="delete_id" value="<?php echo $entry['id']; ?>">
                            <button type="submit" class="text-gray-400 hover:text-red-500 transition">✕</button>
                        </form>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php else: ?>
        <div class="text-center py-10 text-gray-400 italic text-sm">
            All caught up! No pending updates.
        </div>
    <?php endif; ?>

</div>

<?php require_once '../includes/footer.php'; ?>
