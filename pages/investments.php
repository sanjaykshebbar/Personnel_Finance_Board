<?php
require_once '../config/database.php';
require_once '../includes/auth.php';
requireLogin();

$userId = getCurrentUserId();

$categories = ['Mutual Fund', 'Stocks', 'Fixed Deposit', 'Gold', 'Crypto', 'PF/PPF', 'Insurance', 'Savings', 'Other'];

// Handle Actions (Plans)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['add_plan']) || isset($_POST['edit_plan'])) {
        $name = $_POST['name'];
        $category = ($_POST['category'] === 'Custom...') ? $_POST['custom_category'] : $_POST['category'];
        $type = $_POST['type'];
        $amount = $_POST['amount'];
        $tenure = !empty($_POST['tenure_months']) ? (int)$_POST['tenure_months'] : 0;
        $start = $_POST['start_date'];
        
        if (isset($_POST['add_plan'])) {
            $stmt = $pdo->prepare("INSERT INTO investment_plans (user_id, name, category, type, amount, tenure_months, start_date) VALUES (?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([$userId, $name, $category, $type, $amount, $tenure, $start]);
            $_SESSION['flash_message'] = "Investment plan created.";
        } else {
            $planId = $_POST['edit_plan'];
            $stmt = $pdo->prepare("UPDATE investment_plans SET name = ?, category = ?, type = ?, amount = ?, tenure_months = ?, start_date = ? WHERE id = ? AND user_id = ?");
            $stmt->execute([$name, $category, $type, $amount, $tenure, $start, $planId, $userId]);
            $_SESSION['flash_message'] = "Investment plan updated.";
        }
    } elseif (isset($_POST['record_payment'])) {
        $id = $_POST['record_payment'];
        $paidAmount = $_POST['paid_amount'];
        $paymentDates = isset($_POST['payment_dates']) ? $_POST['payment_dates'] : [date('Y-m-d')];
        
        $planStmt = $pdo->prepare("SELECT * FROM investment_plans WHERE id = ?");
        $planStmt->execute([$id]);
        $plan = $planStmt->fetch();
        
        if ($plan) {
            $count = 0;
            foreach ($paymentDates as $paymentDate) {
                if (empty($paymentDate)) continue;
                
                // 1. Update Plan Count
                $stmt = $pdo->prepare("UPDATE investment_plans SET paid_count = paid_count + 1 WHERE id = ? AND user_id = ?");
                $stmt->execute([$id, $userId]);
                
                // 2. Create Expense Record
                $desc = "Investment: " . $plan['name'];
                $method = $_POST['payment_method'] ?? 'Bank Account';
                $expStmt = $pdo->prepare("INSERT INTO expenses (user_id, date, category, description, amount, payment_method) VALUES (?, ?, 'Investment', ?, ?, ?)");
                $expStmt->execute([$userId, $paymentDate, $desc, $paidAmount, $method]);
                $expenseId = $pdo->lastInsertId();
                
                // 3. Create Investment History Record
                $stmt = $pdo->prepare("INSERT INTO investments (user_id, investment_name, frequency, amount, status, due_date, plan_id, expense_id) 
                                      VALUES (:uid, :iname, :freq, :amt, 'Paid', :dt, :pid, :eid)");
                $stmt->execute([
                    ':uid' => $userId,
                    ':iname' => $plan['name'] . " (Installment)",
                    ':freq' => $plan['type'],
                    ':amt' => $paidAmount,
                    ':dt' => $paymentDate,
                    ':pid' => $id,
                    ':eid' => $expenseId
                ]);
                $count++;
            }
            $_SESSION['flash_message'] = "Recorded $count installments for " . htmlspecialchars($plan['name']) . ".";
        }
    } elseif (isset($_POST['delete_plan'])) {
        $planId = $_POST['delete_plan'];
        
        // Fetch plan details before deletion
        $planStmt = $pdo->prepare("SELECT name FROM investment_plans WHERE id = ? AND user_id = ?");
        $planStmt->execute([$planId, $userId]);
        $plan = $planStmt->fetch();
        
        if ($plan) {
            $desc = "Investment: " . $plan['name'];
            $invName = $plan['name'] . " (Installment)";
            
            // Delete related expense entries
            $delExp = $pdo->prepare("DELETE FROM expenses WHERE user_id = ? AND description = ? AND category = 'Investment'");
            $delExp->execute([$userId, $desc]);
            
            // Delete related investment history entries
            $delInv = $pdo->prepare("DELETE FROM investments WHERE user_id = ? AND investment_name = ?");
            $delInv->execute([$userId, $invName]);
            
            // Delete the plan
            $stmt = $pdo->prepare("DELETE FROM investment_plans WHERE id = ? AND user_id = ?");
            $stmt->execute([$planId, $userId]);
            
            $_SESSION['flash_message'] = "Investment plan and all related entries deleted.";
        } else {
            $_SESSION['flash_message'] = "Plan not found.";
        }
    } elseif (isset($_POST['delete_history_id'])) {
        $histId = $_POST['delete_history_id'];
        
        // Fetch history details for smart cleanup
        $stmt = $pdo->prepare("SELECT * FROM investments WHERE id = ? AND user_id = ?");
        $stmt->execute([$histId, $userId]);
        $hist = $stmt->fetch();
        
        if ($hist) {
            $planId = $hist['plan_id'];
            $expenseId = $hist['expense_id'];

            // SMART FALLBACK: If tracking IDs are missing (legacy records), match by name and date
            if (empty($planId)) {
                $rawName = str_replace(" (Installment)", "", $hist['investment_name']);
                $ps = $pdo->prepare("SELECT id FROM investment_plans WHERE name = ? AND user_id = ?");
                $ps->execute([$rawName, $userId]);
                $planId = $ps->fetchColumn();
            }

            // 1. Decrement Plan Count
            if ($planId) {
                $pdo->prepare("UPDATE investment_plans SET paid_count = MAX(0, paid_count - 1) WHERE id = ? AND user_id = ?")->execute([$planId, $userId]);
            }
            
            // 2. Delete Expense Entry (Smart or Match-based)
            if ($expenseId) {
                $pdo->prepare("DELETE FROM expenses WHERE id = ? AND user_id = ?")->execute([$expenseId, $userId]);
            } else {
                // Fallback: Delete matching expense by description and date
                $fallbackDesc = "Investment: " . str_replace(" (Installment)", "", $hist['investment_name']);
                $pdo->prepare("DELETE FROM expenses WHERE user_id = ? AND date = ? AND description = ? AND amount = ? AND category = 'Investment'")
                    ->execute([$userId, $hist['due_date'], $fallbackDesc, $hist['amount']]);
            }
            
            // 3. Delete History Record
            $pdo->prepare("DELETE FROM investments WHERE id = ? AND user_id = ?")->execute([$histId, $userId]);
            
            $_SESSION['flash_message'] = "Record deleted and progress correctly reverted.";
        }
    }
    header("Location: investments.php");
    exit;
}

