<?php
require_once '../config/database.php';
require_once '../includes/auth.php';
error_reporting(E_ALL);
ini_set('display_errors', 1);
requireLogin();

$userId = getCurrentUserId();

// Handle Actions (Add, Update, Delete)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['delete_id'])) {
        $emiId = $_POST['delete_id'];
        
        // Fetch EMI details before deletion
        $emiStmt = $pdo->prepare("SELECT name FROM emis WHERE id = ? AND user_id = ?");
        $emiStmt->execute([$emiId, $userId]);
        $emi = $emiStmt->fetch();
        
        if ($emi) {
            // Delete related expense entries
            $desc = "EMI Payment: " . $emi['name'];
            $delExp = $pdo->prepare("DELETE FROM expenses WHERE user_id = ? AND description = ? AND category = 'EMI/Bills'");
            $delExp->execute([$userId, $desc]);
            
            // Delete the EMI record
            $stmt = $pdo->prepare("DELETE FROM emis WHERE id = ? AND user_id = ?");
            $stmt->execute([$emiId, $userId]);
            
            $_SESSION['flash_message'] = "EMI plan and all related expense entries deleted.";
        } else {
            $_SESSION['flash_message'] = "EMI not found.";
        }
    } elseif (isset($_POST['update_payment'])) {
        // Manually record a payment (increment paid months with custom amount)
        $id = $_POST['update_payment'];
        $paidAmount = $_POST['paid_amount'];
        
        // Fetch EMI details
        $emiStmt = $pdo->prepare("SELECT * FROM emis WHERE id = ? AND user_id = ?");
        $emiStmt->execute([$id, $userId]);
        $emi = $emiStmt->fetch();
        
        if ($emi) {
            // Check if EMI is already completed
            if ($emi['status'] === 'Completed') {
                $_SESSION['flash_message'] = "This EMI is already completed.";
            } else {
                // Update paid months
                $newPaidMonths = $emi['paid_months'] + 1;
                
                // Check if this payment completes the EMI
                $status = ($newPaidMonths >= $emi['tenure_months']) ? 'Completed' : $emi['status'];
                
                $stmt = $pdo->prepare("UPDATE emis SET paid_months = ?, status = ? WHERE id = ? AND user_id = ?");
                $stmt->execute([$newPaidMonths, $status, $id, $userId]);
                
                // Record as Expense (Include links for cascading delete)
                $desc = "EMI Payment: " . $emi['name'];
                $paymentDate = $_POST['payment_date'] ?? date('Y-m-d');
                $expStmt = $pdo->prepare("INSERT INTO expenses (user_id, date, category, description, amount, payment_method, linked_type, linked_id) VALUES (?, ?, 'EMI/Bills', ?, ?, ?, 'EMI', ?)");
                $expStmt->execute([$userId, $paymentDate, $desc, $paidAmount, $emi['payment_method'], $id]);
                
                $_SESSION['flash_message'] = "Payment of ₹" . number_format($paidAmount, 2) . " recorded on " . $paymentDate . ". " . 
                                            ($status === 'Completed' ? "EMI COMPLETED! 🎉" : ($emi['tenure_months'] - $newPaidMonths) . " payments remaining.");
            }
        } else {
            $_SESSION['flash_message'] = "EMI not found.";
        }
    } else {
        // Add New EMI
        $name = $_POST['name'];
        $amount = $_POST['total_amount'];
        $rate = $_POST['interest_rate'];
        $tenure = $_POST['tenure_months'];
        $start = $_POST['start_date'];
        $method = $_POST['payment_method'] ?? 'Other';
        $expenseId = $_POST['expense_id'] ?? null;
        
        // Calculate EMI
        $r = $rate / 12 / 100;
        if ($r > 0) {
            $emi = ($amount * $r * pow(1 + $r, $tenure)) / (pow(1 + $r, $tenure) - 1);
        } else {
            $emi = $amount / $tenure;
        }

        $paidInitial = (int)($_POST['initial_paid_installments'] ?? 0);
        
        $stmt = $pdo->prepare("INSERT INTO emis (user_id, name, total_amount, interest_rate, tenure_months, emi_amount, paid_months, initial_paid_installments, start_date, payment_method, expense_id) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([$userId, $name, $amount, $rate, $tenure, $emi, $paidInitial, $paidInitial, $start, $method, $expenseId]);
        
        // Mark expense as converted if applicable
        if ($expenseId) {
            $stmt = $pdo->prepare("UPDATE expenses SET converted_to_emi = 1 WHERE id = ? AND user_id = ?");
            $stmt->execute([$expenseId, $userId]);
        }
        
        
        $_SESSION['flash_message'] = "EMI plan created.";
    }
    header("Location: emis.php");
    exit;
}

$pageTitle = 'EMI Tracker';
require_once '../includes/header.php';

// Fetch EMIs
$stmt = $pdo->prepare("SELECT * FROM emis WHERE user_id = ? ORDER BY start_date DESC");
$stmt->execute([$userId]);
$emis = $stmt->fetchAll();
// Fetch Credit Providers for manual selection
$stmt = $pdo->prepare("SELECT provider_name FROM credit_accounts WHERE user_id = ?");
$stmt->execute([$userId]);
$providers = $stmt->fetchAll(PDO::FETCH_COLUMN);

