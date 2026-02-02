<?php
require_once '../config/database.php';
require_once '../includes/auth.php';
requireLogin();


$userId = getCurrentUserId();
$cutoffDate = '2026-01-20';

// Load Payment Methods dynamically from credit_accounts + default options
$paymentMethods = ['Bank Account', 'Cash']; // Default options
$creditStmt = $pdo->prepare("SELECT provider_name FROM credit_accounts WHERE user_id = ? ORDER BY provider_name");
$creditStmt->execute([$userId]);
while ($row = $creditStmt->fetch()) {
    $paymentMethods[] = $row['provider_name'];
}

// Fetch Active Loans & EMIs for Linking
$linkableLoans = $pdo->prepare("SELECT id, person_name, type, amount, paid_amount FROM loans WHERE user_id = ? AND status = 'Pending'");
$linkableLoans->execute([$userId]);
$loansList = $linkableLoans->fetchAll();

$linkableEmis = $pdo->prepare("SELECT id, name, emi_amount FROM emis WHERE user_id = ? AND status = 'Active'");
$linkableEmis->execute([$userId]);
$emisList = $linkableEmis->fetchAll();


$categories = [
    'Food', 'Entertainment', 'Shopping', 'Health', 'Education', 'Other',
    'Mobile Recharge - Self', 'Mobile Recharge - Wife', 'Mobile Recharge - Family',
    'Home Internet - Bangalore', 'Home Internet - Home',
    'Electricity - Bangalore', 'Electricity - Home',
    'Transport - Daily/Cabs', 'Transport - Outstation',
    'LPG Gas', 'HomeRent'
];

