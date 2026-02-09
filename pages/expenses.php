<?php
require_once '../config/database.php';
require_once '../includes/auth.php';
require_once '../config/expense_categories.php'; // Ensure this exists

requireLogin();
$userId = getCurrentUserId();
$pageTitle = 'Manage Expenses';

// --- Handle Form Submissions ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    // DELETE
    if (isset($_POST['delete_id'])) {
        $stmt = $pdo->prepare("DELETE FROM expenses WHERE id = ? AND user_id = ?");
        try {
            $stmt->execute([$_POST['delete_id'], $userId]);
            $_SESSION['flash_message'] = "Expense deleted successfully! 🗑️";
        } catch (PDOException $e) {
            $_SESSION['flash_message'] = "❌ Delete failed: " . $e->getMessage();
        }
        header("Location: expenses.php");
        exit;
    }

    // ADD / UPDATE
    $date = $_POST['date'] ?? date('Y-m-d');
    $category = $_POST['category'] ?? 'Other';
    $description = trim($_POST['description'] ?? '');
    $amount = filter_input(INPUT_POST, 'amount', FILTER_VALIDATE_FLOAT);
    $paymentMethod = $_POST['payment_method'] ?? 'Cash';
    $expenseId = $_POST['id'] ?? null;

    if ($amount === false || $amount <= 0) {
        $_SESSION['flash_message'] = "❌ Invalid amount entered.";
    } else {
        try {
            if ($expenseId) {
                // Update existing
                $stmt = $pdo->prepare("UPDATE expenses SET date=?, category=?, description=?, amount=?, payment_method=? WHERE id=? AND user_id=?");
                $stmt->execute([$date, $category, $description, $amount, $paymentMethod, $expenseId, $userId]);
                $_SESSION['flash_message'] = "Expense updated successfully! ✅";
            } else {
                // Create new
                $stmt = $pdo->prepare("INSERT INTO expenses (user_id, date, category, description, amount, payment_method) VALUES (?, ?, ?, ?, ?, ?)");
                $stmt->execute([$userId, $date, $category, $description, $amount, $paymentMethod]);
                $_SESSION['flash_message'] = "Expense added! 💸";
            }
        } catch (PDOException $e) {
            $_SESSION['flash_message'] = "❌ Database Error: " . $e->getMessage();
        }
    }
    header("Location: expenses.php");
    exit;
}

require_once '../includes/header.php';

// --- Fetch Data ---

// 1. Categories
$categories = [];
if (function_exists('getExpenseCategories')) {
    $categories = getExpenseCategories($userId);
} else {
    // Fallback if config/expense_categories.php isn't fully set up/included
    $categories = [
        'Food & Dining', 'Transportation', 'Shopping', 'Bills & Utilities', 
        'Healthcare', 'Entertainment', 'Groceries', 'Travel', 'Education', 'Other'
    ];
}

// 2. Payment Methods
$paymentMethods = ['Cash', 'UPI', 'Bank Transfer', 'Debit Card'];
try {
    $ccStmt = $pdo->prepare("SELECT provider_name FROM credit_accounts WHERE user_id = ? ORDER BY provider_name");
    $ccStmt->execute([$userId]);
    $creditCards = $ccStmt->fetchAll(PDO::FETCH_COLUMN);
    $allPaymentMethods = array_merge($paymentMethods, $creditCards);
} catch (PDOException $e) {
    // Fallback if table doesn't exist
    $allPaymentMethods = $paymentMethods;
}