$isCompleted = false; // Initialize to avoid notice if no EMIs exist
?>

<div class="max-w-4xl mx-auto space-y-6">
    
    <!-- Header & Add Button -->
    <div class="flex justify-between items-center bg-white p-4 rounded-lg shadow">
        <h1 class="text-xl font-bold text-gray-800">EMI Tracker</h1>
        <button onclick="document.getElementById('addEmiModal').classList.remove('hidden')" 
                class="bg-brand-600 hover:bg-brand-700 text-white px-4 py-2 rounded text-sm font-medium transition">
            + Add New EMI
        </button>
    </div>


    <!-- EMI Grid -->
    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
        <?php foreach ($emis as $emi): ?>
            <?php 
                $paid = $emi['paid_months'];
                $totalMonths = $emi['tenure_months'];
                $paidAmount = $emi['emi_amount'] * $paid;
                $totalPayable = $emi['emi_amount'] * $totalMonths;
                $progress = min(100, round(($paid / $totalMonths) * 100));
                
                // Closure Date
                $startDate = new DateTime($emi['start_date']);
                $closureDate = clone $startDate;
                $closureDate->modify("+$totalMonths months");

                $isCompleted = ($emi['status'] === 'Completed'); 
            ?>
            <div class="bg-white rounded-lg shadow p-5 border <?php echo $isCompleted ? 'border-emerald-200 bg-emerald-50/10' : 'border-gray-100'; ?> relative overflow-hidden">
                <?php if ($isCompleted): ?>
                    <div class="absolute top-0 right-0">
                        <div class="bg-emerald-500 text-white text-[10px] font-black px-4 py-1 uppercase tracking-tighter rotate-12 translate-x-3 -translate-y-1 shadow-sm">
                            Completed
                        </div>
                    </div>
                <?php endif; ?>
                <div class="flex justify-between items-start mb-4">
                    <div>
                        <h3 class="font-bold text-lg text-gray-900"><?php echo htmlspecialchars($emi['name']); ?></h3>
                        <p class="text-xs text-gray-500">Started: <?php echo $startDate->format('M Y'); ?> • Ends: <?php echo $closureDate->format('M Y'); ?></p>
                    </div>
                    <div class="text-right">
                        <span class="block text-2xl font-bold <?php echo $isCompleted ? 'text-emerald-600' : 'text-brand-600'; ?>">₹<?php echo number_format($emi['emi_amount'], 0); ?></span>
                        <span class="text-xs text-gray-500">per month</span>
                    </div>
                </div>

                <!-- Progress Bar & Pie Chart -->
                <div class="mb-2 flex items-center space-x-4">
                    <div class="w-12 h-12 flex-shrink-0">
                        <canvas id="emi-chart-<?php echo $emi['id']; ?>" class="emi-chart" 
                                data-paid="<?php echo $paid; ?>" 
                                data-pending="<?php echo max(0, $totalMonths - $paid); ?>"
                                data-color="<?php echo $isCompleted ? '#10b981' : '#4f46e5'; ?>"></canvas>
                    </div>
                    <div class="flex-grow">
                        <div class="flex justify-between text-xs text-gray-600 mb-1">
                            <span>Paid: <?php echo $paid; ?>/<?php echo $totalMonths; ?> mths</span>
                            <span class="font-bold">₹<?php echo number_format($paidAmount, 0); ?> / ₹<?php echo number_format($totalPayable, 0); ?></span>
                        </div>
                        <div class="w-full bg-gray-200 rounded-full h-2">
                            <div class="<?php echo $isCompleted ? 'bg-emerald-500' : 'bg-brand-500'; ?> h-2 rounded-full transition-all duration-500" style="width: <?php echo $progress; ?>%"></div>
                        </div>
                    </div>
                </div>

                <!-- Actions -->
                <div class="flex justify-between items-center mt-4 pt-4 border-t border-gray-100">
                    <?php if (!$isCompleted): ?>
                        <button onclick="openPayModal('<?php echo $emi['id']; ?>', '<?php echo htmlspecialchars($emi['name']); ?>', '<?php echo $emi['emi_amount']; ?>')" 
                                class="text-xs bg-brand-50 hover:bg-brand-100 text-brand-700 px-3 py-1.5 rounded font-bold uppercase tracking-wider">
                            Record Payment
                        </button>
                    <?php else: ?>
                        <div class="flex items-center text-emerald-600 font-bold text-xs uppercase italic">
                            <span class="mr-1">✅</span> Fully Settled
                        </div>
                    <?php endif; ?>
                    
                    <form method="POST" onsubmit="return confirm('Delete this EMI?');">
                         <input type="hidden" name="delete_id" value="<?php echo $emi['id']; ?>">
                         <button type="submit" class="text-xs text-red-500 hover:text-red-700 font-medium">Delete</button>
                    </form>
                </div>
            </div>
        <?php endforeach; ?>
        
        <?php if (empty($emis)): ?>
            <div class="col-span-full text-center py-10 bg-white rounded shadow text-gray-500">
                No active or completed EMIs found.
            </div>
        <?php endif; ?>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