// Handle POST (Add/Delete)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['delete_id'])) {
        $stmt = $pdo->prepare("DELETE FROM expenses WHERE id = ? AND user_id = ?");
        $stmt->execute([$_POST['delete_id'], $userId]);
        $_SESSION['flash_message'] = "Expense deleted.";
    } else {
        try {
            $date = $_POST['date'];
            $category = $_POST['category'];
            $desc = $_POST['description'];
            $amount = $_POST['amount'];
            $method = $_POST['payment_method'];
            
            // Check for valid amount
            if ($amount <= 0) {
                throw new Exception("Amount must be greater than zero.");
            }

            // Check if linking target is valid (if selected)
            if (!empty($_POST['link_ref'])) {
                 $ref = $_POST['link_ref'];
                 $parts = explode('_', $ref);
                 if (count($parts) !== 2) throw new Exception("Invalid Link Reference.");
                 $type = $parts[0];
                 $id = $parts[1];
                 
                 // Verify ownership and status
                 if ($type === 'LOAN') {
                     $chk = $pdo->prepare("SELECT status FROM loans WHERE id = ? AND user_id = ?");
                     $chk->execute([$id, $userId]);
                     $lSt = $chk->fetchColumn();
                     if (!$lSt || $lSt !== 'Pending') throw new Exception("Cannot link to a settled or invalid loan.");
                 } elseif ($type === 'EMI') {
                     $chk = $pdo->prepare("SELECT status FROM emis WHERE id = ? AND user_id = ?");
                     $chk->execute([$id, $userId]);
                     $eSt = $chk->fetchColumn();
                     if (!$eSt || $eSt !== 'Active') throw new Exception("Cannot link to a completed or invalid EMI.");
                 }
            }

            // Insert Expense
            $stmt = $pdo->prepare("INSERT INTO expenses (user_id, date, category, description, amount, payment_method, converted_to_emi) VALUES (?, ?, ?, ?, ?, ?, ?)");
            $isEmi = (isset($_POST['convert_to_emi']) && $_POST['convert_to_emi'] == 'on') ? 1 : 0;
            
            // Transaction Start (Optional but good for complex logic)
            $pdo->beginTransaction();
            
            $stmt->execute([$userId, $date, $category, $desc, $amount, $method, $isEmi]);
            
            // Handle EMI Conversion (New Plan)
            if ($isEmi) {
                $tenure = $_POST['tenure_months'];
                $interest = $_POST['interest_rate'];
                
                if ($tenure <= 0) throw new Exception("Tenure must be valid.");

                // Calculate EMI
                $p = $amount;
                $r = ($interest / 100) / 12;
                $n = $tenure;
                if ($r > 0) {
                    $emi = ($p * $r * pow(1 + $r, $n)) / (pow(1 + $r, $n) - 1);
                } else {
                    $emi = $p / $n;
                }

                $stmt = $pdo->prepare("INSERT INTO emis (user_id, name, total_amount, interest_rate, tenure_months, emi_amount, start_date, payment_method) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
                $stmt->execute([$userId, $desc, $amount, $interest, $tenure, $emi, $date, $method]);
            } 
            
            // Handle Linking to Existing Loan/EMI (Two-Way Sync)
            elseif (!empty($_POST['link_ref'])) {
                // Re-parse (already validated above)
                $ref = $_POST['link_ref']; 
                $parts = explode('_', $ref);
                $type = $parts[0];
                $id = $parts[1];

                if ($type === 'LOAN') {
                    $stmt = $pdo->prepare("UPDATE loans SET paid_amount = paid_amount + ? WHERE id = ? AND user_id = ?");
                    $stmt->execute([$amount, $id, $userId]);
                    
                    // Check Settlement
                    $chk = $pdo->prepare("SELECT amount, paid_amount FROM loans WHERE id = ?");
                    $chk->execute([$id]);
                    $ln = $chk->fetch();
                    if ($ln && $ln['paid_amount'] >= $ln['amount']) {
                        $pdo->prepare("UPDATE loans SET status = 'Settled', settlement_date = ? WHERE id = ?")->execute([date('Y-m-d'), $id]);
                        $_SESSION['flash_message'] = "Expense added & Linked Loan Settled! 🎉";
                    } else {
                        $_SESSION['flash_message'] = "Expense added & Loan balance updated.";
                    }

                } elseif ($type === 'EMI') {
                    // Increment paid months by 1 (Assuming 1 expense = 1 EMI usually, or we could calculate fraction but strict count is safer for EMI)
                    // Actually, user might pay custom amount. Best constraint is strictly tracking payments.
                    // For EMIs, we track 'paid_months'. 
                    $stmt = $pdo->prepare("UPDATE emis SET paid_months = paid_months + 1 WHERE id = ? AND user_id = ?");
                    $stmt->execute([$id, $userId]);
                    
                    // Check Completion
                     $chk = $pdo->prepare("SELECT paid_months, tenure_months FROM emis WHERE id = ?");
                    $chk->execute([$id]);
                    $em = $chk->fetch();
                    if ($em && $em['paid_months'] >= $em['tenure_months']) {
                        $pdo->prepare("UPDATE emis SET status = 'Completed' WHERE id = ?")->execute([$id]);
                         $_SESSION['flash_message'] = "Expense added & EMI Plan Completed! 🎉";
                    } else {
                        $_SESSION['flash_message'] = "Expense added & EMI Payment recorded.";
                    }
                }
            } 
            else {
                $_SESSION['flash_message'] = "Expense added successfully.";
            }
            
            $pdo->commit();
            
        } catch (Exception $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            $_SESSION['flash_message'] = "Error: " . $e->getMessage();
        }
    }
    header("Location: expenses.php");
    exit;
}

$pageTitle = 'Expenses';
require_once '../includes/header.php';

// Filters
$filterMonth = $_GET['month'] ?? date('Y-m');
$filterCategory = $_GET['category'] ?? '';
$filterMethod = $_GET['method'] ?? '';

// Build Query (Using shifted month logic)
$query = "SELECT * FROM expenses WHERE strftime('%Y-%m', date) = ? AND user_id = ?";
$params = [$filterMonth, $userId];

if ($filterCategory) {
    $query .= " AND category = ?";
    $params[] = $filterCategory;
}
if ($filterMethod) {
    $query .= " AND payment_method = ?";
    $params[] = $filterMethod;
}

$query .= " ORDER BY date DESC, id DESC";

$stmt = $pdo->prepare($query);
$stmt->execute($params);
$expenses = $stmt->fetchAll();

// Calculate Total for view
$totalView = 0;
foreach($expenses as $e) {
    if ($e['date'] >= $cutoffDate) {
        $totalView += $e['amount'];
    }
}
?>