$pageTitle = 'Investments & SIP Tracker';
require_once '../includes/header.php';

// --- Filter & Sort Settings ---
$search = $_GET['search'] ?? '';
$catFilter = $_GET['cat_filter'] ?? '';
$sortBy = $_GET['sort_by'] ?? 'newest';

// Build Query for Plans
$query = "SELECT * FROM investment_plans WHERE user_id = ?";
$params = [$userId];

if (!empty($search)) {
    $query .= " AND (name LIKE ? OR category LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
}
if (!empty($catFilter)) {
    $query .= " AND category = ?";
    $params[] = $catFilter;
}

// Sorting logic
switch ($sortBy) {
    case 'name_asc': $query .= " ORDER BY name ASC"; break;
    case 'name_desc': $query .= " ORDER BY name DESC"; break;
    case 'amount_high': $query .= " ORDER BY amount DESC"; break;
    case 'amount_low': $query .= " ORDER BY amount ASC"; break;
    case 'oldest': $query .= " ORDER BY created_at ASC"; break;
    default: $query .= " ORDER BY created_at DESC";
}

$stmt = $pdo->prepare($query);
$stmt->execute($params);
$plans = $stmt->fetchAll();

// Get unique categories for filter
$catStmt = $pdo->prepare("SELECT DISTINCT category FROM investment_plans WHERE user_id = ? ORDER BY category ASC");
$catStmt->execute([$userId]);
$existingCategories = $catStmt->fetchAll(PDO::FETCH_COLUMN);

// Master Chart Data (remains global for now)

// Master Chart Data (Grouped by Name/Item)
$masterLabels = [];
$masterValues = [];
$colorPalette = [
    '#6366f1', '#a855f7', '#ec4899', '#f97316', '#10b981', '#3b82f6', '#f43f5e', '#8b5cf6', '#06b6d4', '#f59e0b'
];