// 3. Filter & List Expenses
$filterMonth = $_GET['month'] ?? date('Y-m');
$stmt = $pdo->prepare("
    SELECT * FROM expenses 
    WHERE user_id = ? 
    AND strftime('%Y-%m', date) = ? 
    ORDER BY date DESC, id DESC
");
$stmt->execute([$userId, $filterMonth]);
$expenses = $stmt->fetchAll();

$monthTotal = 0;
foreach ($expenses as $ex) $monthTotal += $ex['amount'];

?>

<div class="max-w-6xl mx-auto space-y-6">

    <!-- Header / Stats -->
    <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 bg-white dark:bg-gray-800 p-6 rounded-2xl shadow-sm border border-gray-100 dark:border-gray-700">
        <div>
            <h1 class="text-2xl font-bold text-gray-900 dark:text-white flex items-center gap-2">
                <span class="p-2 bg-red-100 text-red-600 rounded-lg text-xl">💸</span> 
                Expense Manager
            </h1>
            <p class="text-gray-500 dark:text-gray-400 text-sm mt-1">Track and categorize your spending</p>
        </div>
        
        <div class="flex items-center gap-4">
             <!-- Month Filter -->
             <form method="GET" class="flex items-center gap-2">
                <label class="text-sm font-medium text-gray-700 dark:text-gray-300">Month:</label>
                <input type="month" name="month" value="<?php echo $filterMonth; ?>" 
                       onchange="this.form.submit()"
                       class="rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-900 dark:text-white text-sm focus:ring-brand-500 focus:border-brand-500">
            </form>
            
            <div class="text-right">
                <p class="text-xs text-gray-400 uppercase font-bold tracking-wider">Total <?php echo date('M Y', strtotime($filterMonth)); ?></p>
                <p class="text-2xl font-black text-gray-900 dark:text-white">₹<?php echo number_format($monthTotal, 2); ?></p>
            </div>
        </div>
    </div>

    <!-- Main Content Grid -->
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        
        <!-- Left: Add/Edit Form -->
        <div class="lg:col-span-1">
            <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-lg border border-gray-100 dark:border-gray-700 sticky top-6">
                <div class="p-6 border-b border-gray-100 dark:border-gray-700 flex justify-between items-center">
                    <h2 class="text-lg font-bold text-gray-800 dark:text-gray-200" id="formTitle">Add Expense</h2>
                    <button type="button" onclick="resetForm()" id="cancelEditBtn" class="hidden text-xs text-gray-500 hover:text-red-500 underline">Cancel Edit</button>
                </div>
                
                <form method="POST" action="expenses.php" id="expenseForm" class="p-6 space-y-4">
                    <input type="hidden" name="id" id="expenseId">
                    
                    <!-- Date -->
                    <div>
                        <label class="block text-xs font-bold text-gray-500 uppercase mb-1">Date</label>
                        <input type="date" name="date" id="date" required 
                               value="<?php echo date('Y-m-d'); ?>"
                               class="w-full rounded-xl border-gray-200 dark:border-gray-700 dark:bg-gray-900 dark:text-white focus:ring-2 focus:ring-brand-500 transition-all text-sm">
                    </div>

                    <!-- Amount -->
                    <div>
                        <label class="block text-xs font-bold text-gray-500 uppercase mb-1">Amount</label>
                        <div class="relative">
                            <span class="absolute left-3 top-1/2 -translate-y-1/2 text-gray-400 font-bold">₹</span>
                            <input type="number" step="0.01" name="amount" id="amount" required placeholder="0.00"
                                   class="w-full pl-8 rounded-xl border-gray-200 dark:border-gray-700 dark:bg-gray-900 dark:text-white focus:ring-2 focus:ring-brand-500 transition-all font-bold text-lg">
                        </div>
                    </div>

                    <!-- Description -->
                    <div>
                        <label class="block text-xs font-bold text-gray-500 uppercase mb-1">Description</label>
                        <input type="text" name="description" id="description" placeholder="What did you buy?"
                               class="w-full rounded-xl border-gray-200 dark:border-gray-700 dark:bg-gray-900 dark:text-white focus:ring-2 focus:ring-brand-500 transition-all text-sm">
                    </div>

                    <!-- Category -->
                    <div>
                        <label class="block text-xs font-bold text-gray-500 uppercase mb-1">Category</label>
                        <select name="category" id="category" required 
                                class="w-full rounded-xl border-gray-200 dark:border-gray-700 dark:bg-gray-900 dark:text-white focus:ring-2 focus:ring-brand-500 transition-all text-sm">
                                <option value="" disabled selected>Select Category</option>
                                <?php foreach($categories as $cat): ?>
                                    <option value="<?php echo htmlspecialchars($cat); ?>"><?php echo htmlspecialchars($cat); ?></option>
                                <?php endforeach; ?>
                        </select>
                    </div>

                    <!-- Payment Method -->
                    <div>
                        <label class="block text-xs font-bold text-gray-500 uppercase mb-1">Payment Method</label>
                        <select name="payment_method" id="payment_method" required 
                                class="w-full rounded-xl border-gray-200 dark:border-gray-700 dark:bg-gray-900 dark:text-white focus:ring-2 focus:ring-brand-500 transition-all text-sm">
                                <?php foreach($allPaymentMethods as $pm): ?>
                                    <option value="<?php echo htmlspecialchars($pm); ?>"><?php echo htmlspecialchars($pm); ?></option>
                                <?php endforeach; ?>
                        </select>
                    </div>

                    <button type="submit" id="submitBtn" class="w-full bg-brand-600 hover:bg-brand-700 text-white font-bold py-3 rounded-xl shadow-lg shadow-brand-500/20 transition-all active:scale-95 mt-2">
                        Save Expense 💸
                    </button>
                    
                </form>
            </div>
        </div>

        <!-- Right: Filterable List -->
        <div class="lg:col-span-2 space-y-4">
            
            <!-- Quick Filters (Client Side) -->
            <div class="bg-white dark:bg-gray-800 p-4 rounded-xl border border-gray-100 dark:border-gray-700 flex flex-wrap gap-2">
                <input type="text" id="searchInput" placeholder="Search expenses..." 
                       class="flex-grow min-w-[200px] rounded-lg border-gray-200 dark:border-gray-700 dark:bg-gray-900 dark:text-white text-sm focus:ring-brand-500">
                
                <select id="filterCategory" class="rounded-lg border-gray-200 dark:border-gray-700 dark:bg-gray-900 dark:text-white text-sm focus:ring-brand-500">
                    <option value="all">All Categories</option>
                    <?php foreach($categories as $cat): ?>
                        <option value="<?php echo htmlspecialchars($cat); ?>"><?php echo htmlspecialchars($cat); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <!-- List -->
            <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-sm border border-gray-100 dark:border-gray-700 overflow-hidden">
                <?php if(empty($expenses)): ?>
                    <div class="p-12 text-center text-gray-400">
                        <div class="text-4xl mb-3">📭</div>
                        <p>No expenses found for this month.</p>
                    </div>
                <?php else: ?>
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-100 dark:divide-gray-700" id="expensesTable">
                            <thead class="bg-gray-50 dark:bg-gray-900/50">
                                <tr>
                                    <th class="px-6 py-3 text-left text-xs font-bold text-gray-500 uppercase tracking-wider">Date</th>
                                    <th class="px-6 py-3 text-left text-xs font-bold text-gray-500 uppercase tracking-wider">Details</th>
                                    <th class="px-6 py-3 text-left text-xs font-bold text-gray-500 uppercase tracking-wider">Amount</th>
                                    <th class="px-6 py-3 text-right text-xs font-bold text-gray-500 uppercase tracking-wider">Actions</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                                <?php foreach($expenses as $row): ?>
                                <tr class="hover:bg-gray-50 dark:hover:bg-gray-900/40 transition-colors expense-row" 
                                    data-category="<?php echo htmlspecialchars($row['category']); ?>"
                                    data-search="<?php echo htmlspecialchars(strtolower($row['description'] . ' ' . $row['category'] . ' ' . $row['payment_method'])); ?>">
                                    
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">
                                        <span class="font-medium text-gray-900 dark:text-white block"><?php echo date('d M', strtotime($row['date'])); ?></span>
                                        <span class="text-xs"><?php echo date('D', strtotime($row['date'])); ?></span>
                                    </td>
                                    
                                    <td class="px-6 py-4">
                                        <div class="font-medium text-gray-900 dark:text-white"><?php echo htmlspecialchars($row['description'] ?: 'No Description'); ?></div>
                                        <div class="flex items-center gap-2 mt-1">
                                            <span class="text-xs px-2 py-0.5 rounded-full bg-gray-100 dark:bg-gray-700 text-gray-600 dark:text-gray-300">
                                                <?php echo htmlspecialchars($row['category']); ?>
                                            </span>
                                            <span class="text-xs text-brand-600 dark:text-brand-400">
                                                <?php echo htmlspecialchars($row['payment_method']); ?>
                                            </span>
                                        </div>
                                    </td>
                                    
                                    <td class="px-6 py-4 whitespace-nowrap text-sm font-bold text-gray-900 dark:text-white">
                                        ₹<?php echo number_format($row['amount'], 2); ?>
                                    </td>
                                    
                                    <td class="px-6 py-4 whitespace-nowrap text-right text-sm">
                                        <div class="flex items-center justify-end gap-2">
                                            <button onclick='editExpense(<?php echo json_encode($row); ?>)' 
                                                    class="p-2 text-blue-600 hover:bg-blue-50 dark:hover:bg-blue-900/30 rounded-lg transition-colors" title="Edit">
                                                ✏️
                                            </button>
                                            
                                            <form method="POST" onsubmit="return confirm('Are you sure you want to delete this expense?');" class="inline">
                                                <input type="hidden" name="delete_id" value="<?php echo $row['id']; ?>">
                                                <button type="submit" class="p-2 text-red-600 hover:bg-red-50 dark:hover:bg-red-900/30 rounded-lg transition-colors" title="Delete">
                                                    🗑️
                                                </button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<script>
// Filter Logic
const searchInput = document.getElementById('searchInput');
const filterCategory = document.getElementById('filterCategory');
const rows = document.querySelectorAll('.expense-row');

function filterTable() {
    const search = searchInput.value.toLowerCase();
    const cat = filterCategory.value;
    
    rows.forEach(row => {
        const rowText = row.getAttribute('data-search');
        const rowCat = row.getAttribute('data-category');
        
        const matchSearch = rowText.includes(search);
        const matchCat = (cat === 'all' || rowCat === cat);
        
        if (matchSearch && matchCat) {
            row.style.display = '';
        } else {
            row.style.display = 'none';
        }
    });
}

searchInput.addEventListener('input', filterTable);
filterCategory.addEventListener('change', filterTable);


// Edit Logic
function editExpense(data) {
    document.getElementById('formTitle').innerText = 'Edit Expense';
    document.getElementById('submitBtn').innerHTML = 'Update Expense 🔄';
    document.getElementById('cancelEditBtn').classList.remove('hidden');
    
    document.getElementById('expenseId').value = data.id;
    document.getElementById('date').value = data.date;
    document.getElementById('amount').value = data.amount;
    document.getElementById('description').value = data.description || '';
    document.getElementById('category').value = data.category;
    document.getElementById('payment_method').value = data.payment_method;
    
    // Scroll to form (on mobile)
    document.getElementById('expenseForm').scrollIntoView({ behavior: 'smooth' });
    
    // Highlight form
    const formBox = document.getElementById('expenseForm').parentElement;
    formBox.classList.add('ring-2', 'ring-brand-500');
    setTimeout(() => formBox.classList.remove('ring-2', 'ring-brand-500'), 1000);
}

function resetForm() {
    document.getElementById('formTitle').innerText = 'Add Expense';
    document.getElementById('submitBtn').innerHTML = 'Save Expense 💸';
    document.getElementById('cancelEditBtn').classList.add('hidden');
    
    document.getElementById('expenseForm').reset();
    document.getElementById('expenseId').value = '';
    // Reset date to today
    document.getElementById('date').value = new Date().toISOString().split('T')[0];
}
</script>

<?php require_once '../includes/footer.php'; ?>