<div class="space-y-6">
    <!-- Success Msg -->

    <!-- Add Expense Form (Collapsible or visible) -->
    <div class="bg-white p-6 rounded-lg shadow">
        <h2 class="text-lg font-bold mb-4">Add Expense</h2>
        <form method="POST" id="expenseForm" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-6 gap-4 items-end">
            <div class="col-span-1">
                <label class="text-xs font-bold text-gray-700">Date</label>
                <input type="date" name="date" required value="<?php echo date('Y-m-d'); ?>" class="w-full border p-2 rounded text-sm">
            </div>
            <div class="col-span-1">
                <label class="text-xs font-bold text-gray-700">Category</label>
                <select name="category" class="w-full border p-2 rounded text-sm">
                    <?php foreach($categories as $c) echo "<option>$c</option>"; ?>
                </select>
            </div>
            <div class="col-span-1 lg:col-span-2">
                <label class="text-xs font-bold text-gray-700">Description</label>
                <input type="text" name="description" id="expDesc" placeholder="Lunch, Uber, etc." class="w-full border p-2 rounded text-sm">
            </div>
            <div class="col-span-1">
                <label class="text-xs font-bold text-gray-700">Amount</label>
                <input type="number" step="0.01" name="amount" id="expAmount" required placeholder="0.00" class="w-full border p-2 rounded text-sm">
            </div>
            <div class="col-span-1">
                <label class="text-xs font-bold text-gray-700">Payment Method</label>
                <select name="payment_method" id="expMethod" class="w-full border p-2 rounded text-sm">
                    <?php foreach($paymentMethods as $m) echo "<option>$m</option>"; ?>
                </select>
            </div>
            
            <div class="col-span-1 md:col-span-2">
                 <label class="text-xs font-bold text-gray-700">Link to Loan / EMI (Optional)</label>
                 <select name="link_ref" class="w-full border p-2 rounded text-sm bg-blue-50/50">
                     <option value="">-- None --</option>
                     <optgroup label="Active Loans">
                         <?php foreach($loansList as $l): ?>
                             <?php $rem = $l['amount'] - ($l['paid_amount']??0); ?>
                             <option value="LOAN_<?php echo $l['id']; ?>">
                                 <?php echo htmlspecialchars($l['person_name']); ?> (Rem: ₹<?php echo number_format($rem); ?>)
                             </option>
                         <?php endforeach; ?>
                     </optgroup>
                     <optgroup label="Active EMI Plans">
                         <?php foreach($emisList as $e): ?>
                             <option value="EMI_<?php echo $e['id']; ?>">
                                 <?php echo htmlspecialchars($e['name']); ?> (EMI: ₹<?php echo number_format($e['emi_amount']); ?>)
                             </option>
                         <?php endforeach; ?>
                     </optgroup>
                 </select>
            </div>

            <!-- EMI Toggle (Only for CC/Later) -->
            <div id="emiToggleContainer" class="col-span-1 md:col-span-2 lg:col-span-6 hidden">
                <div class="flex items-center space-x-2 p-2 bg-brand-50 rounded border border-brand-100">
                    <input type="checkbox" name="convert_to_emi" id="convertToEmi" class="w-4 h-4 text-brand-600">
                    <label for="convertToEmi" class="text-sm font-bold text-brand-700">Convert this to EMI?</label>
                </div>
            </div>

            <!-- Advanced EMI Fields -->
            <div id="expEmiFields" class="col-span-1 md:col-span-2 lg:col-span-6 grid grid-cols-1 md:grid-cols-3 gap-4 p-4 bg-gray-50 rounded-lg hidden">
                <div>
                    <label class="block text-[10px] font-bold text-gray-400 uppercase">Tenure (Months)</label>
                    <input type="number" name="tenure_months" id="expTenure" value="12" class="w-full border p-2 rounded text-sm">
                </div>
                <div>
                    <label class="block text-[10px] font-bold text-gray-400 uppercase">Interest Rate (% p.a)</label>
                    <input type="number" step="0.1" name="interest_rate" id="expInterest" value="15" class="w-full border p-2 rounded text-sm">
                </div>
                <div>
                    <label class="block text-[10px] font-bold text-gray-400 uppercase">Monthly EMI (Calculated)</label>
                    <div id="expEmiCalc" class="text-lg font-bold text-brand-600 pt-1">₹0.00</div>
                </div>
            </div>

            <div class="col-span-1 md:col-span-2 lg:col-span-6">
                <button type="submit" class="w-full bg-red-600 hover:bg-red-700 text-white font-bold py-2 rounded">Add Expense</button>
            </div>
        </form>
    </div>

    <!-- Filters -->
    <div class="flex flex-col md:flex-row justify-between items-center bg-gray-100 p-4 rounded-lg">
        <form method="GET" class="flex flex-wrap gap-4 w-full md:w-auto">
            <input type="month" name="month" value="<?php echo $filterMonth; ?>" class="border p-2 rounded text-sm" onchange="this.form.submit()">
            <select name="category" class="border p-2 rounded text-sm" onchange="this.form.submit()">
                <option value="">All Categories</option>
                <?php foreach($categories as $c) echo "<option ".($filterCategory==$c?'selected':'').">$c</option>"; ?>
            </select>
            <select name="method" class="border p-2 rounded text-sm" onchange="this.form.submit()">
                <option value="">All Methods</option>
                <?php foreach($paymentMethods as $m) echo "<option ".($filterMethod==$m?'selected':'').">$m</option>"; ?>
            </select>
        </form>
        <div class="mt-4 md:mt-0 font-bold text-gray-700">
            Total Shown: <span class="text-red-600">₹<?php echo number_format($totalView, 2); ?></span>
        </div>
    </div>

    <!-- Table -->
    <div class="bg-white shadow rounded-lg overflow-x-auto">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-bold text-gray-500 uppercase">Date</th>
                        <th class="px-6 py-3 text-left text-xs font-bold text-gray-500 uppercase">Description</th>
                        <th class="px-6 py-3 text-left text-xs font-bold text-gray-500 uppercase">Category</th>

                        <th class="px-6 py-3 text-right text-xs font-bold text-gray-500 uppercase">Amount</th>
                        <th class="px-6 py-3 text-right text-xs font-bold text-gray-500 uppercase">Action</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200">
                    <?php foreach ($expenses as $row): ?>
                    <tr>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                            <?php echo htmlspecialchars($row['date']); ?>
                            <?php if ($row['date'] < $cutoffDate): ?>
                                <div class="text-[8px] text-amber-600 font-bold uppercase">Reference Only</div>
                            <?php endif; ?>
                        </td>
                        <td class="px-6 py-4 text-sm text-gray-900">
                            <?php echo htmlspecialchars($row['description']); ?>
                            <div class="text-xs text-gray-400 mt-1">Paid via <?php echo htmlspecialchars($row['payment_method']); ?></div>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                            <span class="bg-gray-100 text-gray-800 text-xs font-semibold px-2 py-0.5 rounded"><?php echo htmlspecialchars($row['category']); ?></span>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-right text-sm">
                            <span class="font-bold text-gray-900">₹<?php echo number_format($row['amount'], 2); ?></span>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-right text-sm space-x-3">
                            <?php if(strpos(strtolower($row['payment_method']), 'credit') !== false || strpos(strtolower($row['payment_method']), 'later') !== false): ?>
                                <button onclick='openEmiModal(<?php echo json_encode($row); ?>)' 
                                        class="text-brand-600 hover:text-brand-800 text-xs font-bold uppercase tracking-wider">
                                    Convert to EMI
                                </button>
                            <?php endif; ?>
                            
                            <form method="POST" onsubmit="return confirm('Delete?');" class="inline">
                                <input type="hidden" name="delete_id" value="<?php echo $row['id']; ?>">
                                <button type="submit" class="text-red-500 hover:text-red-700 font-bold">&times;</button>
                            </form>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php if(empty($expenses)): ?>
            <p class="p-6 text-center text-gray-500">No expenses found for this selection.</p>
        <?php endif; ?>
    </div>