foreach ($plans as $plan) {
    $masterLabels[] = $plan['name'];
    $masterValues[] = $plan['paid_count'] * $plan['amount'];
}

?>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<div class="space-y-6">
    
    <!-- Top Stats & Master Chart -->
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <!-- Stats Cards -->
        <div class="lg:col-span-1 space-y-4">
            <div class="bg-white p-6 rounded-xl shadow-sm border border-gray-100">
                <h3 class="text-sm font-medium text-gray-500 mb-1">Total Portfolio Value</h3>
                <p class="text-3xl font-bold text-gray-900">₹<?php echo number_format(array_sum($masterValues), 0); ?></p>
            </div>
            
            <div class="bg-brand-600 p-6 rounded-xl shadow-sm text-white">
                <h3 class="text-sm font-brand-100 mb-1">Portfolio Strategy</h3>
                <p class="text-xs opacity-80 mb-4">Manage your assets and track your path to financial freedom.</p>
                <button onclick="document.getElementById('addPlanModal').classList.remove('hidden')" 
                        class="w-full bg-white bg-opacity-20 hover:bg-opacity-30 py-2 rounded-lg text-sm font-bold transition">
                    + New Investment Plan
                </button>
            </div>
        </div>

        <!-- Master Pie Chart -->
        <div class="lg:col-span-2 bg-white p-6 rounded-xl shadow-sm border border-gray-100 flex flex-col items-center">
            <h3 class="text-lg font-bold text-gray-900 mb-4 w-full text-left">Master Portfolio Breakdown</h3>
            <?php if(empty($masterValues)): ?>
                <div class="h-64 flex items-center justify-center text-gray-400 text-sm italic">
                    Add investments to see portfolio weightage
                </div>
            <?php else: ?>
                <div class="h-64 w-full flex items-center justify-center">
                    <canvas id="masterChart"></canvas>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Management Bar: Search, Filter, Sort -->
    <div class="bg-white p-4 rounded-xl shadow-sm border border-gray-100 flex flex-col md:flex-row gap-4 items-center justify-between">
        <form method="GET" class="flex flex-col md:flex-row gap-4 w-full">
            <div class="relative flex-1">
                <span class="absolute left-3 top-1/2 -translate-y-1/2 text-gray-400">🔍</span>
                <input type="text" name="search" value="<?php echo htmlspecialchars($search); ?>" placeholder="Search plans..." 
                       class="w-full pl-10 pr-4 py-2 bg-gray-50 border-none rounded-lg text-sm focus:ring-2 focus:ring-brand-500 outline-none">
            </div>
            
            <div class="flex gap-2">
                <select name="cat_filter" onchange="this.form.submit()" class="bg-gray-50 border-none rounded-lg text-sm py-2 px-4 focus:ring-2 focus:ring-brand-500 outline-none">
                    <option value="">All Categories</option>
                    <?php foreach ($existingCategories as $cat): ?>
                        <option value="<?php echo $cat; ?>" <?php echo ($catFilter === $cat) ? 'selected' : ''; ?>><?php echo $cat; ?></option>
                    <?php endforeach; ?>
                </select>

                <select name="sort_by" onchange="this.form.submit()" class="bg-gray-50 border-none rounded-lg text-sm py-2 px-4 focus:ring-2 focus:ring-brand-500 outline-none">
                    <option value="newest" <?php echo ($sortBy === 'newest') ? 'selected' : ''; ?>>Newest First</option>
                    <option value="oldest" <?php echo ($sortBy === 'oldest') ? 'selected' : ''; ?>>Oldest First</option>
                    <option value="name_asc" <?php echo ($sortBy === 'name_asc') ? 'selected' : ''; ?>>Name (A-Z)</option>
                    <option value="name_desc" <?php echo ($sortBy === 'name_desc') ? 'selected' : ''; ?>>Name (Z-A)</option>
                    <option value="amount_high" <?php echo ($sortBy === 'amount_high') ? 'selected' : ''; ?>>Amount (High-Low)</option>
                    <option value="amount_low" <?php echo ($sortBy === 'amount_low') ? 'selected' : ''; ?>>Amount (Low-High)</option>
                </select>
            </div>
            
            <?php if(!empty($search) || !empty($catFilter)): ?>
                <a href="investments.php" class="text-xs font-bold text-brand-600 hover:text-brand-800 self-center">Clear Filters</a>
            <?php endif; ?>
        </form>
    </div>


    <!-- Active Plans Grid -->
    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
        <?php foreach ($plans as $index => $plan): ?>
            <?php 
                $isOngoing = ($plan['tenure_months'] <= 0);
                $totalAmt = !$isOngoing ? ($plan['tenure_months'] * $plan['amount']) : 0;
                $paidAmt = $plan['paid_count'] * $plan['amount'];
                $pendingAmt = !$isOngoing ? ($totalAmt - $paidAmt) : 'N/A';
                $totalPayables = $plan['tenure_months'];
                $paid = $plan['paid_count'];
                $perc = !$isOngoing ? min(100, round(($paid / $totalPayables) * 100)) : 100; // For chart we use 100 or something if ongoing?
            ?>
            <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-5 flex flex-col md:flex-row gap-6">
                <!-- Individual Pie Chart -->
                <div class="w-full md:w-32 flex flex-col items-center justify-center">
                        <div class="h-24 w-24 relative">
                            <canvas id="planChart_<?php echo $plan['id']; ?>"></canvas>
                            <div class="absolute inset-0 flex items-center justify-center pointer-events-none">
                                <span class="text-[10px] font-bold text-gray-900"><?php echo $isOngoing ? '∞' : $perc . '%'; ?></span>
                            </div>
                        </div>
                        <span class="text-[10px] text-gray-400 uppercase font-bold mt-2"><?php echo $isOngoing ? 'Ongoing' : 'Paid vs Pend.'; ?></span>
                </div>

                <!-- Plan Details -->
                <div class="flex-1 space-y-3">
                    <div class="flex justify-between items-start">
                        <div>
                            <span class="px-2 py-0.5 bg-brand-50 text-brand-700 text-[10px] font-bold uppercase rounded-full border border-brand-100">
                                <?php echo $plan['category']; ?> • <?php echo $plan['type']; ?>
                            </span>
                            <h3 class="text-lg font-bold text-gray-900 mt-1"><?php echo htmlspecialchars($plan['name']); ?></h3>
                        </div>
                        <div class="text-right">
                            <p class="text-xl font-bold text-gray-900">₹<?php echo number_format($plan['amount']); ?></p>
                            <p class="text-[10px] text-gray-400 uppercase font-bold">Planned SIP</p>
                        </div>
                    </div>

                    <div class="grid grid-cols-2 gap-2 text-[10px] font-bold uppercase tracking-wider">
                        <div class="p-2 bg-green-50 rounded-lg text-green-700 border border-green-100">
                            Paid: ₹<?php echo number_format($paidAmt); ?>
                        </div>
                        <div class="p-2 bg-gray-50 rounded-lg text-gray-500 border border-gray-100">
                            <?php echo $isOngoing ? 'Indefinite' : 'Pend: ₹' . number_format($pendingAmt); ?>
                        </div>
                    </div>

                    <div class="pt-4 border-t border-gray-50 flex items-center justify-between">
                        <div class="flex gap-2">
                            <button onclick='openPaymentModal(<?php echo json_encode($plan); ?>)' 
                                    class="flex items-center gap-2 bg-gray-900 hover:bg-black text-white px-4 py-2 rounded-lg text-xs font-bold transition shadow-sm">
                                <span>✅</span> Log Payment
                            </button>
                            <button onclick='openEditModal(<?php echo json_encode($plan); ?>)' 
                                    class="p-2 bg-gray-100 hover:bg-gray-200 text-gray-600 rounded-lg transition" title="Edit Plan">
                                ✏️
                            </button>
                        </div>
                        <form method="POST" onsubmit="return confirm('Delete this plan and all history?');">
                            <input type="hidden" name="delete_plan" value="<?php echo $plan['id']; ?>">
                            <button type="submit" class="p-2 text-gray-300 hover:text-red-500 transition">🗑️</button>
                        </form>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
        
        <?php if(empty($plans)): ?>
            <div class="col-span-full bg-white p-12 rounded-xl text-center border-2 border-dashed border-gray-100">
                <p class="text-gray-400">No active investment plans. Create one to start tracking!</p>
            </div>
        <?php endif; ?>
    </div>