document.querySelectorAll('.emi-chart').forEach(canvas => {
    const paid = parseInt(canvas.dataset.paid);
    const pending = parseInt(canvas.dataset.pending);
    
    new Chart(canvas, {
        type: 'pie',
        data: {
            datasets: [{
                data: [paid, max(0.1, pending)],
                backgroundColor: [canvas.dataset.color, '#f3f4f6'],
                borderWidth: 0
            }]
        },
        options: {
            cutout: '70%',
            plugins: { tooltip: { enabled: false }, legend: { display: false } }
        }
    });
});

function max(a, b) { return a > b ? a : b; }

function openPayModal(id, name, amount) {
    document.getElementById('payEmiId').value = id;
    document.getElementById('payEmiAmount').value = amount;
    document.getElementById('payEmiName').innerText = name;
    document.getElementById('payEmiModal').classList.remove('hidden');
}

function closePayModal() {
    document.getElementById('payEmiModal').classList.add('hidden');
}
</script>

<!-- Record Payment Modal -->
<div id="payEmiModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 hidden flex items-center justify-center p-4 z-50">
    <div class="bg-white rounded-lg shadow-lg w-full max-w-sm p-6">
        <h2 class="text-lg font-bold mb-1">Record EMI Payment</h2>
        <p id="payEmiName" class="text-xs text-brand-600 font-bold uppercase mb-4"></p>
        
        <form method="POST" class="space-y-4">
            <input type="hidden" name="update_payment" id="payEmiId">
            <div>
                <label class="block text-xs font-bold text-gray-500 uppercase mb-1">Confirm Amount (₹)</label>
                <input type="number" name="paid_amount" id="payEmiAmount" step="0.01" required 
                       class="w-full border p-3 rounded-lg text-lg font-bold focus:ring-2 focus:ring-brand-500 outline-none">
            </div>
            <div>
                <label class="block text-xs font-bold text-gray-500 uppercase mb-1">Payment Date</label>
                <input type="date" name="payment_date" value="<?php echo date('Y-m-d'); ?>" required 
                       class="w-full border p-3 rounded-lg text-sm font-bold focus:ring-2 focus:ring-brand-500 outline-none">
            </div>
            <div class="flex justify-end space-x-3 pt-2">
                <button type="button" onclick="closePayModal()" class="px-4 py-2 text-sm text-gray-500 font-medium">Cancel</button>
                <button type="submit" class="px-6 py-2 bg-brand-600 text-white rounded-lg font-bold shadow-md hover:bg-brand-700 transition">Save Payment</button>
            </div>
        </form>
    </div>
</div>

<!-- Add EMI Modal -->
<div id="addEmiModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 hidden flex items-center justify-center p-4">
    <div class="bg-white rounded-lg shadow-lg w-full max-w-md p-6">
        <h2 class="text-xl font-bold mb-4">Add EMI Plan</h2>
        <form method="POST" class="space-y-4">
            <div>
                <label class="block text-xs font-bold text-gray-700 uppercase">Description</label>
                <input type="text" name="name" required class="w-full border p-2 rounded">
            </div>
            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs font-bold text-gray-700 uppercase">Principal (₹)</label>
                    <input type="number" step="0.01" name="total_amount" required class="w-full border p-2 rounded">
                </div>
                <div>
                    <label class="block text-xs font-bold text-gray-700 uppercase">Interest Rate (% p.a)</label>
                    <input type="number" step="0.1" name="interest_rate" value="15" required class="w-full border p-2 rounded">
                </div>
            </div>
            <div>
                <label class="block text-xs font-bold text-gray-700 uppercase">Linked Account / Card</label>
                <select name="payment_method" class="w-full border p-2 rounded bg-white">
                    <option value="Other">Other / Cash</option>
                    <?php foreach($providers as $p) echo "<option>$p</option>"; ?>
                </select>
            </div>
            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs font-bold text-gray-700 uppercase">Tenure (Months)</label>
                    <input type="number" name="tenure_months" required class="w-full border p-2 rounded">
                </div>
                <div>
                    <label class="block text-xs font-bold text-gray-700 uppercase">Start Date</label>
                    <input type="date" name="start_date" value="<?php echo date('Y-m-d'); ?>" required class="w-full border p-2 rounded">
                </div>
            </div>

            <div>
                <label class="block text-xs font-bold text-gray-700 uppercase">Installments Already Paid (Before Start)</label>
                <input type="number" name="initial_paid_installments" value="0" min="0" required class="w-full border p-2 rounded">
                <p class="text-[10px] text-gray-400 mt-1">These will be added to the total count without creating expense records.</p>
            </div>
            
            <div class="flex justify-end space-x-3 pt-4">
                <button type="button" onclick="document.getElementById('addEmiModal').classList.add('hidden')" class="px-4 py-2 text-gray-600 hover:text-gray-800">Cancel</button>
                <button type="submit" class="px-4 py-2 bg-brand-600 text-white rounded hover:bg-brand-700">Calculate & Save</button>
            </div>
        </form>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>