</div>

<!-- Convert to EMI Modal -->
<div id="convertEmiModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 hidden flex items-center justify-center p-4 z-50">
    <div class="bg-white rounded-xl shadow-xl w-full max-w-md p-6">
        <h2 class="text-xl font-bold text-gray-900 mb-1">Convert to EMI</h2>
        <p class="text-xs text-gray-500 mb-6">Create a repayment plan for this transaction.</p>
        
        <form action="emis.php" method="POST" class="space-y-4">
            <input type="hidden" name="name" id="emi_name">
            <input type="hidden" name="total_amount" id="emi_amount">
            <input type="hidden" name="start_date" id="emi_date">
            <input type="hidden" name="payment_method" id="emi_method">
            <input type="hidden" name="expense_id" id="emi_expense_id">

            <div class="p-3 bg-gray-50 rounded-lg border border-gray-100 mb-4">
                <div class="flex justify-between text-sm">
                    <span class="text-gray-500">Item:</span>
                    <span class="font-bold text-gray-900" id="display_name"></span>
                </div>
                <div class="flex justify-between text-sm mt-1">
                    <span class="text-gray-500">Principal:</span>
                    <span class="font-bold text-brand-600" id="display_amount"></span>
                </div>
            </div>

            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs font-bold text-gray-700 uppercase mb-1">Tenure (Months)</label>
                    <select name="tenure_months" class="w-full border p-2 rounded-lg text-sm bg-white">
                        <option value="3">3 Months</option>
                        <option value="6">6 Months</option>
                        <option value="9">9 Months</option>
                        <option value="12" selected>12 Months</option>
                        <option value="18">18 Months</option>
                        <option value="24">24 Months</option>
                        <option value="36">36 Months</option>
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-bold text-gray-700 uppercase mb-1">Interest Rate (% p.a)</label>
                    <input type="number" step="0.1" name="interest_rate" value="15" required 
                           class="w-full border p-2 rounded-lg text-sm outline-none focus:ring-2 focus:ring-brand-500">
                </div>
            </div>
            
            <div class="flex justify-end space-x-3 pt-6">
                <button type="button" onclick="document.getElementById('convertEmiModal').classList.add('hidden')" 
                        class="px-4 py-2 text-sm text-gray-600 hover:text-gray-800 font-medium">Cancel</button>
                <button type="submit" class="px-6 py-2 bg-brand-600 text-white rounded-lg text-sm font-bold hover:bg-brand-700 shadow-md transition">
                    Create EMI Plan
                </button>
            </div>
        </form>
    </div>