</div>

    <!-- Transaction History (Logs) - Moved outside modal for better visibility -->
    <div class="mt-12 bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
        <div class="px-6 py-4 border-b border-gray-200 flex justify-between items-center bg-gray-50/50">
            <div>
                <h3 class="text-lg font-bold text-gray-900">Payment History Logs</h3>
                <p class="text-[10px] text-gray-400 font-bold uppercase tracking-wider">Historical records for all investments</p>
            </div>
            <span class="px-3 py-1 bg-gray-100 rounded-full text-[10px] font-black text-gray-400 uppercase tracking-tighter">Verified Entries</span>
        </div>
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200 text-sm">
                <thead class="bg-gray-50/30">
                    <tr>
                        <th class="px-6 py-3 text-left font-bold text-gray-500 uppercase tracking-wider text-[10px]">Date</th>
                        <th class="px-6 py-3 text-left font-bold text-gray-500 uppercase tracking-wider text-[10px]">Investment</th>
                        <th class="px-6 py-3 text-left font-bold text-gray-500 uppercase tracking-wider text-[10px]">Freq</th>
                        <th class="px-6 py-3 text-right font-bold text-gray-500 uppercase tracking-wider text-[10px]">Amount</th>
                        <th class="px-6 py-3 text-right font-bold text-gray-500 uppercase tracking-wider text-[10px]">Action</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200">
                    <?php
                    $histStmt = $pdo->prepare("SELECT * FROM investments WHERE user_id = ? ORDER BY due_date DESC LIMIT 20");
                    $histStmt->execute([$userId]);
                    $history = $histStmt->fetchAll();
                    foreach ($history as $h):
                    ?>
                    <tr class="hover:bg-gray-50 transition-colors">
                        <td class="px-6 py-4 whitespace-nowrap text-gray-500 text-xs font-medium"><?php echo date('d M Y', strtotime($h['due_date'])); ?></td>
                        <td class="px-6 py-4 font-bold text-gray-900"><?php echo htmlspecialchars($h['investment_name']); ?></td>
                        <td class="px-6 py-4 text-gray-400 text-xs uppercase font-bold"><?php echo $h['frequency']; ?></td>
                        <td class="px-6 py-4 text-right font-black text-gray-900">₹<?php echo number_format($h['amount'], 2); ?></td>
                        <td class="px-6 py-4 text-right">
                            <form method="POST" onsubmit="return confirm('Smart Delete will undo plan progress and remove expense entry. Confirm?');" class="inline">
                                <input type="hidden" name="delete_history_id" value="<?php echo $h['id']; ?>">
                                <button type="submit" class="p-2 text-red-300 hover:text-red-500 hover:bg-red-50 rounded-lg transition-all" title="Secure Delete & Rollback">
                                    🗑️
                                </button>
                            </form>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    <?php if(empty($history)): ?>
                        <tr><td colspan="5" class="px-6 py-10 text-center text-gray-400 italic">No payment history found.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Add Plan Modal -->