</div>

<script>
const expMethod = document.getElementById('expMethod');
const emiToggleContainer = document.getElementById('emiToggleContainer');
const expEmiFields = document.getElementById('expEmiFields');
const convertToEmi = document.getElementById('convertToEmi');

function updateEmiVisibility() {
    const method = expMethod.value.toLowerCase();
    // Show EMI option for all payment methods EXCEPT Cash
    const canConvertToEmi = !method.includes('cash');
    
    if (canConvertToEmi) {
        emiToggleContainer.classList.remove('hidden');
        if (convertToEmi.checked) {
            expEmiFields.classList.remove('hidden');
        } else {
            expEmiFields.classList.add('hidden');
        }
    } else {
        emiToggleContainer.classList.add('hidden');
        expEmiFields.classList.add('hidden');
        convertToEmi.checked = false;
    }
}

function calculateExpEmi() {
    const p = parseFloat(document.getElementById('expAmount').value) || 0;
    const r_annual = parseFloat(document.getElementById('expInterest').value) || 0;
    const n = parseInt(document.getElementById('expTenure').value) || 0;
    
    if (p > 0 && n > 0) {
        const r = (r_annual / 100) / 12;
        let emi = 0;
        if (r > 0) {
            emi = (p * r * Math.pow(1 + r, n)) / (Math.pow(1 + r, n) - 1);
        } else {
            emi = p / n;
        }
        document.getElementById('expEmiCalc').innerText = '₹' + emi.toLocaleString(undefined, {minimumFractionDigits: 2, maximumFractionDigits: 2});
    } else {
        document.getElementById('expEmiCalc').innerText = '₹0.00';
    }
}

expMethod.addEventListener('change', updateEmiVisibility);
convertToEmi.addEventListener('change', updateEmiVisibility);
['expAmount', 'expTenure', 'expInterest'].forEach(id => {
    document.getElementById(id).addEventListener('input', calculateExpEmi);
});

// Initial Visibility
updateEmiVisibility();

function openEmiModal(expense) {
    document.getElementById('emi_name').value = expense.description;
    document.getElementById('emi_amount').value = expense.amount;
    document.getElementById('emi_date').value = expense.date;
    document.getElementById('emi_method').value = expense.payment_method;
    document.getElementById('emi_expense_id').value = expense.id;
    
    document.getElementById('display_name').innerText = expense.description;
    document.getElementById('display_amount').innerText = '₹' + parseFloat(expense.amount).toLocaleString();
    
    document.getElementById('convertEmiModal').classList.remove('hidden');
}
</script>

<?php require_once '../includes/footer.php'; ?>