<div id="addPlanModal" class="fixed inset-0 bg-gray-900 bg-opacity-60 hidden flex items-center justify-center p-4 z-50 backdrop-blur-sm">
    <div class="bg-white rounded-2xl shadow-xl w-full max-w-md p-8">
        <h2 class="text-2xl font-bold text-gray-900 mb-2">New Investment Plan</h2>
        <form method="POST" class="space-y-4">
            <input type="hidden" name="add_plan" value="1">
            <div class="grid grid-cols-2 gap-4">
                <div class="col-span-2">
                    <label class="block text-xs font-bold text-gray-700 uppercase mb-1">Investment Name</label>
                    <input type="text" name="name" required placeholder="e.g., Mirae Asset Bluechip SIP" class="w-full border p-3 rounded-xl text-sm outline-none focus:ring-2 focus:ring-brand-500">
                </div>
                <div>
                    <label class="block text-xs font-bold text-gray-700 uppercase mb-1">Category</label>
                    <select name="category" onchange="toggleCustomCategory(this, 'customCatAdd')" class="w-full border p-3 rounded-xl text-sm bg-white outline-none">
                        <?php foreach($categories as $cat) echo "<option value=\"$cat\">$cat</option>"; ?>
                        <option value="Custom...">Custom...</option>
                    </select>
                </div>
                <div id="customCatAdd" class="hidden">
                    <label class="block text-xs font-bold text-gray-700 uppercase mb-1">New Saving Type</label>
                    <input type="text" name="custom_category" placeholder="e.g. House Fund" class="w-full border p-3 rounded-xl text-sm outline-none focus:ring-2 focus:ring-brand-500">
                </div>
                <div>
                    <label class="block text-xs font-bold text-gray-700 uppercase mb-1">Frequency</label>
                    <select name="type" onchange="updateTenureLabel(this, 'tenureLabelAdd')" class="w-full border p-3 rounded-xl text-sm bg-white outline-none">
                        <option>Monthly</option><option>Quarterly</option><option>Yearly</option>
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-bold text-gray-700 uppercase mb-1">Inst. Amount (₹)</label>
                    <input type="number" name="amount" required placeholder="5000" class="w-full border p-3 rounded-xl text-sm">
                </div>
                <div>
                    <label id="tenureLabelAdd" class="block text-xs font-bold text-gray-700 uppercase mb-1">Tenure (Months)</label>
                    <input type="number" name="tenure_months" placeholder="Leave empty for ongoing" class="w-full border p-3 rounded-xl text-sm">
                </div>
                <div class="col-span-2">
                    <label class="block text-xs font-bold text-gray-700 uppercase mb-1">Start Date</label>
                    <input type="date" name="start_date" required value="<?php echo date('Y-m-d'); ?>" class="w-full border p-3 rounded-xl text-sm">
                </div>
            </div>
            <div class="flex gap-4 pt-6">
                <button type="button" onclick="document.getElementById('addPlanModal').classList.add('hidden')" class="flex-1 px-4 py-3 bg-gray-100 rounded-xl text-sm font-bold">Cancel</button>
                <button type="submit" class="flex-1 px-4 py-3 bg-gray-900 text-white rounded-xl text-sm font-bold shadow-lg hover:bg-black transition-all">Create Plan</button>
            </div>
        </form>
    </div>
</div>

<!-- Edit Plan Modal -->
<div id="editPlanModal" class="fixed inset-0 bg-gray-900 bg-opacity-60 hidden flex items-center justify-center p-4 z-50 backdrop-blur-sm">
    <div class="bg-white rounded-2xl shadow-xl w-full max-w-md p-8">
        <h2 class="text-2xl font-bold text-gray-900 mb-2">Edit Investment Plan</h2>
        <form method="POST" class="space-y-4">
            <input type="hidden" name="edit_plan" id="editPlanId">
            <div class="grid grid-cols-2 gap-4">
                <div class="col-span-2">
                    <label class="block text-xs font-bold text-gray-700 uppercase mb-1">Investment Name</label>
                    <input type="text" name="name" id="editPlanName" required class="w-full border p-3 rounded-xl text-sm outline-none focus:ring-2 focus:ring-brand-500">
                </div>
                <div>
                    <label class="block text-xs font-bold text-gray-700 uppercase mb-1">Category</label>
                    <select name="category" id="editPlanCategory" onchange="toggleCustomCategory(this, 'customCatEdit')" class="w-full border p-3 rounded-xl text-sm bg-white outline-none">
                        <?php foreach($categories as $cat) echo "<option value=\"$cat\">$cat</option>"; ?>
                        <option value="Custom...">Custom...</option>
                    </select>
                </div>
                <div id="customCatEdit" class="hidden">
                    <label class="block text-xs font-bold text-gray-700 uppercase mb-1">New Saving Type</label>
                    <input type="text" name="custom_category" id="editCustomCategory" placeholder="e.g. House Fund" class="w-full border p-3 rounded-xl text-sm outline-none focus:ring-2 focus:ring-brand-500">
                </div>
                <div>
                    <label class="block text-xs font-bold text-gray-700 uppercase mb-1">Frequency</label>
                    <select name="type" id="editPlanType" onchange="updateTenureLabel(this, 'tenureLabelEdit')" class="w-full border p-3 rounded-xl text-sm bg-white outline-none">
                        <option>Monthly</option><option>Quarterly</option><option>Yearly</option>
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-bold text-gray-700 uppercase mb-1">Inst. Amount (₹)</label>
                    <input type="number" name="amount" id="editPlanAmount" required class="w-full border p-3 rounded-xl text-sm">
                </div>
                <div>
                    <label id="tenureLabelEdit" class="block text-xs font-bold text-gray-700 uppercase mb-1">Tenure (Months)</label>
                    <input type="number" name="tenure_months" id="editPlanTenure" placeholder="Leave empty for ongoing" class="w-full border p-3 rounded-xl text-sm">
                </div>
                <div class="col-span-2">
                    <label class="block text-xs font-bold text-gray-700 uppercase mb-1">Start Date</label>
                    <input type="date" name="start_date" id="editPlanStart" required class="w-full border p-3 rounded-xl text-sm">
                </div>
            </div>
            <div class="flex gap-4 pt-6">
                <button type="button" onclick="document.getElementById('editPlanModal').classList.add('hidden')" class="flex-1 px-4 py-3 bg-gray-100 rounded-xl text-sm font-bold">Cancel</button>
                <button type="submit" class="flex-1 px-4 py-3 bg-brand-600 text-white rounded-xl text-sm font-bold shadow-lg hover:bg-brand-700 transition-all">Save Changes</button>
            </div>
        </form>
    </div>
</div>

<div id="paymentModal" class="fixed inset-0 bg-gray-900 bg-opacity-60 hidden flex items-center justify-center p-4 z-50 backdrop-blur-sm">
    <div class="bg-white rounded-2xl shadow-2xl w-full max-w-sm p-8 transform transition-all">
        <div class="flex items-center justify-between mb-6">
            <div>
                <h2 class="text-xl font-black text-gray-900 tracking-tight">Record Payment</h2>
                <p class="text-[10px] text-brand-600 font-bold uppercase tracking-wider" id="payPlanName"></p>
            </div>
            <button onclick="document.getElementById('paymentModal').classList.add('hidden')" class="text-gray-400 hover:text-gray-600">✕</button>
        </div>

        <form method="POST" class="space-y-5">
            <input type="hidden" name="record_payment" id="payPlanId">
            
            <div class="space-y-1">
                <label class="block text-[10px] font-black text-gray-400 uppercase tracking-widest">Investment Amount (Per Entry)</label>
                <div class="relative">
                    <span class="absolute left-4 top-1/2 -translate-y-1/2 font-bold text-gray-400">₹</span>
                    <input type="number" name="paid_amount" id="payAmount" required 
                           class="w-full border-gray-100 bg-gray-50 pl-8 p-4 rounded-xl text-xl font-black outline-none focus:ring-2 focus:ring-brand-500 focus:bg-white transition-all">
                </div>
            </div>

            <div class="space-y-3">
                <div class="flex justify-between items-center">
                    <label class="block text-[10px] font-black text-gray-400 uppercase tracking-widest">Payment Dates</label>
                    <button type="button" onclick="addDateRow()" class="text-brand-600 hover:text-brand-700 text-[10px] font-black uppercase tracking-widest">+ Add Date</button>
                </div>
                <div id="dateRowsContainer" class="space-y-2 max-h-40 overflow-y-auto pr-2 custom-scrollbar">
                    <!-- Dynamic Date Rows -->
                </div>
            </div>

            <div class="space-y-1">
                <label class="block text-[10px] font-black text-gray-400 uppercase tracking-widest">Source Account</label>
                <select name="payment_method" class="w-full border p-3 rounded-xl text-sm font-bold bg-white outline-none focus:ring-2 focus:ring-brand-500">
                    <option value="Bank Account">Bank Account</option>
                    <option value="Credit Card">Credit Card</option>
                </select>
            </div>

            <div class="pt-4">
                <button type="submit" class="w-full bg-gray-900 text-white py-4 rounded-xl text-sm font-black hover:bg-black transition-all shadow-xl shadow-gray-900/10 active:scale-[0.98]">
                    Confirm Bulk Entry →
                </button>
                <button type="button" onclick="document.getElementById('paymentModal').classList.add('hidden')" 
                        class="w-full mt-2 text-xs font-bold text-gray-400 py-2 hover:text-gray-600 transition">Cancel</button>
            </div>
        </form>
    </div>
</div>

<script>
function updateTenureLabel(select, labelId) {
    const label = document.getElementById(labelId);
    const freq = select.value;
    if (freq === 'Monthly') label.innerText = 'Tenure (Months)';
    else if (freq === 'Quarterly') label.innerText = 'Tenure (Quarters)';
    else if (freq === 'Yearly') label.innerText = 'Tenure (Years)';
}

function openEditModal(plan) {
    document.getElementById('editPlanId').value = plan.id;
    document.getElementById('editPlanName').value = plan.name;
    document.getElementById('editPlanType').value = plan.type;
    document.getElementById('editPlanAmount').value = plan.amount;
    document.getElementById('editPlanTenure').value = plan.tenure_months || '';
    document.getElementById('editPlanStart').value = plan.start_date;
    
    // Update tenure label for edit modal
    updateTenureLabel(document.getElementById('editPlanType'), 'tenureLabelEdit');
    
    // Set category and check if it's custom
    const catSelect = document.getElementById('editPlanCategory');
    const isStandard = Array.from(catSelect.options).some(opt => opt.value === plan.category);
    
    if (isStandard) {
        catSelect.value = plan.category;
        document.getElementById('customCatEdit').classList.add('hidden');
    } else {
        catSelect.value = 'Custom...';
        document.getElementById('editCustomCategory').value = plan.category;
        document.getElementById('customCatEdit').classList.remove('hidden');
    }
    
    document.getElementById('editPlanModal').classList.remove('hidden');
}

function toggleCustomCategory(select, containerId) {
    const container = document.getElementById(containerId);
    if (select.value === 'Custom...') {
        container.classList.remove('hidden');
        container.querySelector('input').focus();
    } else {
        container.classList.add('hidden');
    }
}

function openPaymentModal(plan) {
    document.getElementById('payPlanId').value = plan.id;
    document.getElementById('payPlanName').innerText = plan.name;
    document.getElementById('payAmount').value = plan.amount;
    
    // Reset date rows
    const container = document.getElementById('dateRowsContainer');
    container.innerHTML = '';
    addDateRow(true); // Add initial row
    
    document.getElementById('paymentModal').classList.remove('hidden');
}

function addDateRow(isFirst = false) {
    const container = document.getElementById('dateRowsContainer');
    const div = document.createElement('div');
    div.className = 'flex gap-2 items-center animated fadeIn faster';
    
    const today = new Date().toISOString().split('T')[0];
    
    div.innerHTML = `
        <input type="date" name="payment_dates[]" required value="${today}"
               class="flex-1 border-gray-100 bg-gray-50 p-3 rounded-xl text-xs font-bold outline-none focus:ring-2 focus:ring-brand-500 focus:bg-white transition-all">
        ${!isFirst ? `
            <button type="button" onclick="this.parentElement.remove()" class="p-2 text-red-300 hover:text-red-500 transition-all">✕</button>
        ` : '<div class="w-8"></div>'}
    `;
    container.appendChild(div);
}

// Master Chart
<?php if(!empty($masterValues)): ?>
new Chart(document.getElementById('masterChart'), {
    type: 'doughnut',
    data: {
        labels: <?php echo json_encode($masterLabels); ?>,
        datasets: [{
            data: <?php echo json_encode($masterValues); ?>,
            backgroundColor: <?php echo json_encode(array_slice($colorPalette, 0, count($masterLabels))); ?>,
            borderWidth: 0,
            hoverOffset: 15
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        cutout: '65%',
        plugins: {
            legend: {
                position: 'right',
                labels: { usePointStyle: true, font: { size: 11, weight: '600' } }
            },
            tooltip: {
                callbacks: { label: (ctx) => ' ' + ctx.label + ': ₹' + ctx.parsed.toLocaleString() }
            }
        }
    }
});
<?php endif; ?>

// Individual Plan Charts
<?php foreach ($plans as $plan): ?>
    <?php 
        $p = $plan['paid_count'] * $plan['amount'];
        $t = $plan['tenure_months'] * $plan['amount'];
        $r = max(0, $t - $p);
    ?>
    new Chart(document.getElementById('planChart_<?php echo $plan['id']; ?>'), {
        type: 'pie',
        <?php if ($plan['tenure_months'] > 0): ?>
        data: {
            labels: ['Paid', 'Pending'],
            datasets: [{
                data: [<?php echo $plan['paid_count'] * $plan['amount']; ?>, <?php echo ($plan['tenure_months'] - $plan['paid_count']) * $plan['amount']; ?>],
                backgroundColor: ['#10b981', '#f3f4f6'],
                borderWidth: 0
            }]
        },
        <?php else: ?>
        data: {
            labels: ['Total Invested'],
            datasets: [{
                data: [100],
                backgroundColor: ['#6366f1'],
                borderWidth: 0
            }]
        },
        <?php endif; ?>
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: { legend: { display: false }, tooltip: { enabled: <?php echo $plan['tenure_months'] > 0 ? 'true' : 'false'; ?> } }
        }
    });
<?php endforeach; ?>
</script>

<?php require_once '../includes/footer.php'; ?>
