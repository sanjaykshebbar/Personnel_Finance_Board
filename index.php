<?php
require_once 'config/database.php';
require_once 'includes/auth.php';
requireLogin();

$userId = getCurrentUserId();
$currentMonth = $_GET['month'] ?? date('Y-m');

// 1. Current Month Expenses (Asset side - Bank/Cash only for cash flow charts)
$stmt = $pdo->prepare("
    SELECT IFNULL(SUM(amount), 0) FROM expenses 
    WHERE user_id = ? AND strftime('%Y-%m', date) = ?
");
$stmt->execute([$userId, $currentMonth]);
$expensesCurrent = $stmt->fetchColumn();

// 1.1 Calculate current month "Asset-only" expenses (Cash flow)
$assetStmt = $pdo->prepare("
    SELECT IFNULL(SUM(amount), 0) FROM expenses 
    WHERE user_id = ? 
    AND strftime('%Y-%m', date) = ?
    AND TRIM(LOWER(payment_method)) NOT IN (SELECT TRIM(LOWER(provider_name)) FROM credit_accounts WHERE user_id = ?)
");
$assetStmt->execute([$userId, $currentMonth, $userId]);
$assetExpensesCurrent = $assetStmt->fetchColumn();

// 2. Current Month Income
$stmt = $pdo->prepare("SELECT SUM(total_income) FROM income WHERE user_id = ? AND month = ?");
$stmt->execute([$userId, $currentMonth]);
$incomeCurrent = $stmt->fetchColumn() ?: 0;

// 3. Carry Forward (Historical Surplus up to previous month)
// FIXED: Carry forward should only consider Asset-based cash flow (Income - AssetExpenses)
$stmt = $pdo->prepare("SELECT SUM(total_income) FROM income WHERE user_id = ? AND month < ?");
$stmt->execute([$userId, $currentMonth]);
$historicalIncome = $stmt->fetchColumn() ?: 0;

$stmt = $pdo->prepare("
    SELECT IFNULL(SUM(amount), 0) FROM expenses 
    WHERE user_id = ? 
    AND strftime('%Y-%m', date) < ?
    AND TRIM(LOWER(payment_method)) NOT IN (SELECT TRIM(LOWER(provider_name)) FROM credit_accounts WHERE user_id = ?)
");
$stmt->execute([$userId, $currentMonth, $userId]);
$historicalAssetExpenses = $stmt->fetchColumn();

$carryForward = $historicalIncome - $historicalAssetExpenses;

// 4. Remaining Balance (Cash in Hand)
// Current Income + Carry Forward - Current Asset Expenses
$remainingSavings = $incomeCurrent + $carryForward - $assetExpensesCurrent;

// 5. Category Breakdown (Current Month)
$stmt = $pdo->prepare("SELECT category, SUM(amount) as total FROM expenses WHERE user_id = ? AND strftime('%Y-%m', date) = ? GROUP BY category");
$stmt->execute([$userId, $currentMonth]);
$categories = $stmt->fetchAll();

$chartLabels = [];
$chartValues = [];
foreach ($categories as $c) {
    if ($c['total'] <= 0) continue;
    $chartLabels[] = $c['category'];
    $chartValues[] = $c['total'];
}


// 6. Credit Utilization Calculation (Dynamic Formula)
// Formula: Debt = InitialBase + Expenses - Payments + EMI_Outstanding
$creditStmt = $pdo->prepare("
    SELECT ca.*, 
    (SELECT IFNULL(SUM(amount), 0) FROM expenses WHERE TRIM(LOWER(payment_method)) = TRIM(LOWER(ca.provider_name)) AND converted_to_emi = 0 AND user_id = ca.user_id AND date >= '" . SYSTEM_START_DATE . "') as one_time_expenses,
    (SELECT IFNULL(SUM(amount), 0) FROM expenses WHERE category = 'Credit Card Bill' AND TRIM(LOWER(target_account)) = TRIM(LOWER(ca.provider_name)) AND user_id = ca.user_id) as bill_payments,
    (SELECT IFNULL(SUM(total_amount - (emi_amount * paid_months)), 0) FROM emis WHERE TRIM(LOWER(payment_method)) = TRIM(LOWER(ca.provider_name)) AND user_id = ca.user_id AND status = 'Active') as emi_outstanding
    FROM credit_accounts ca 
    WHERE ca.user_id = ?
");
$creditStmt->execute([$userId]);
$creditAccounts = $creditStmt->fetchAll();

$totalCreditLimit = 0;
$totalCreditUsed = 0;
$creditCardLabels = [];
$creditCardUsedValues = [];

foreach ($creditAccounts as $acc) {
    $totalCreditLimit += $acc['credit_limit'];
    // Used = Base + Expenses - Payments + EMI
    $usedForThisCard = $acc['used_amount'] + $acc['one_time_expenses'] - ($acc['bill_payments'] ?? 0) + $acc['emi_outstanding'];
    $totalCreditUsed += $usedForThisCard;
    
    if ($usedForThisCard > 0) {
        $creditCardLabels[] = $acc['provider_name'];
        $creditCardUsedValues[] = $usedForThisCard;
    }
}

$creditUtilization = ($totalCreditLimit > 0) ? round(($totalCreditUsed / $totalCreditLimit) * 100, 2) : 0;

// Dashboard Summary Data
$summaryIncome = $incomeCurrent;
$summaryExpense = $assetExpensesCurrent; 
$summarySavings = max(0, $summaryIncome - $summaryExpense);

// 8. Loan Analytics
$loanStmt = $pdo->prepare("SELECT amount, emi_amount, paid_months, tenure_months, status, paid_amount FROM loans WHERE user_id = ? AND type = 'Borrowed'");
$loanStmt->execute([$userId]);
$borrowedLoans = $loanStmt->fetchAll();

$totalBorrowedPrincipal = 0;
$totalBorrowedPaid = 0;
foreach($borrowedLoans as $l) {
    $totalBorrowedPrincipal += $l['amount'];
    if ($l['status'] === 'Settled') {
        $totalBorrowedPaid += $l['amount'];
    } else {
        $totalBorrowedPaid += $l['paid_amount']; 
    }
}
$borrowedRemaining = max(0, $totalBorrowedPrincipal - $totalBorrowedPaid);

$lentStmt = $pdo->prepare("SELECT amount, status, paid_amount FROM loans WHERE user_id = ? AND type = 'Lent'");
$lentStmt->execute([$userId]);
$lentLoans = $lentStmt->fetchAll();

$totalLentPrincipal = 0;
$totalLentReceived = 0;
foreach($lentLoans as $l) {
    $totalLentPrincipal += $l['amount'];
    if ($l['status'] === 'Settled') {
        $totalLentReceived += $l['amount'];
    } else {
        $totalLentReceived += ($l['paid_amount'] ?? 0);
    }
}
$lentRemaining = max(0, $totalLentPrincipal - $totalLentReceived);

// 9. Savings Breakdown
$savStmt = $pdo->prepare("
    SELECT name, SUM(amount * paid_count) as total 
    FROM investment_plans 
    WHERE user_id = ? 
    GROUP BY name 
    HAVING total > 0
    ORDER BY total DESC
");
$savStmt->execute([$userId]);
$savingsBreakdown = $savStmt->fetchAll();

$savingsLabels = [];
$savingsValues = [];
$savingsFullNames = [];
foreach($savingsBreakdown as $s) {
    $savingsLabels[] = strtoupper(substr($s['name'], 0, 3));
    $savingsValues[] = $s['total'];
    $savingsFullNames[] = $s['name'];
}

$pageTitle = 'Dashboard';
require_once 'includes/header.php';
?>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<div class="space-y-6">
    <!-- Month/Year Selector -->
    <div class="bg-white dark:bg-gray-800 p-4 rounded-xl shadow border border-gray-100 dark:border-gray-700 flex flex-col md:flex-row md:items-center justify-between space-y-4 md:space-y-0 transition-colors">
        <div>
            <div class="flex items-center gap-2">
                <h2 class="text-xl font-bold text-gray-900 dark:text-white">Dashboard Overview</h2>
            </div>
            <p class="text-sm text-gray-500 dark:text-gray-400">Viewing data for <?php echo date('F Y', strtotime($currentMonth."-01")); ?></p>
        </div>
        <form method="GET" class="flex items-center space-x-2">
            <label class="text-sm font-medium text-gray-600 dark:text-gray-400">Switch Month:</label>
            <input type="month" name="month" value="<?php echo $currentMonth; ?>" 
                   onchange="this.form.submit()" 
                   class="border border-gray-300 dark:border-gray-600 dark:bg-gray-900 dark:text-white rounded-lg px-3 py-2 text-sm focus:ring-brand-500 focus:border-brand-500 outline-none transition-shadow">
        </form>
    </div>

    <!-- Stats Grid -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
        <div class="bg-white dark:bg-gray-800 rounded-xl shadow p-6 border border-gray-100 dark:border-gray-700">
            <div class="flex items-center justify-between mb-4">
                <h3 class="text-sm font-medium text-gray-500 dark:text-gray-400">Gen. Income</h3>
                <span class="p-2 bg-green-50 dark:bg-green-900/30 text-green-600 dark:text-green-400 rounded-full text-sm">💰</span>
            </div>
            <p class="text-2xl font-bold text-gray-900 dark:text-white">₹<?php echo number_format($incomeCurrent, 2); ?></p>
        </div>
        <div class="bg-white dark:bg-gray-800 rounded-xl shadow p-6 border border-gray-100 dark:border-gray-700">
            <div class="flex items-center justify-between mb-4">
                <h3 class="text-sm font-medium text-gray-500 dark:text-gray-400">Carry Forward</h3>
                <span class="p-2 bg-blue-50 dark:bg-blue-900/30 text-blue-600 dark:text-blue-400 rounded-full text-sm">↪️</span>
            </div>
            <p class="text-2xl font-bold <?php echo $carryForward < 0 ? 'text-red-600 dark:text-red-400' : 'text-gray-900 dark:text-white'; ?>">₹<?php echo number_format($carryForward, 2); ?></p>
        </div>
        <div class="bg-white dark:bg-gray-800 rounded-xl shadow p-6 border border-gray-100 dark:border-gray-700">
            <div class="flex items-center justify-between mb-4">
                <h3 class="text-sm font-medium text-gray-500 dark:text-gray-400">Expenses</h3>
                <span class="p-2 bg-red-50 dark:bg-red-900/30 text-red-600 dark:text-red-400 rounded-full text-sm">💸</span>
            </div>
            <p class="text-2xl font-bold text-gray-900 dark:text-white">₹<?php echo number_format($expensesCurrent, 2); ?></p>
        </div>
        <div class="bg-white dark:bg-gray-800 rounded-xl shadow p-6 border border-gray-100 dark:border-gray-700">
            <div class="flex items-center justify-between mb-4">
                <h3 class="text-sm font-medium text-gray-500 dark:text-gray-400">Remaining Bal</h3>
                <span class="p-2 bg-indigo-50 dark:bg-indigo-900/30 text-indigo-600 dark:text-indigo-400 rounded-full text-sm">🏦</span>
            </div>
            <p class="text-2xl font-bold <?php echo $remainingSavings >= 0 ? 'text-gray-900 dark:text-white' : 'text-red-600 dark:text-red-400'; ?>">
                ₹<?php echo number_format($remainingSavings, 2); ?>
            </p>
        </div>
    </div>

    <!-- Charts Section -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <!-- Monthly Summary -->
        <div class="bg-white dark:bg-gray-800 rounded-xl shadow border border-gray-100 dark:border-gray-700 p-6">
            <h3 class="text-sm font-bold text-gray-500 dark:text-gray-400 uppercase tracking-wider mb-6">Financial Summary</h3>
            <div class="h-64 relative">
                <canvas id="summaryChart"></canvas>
            </div>
            <div class="mt-8 grid grid-cols-3 gap-4 text-center">
                <div>
                    <div class="text-[10px] font-bold text-gray-400 uppercase mb-1">Income</div>
                    <div class="text-sm font-black text-emerald-600">₹<?php echo number_format($summaryIncome/1000, 1); ?>k</div>
                </div>
                <div>
                    <div class="text-[10px] font-bold text-gray-400 uppercase mb-1">Expense</div>
                    <div class="text-sm font-black text-red-500">₹<?php echo number_format($summaryExpense/1000, 1); ?>k</div>
                </div>
                <div>
                    <div class="text-[10px] font-bold text-gray-400 uppercase mb-1">Savings</div>
                    <div class="text-sm font-black text-indigo-600">₹<?php echo number_format($summarySavings/1000, 1); ?>k</div>
                </div>
            </div>
        </div>

        <!-- Credit Card Summary -->
        <div class="bg-white dark:bg-gray-800 rounded-xl shadow border border-gray-100 dark:border-gray-700 p-6">
            <div class="flex justify-between items-center mb-6">
                <h3 class="text-sm font-bold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Credit Card Usage</h3>
                <span class="text-sm font-black <?php echo $creditUtilization > 85 ? 'text-red-600' : ($creditUtilization > 60 ? 'text-amber-600' : 'text-emerald-600'); ?>">
                    <?php echo $creditUtilization; ?>% Used
                </span>
            </div>
            <?php if(empty($creditCardUsedValues)): ?>
                <div class="h-64 flex items-center justify-center text-gray-400 italic text-xs">No active credit card debt.</div>
            <?php else: ?>
                <div class="h-64 relative">
                    <canvas id="creditChart"></canvas>
                </div>
                <div class="mt-8 flex flex-wrap justify-center gap-4 text-[9px] font-bold text-gray-400 uppercase">
                    <?php foreach($creditAccounts as $idx => $acc): 
                        $used = $acc['used_amount'] + $acc['one_time_expenses'] - ($acc['bill_payments'] ?? 0) + $acc['emi_outstanding'];
                        if($used <= 0) continue;
                    ?>
                        <div class="flex items-center">
                            <span class="w-2 h-2 rounded-full mr-1.5" style="background-color: <?php echo ['#6366f1', '#8b5cf6', '#ec4899', '#ef4444', '#f59e0b', '#10b981', '#3b82f6'][$idx % 7]; ?>"></span>
                            <?php echo htmlspecialchars($acc['provider_name']); ?>: ₹<?php echo number_format($used/1000, 1); ?>k
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Transactions and More -->
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <!-- Recent Expenses -->
        <div class="lg:col-span-2 bg-white dark:bg-gray-800 rounded-xl shadow border border-gray-100 dark:border-gray-700 overflow-hidden">
            <div class="px-6 py-4 border-b border-gray-100 dark:border-gray-700 flex justify-between items-center bg-gray-50/50 dark:bg-gray-900/50">
                <h3 class="text-sm font-black text-gray-900 dark:text-white uppercase tracking-widest">Recent Activity</h3>
                <a href="pages/expenses.php" class="text-[10px] font-bold text-brand-600 hover:text-brand-800 uppercase tracking-wider">All History →</a>
            </div>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-100 dark:divide-gray-700">
                    <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-50 dark:divide-gray-700">
                        <?php
                        $recStmt = $pdo->prepare("SELECT * FROM expenses WHERE user_id = ? ORDER BY date DESC, id DESC LIMIT 6");
                        $recStmt->execute([$userId]);
                        while ($row = $recStmt->fetch()):
                        ?>
                        <tr class="hover:bg-gray-25 dark:hover:bg-gray-800/50 transition">
                            <td class="px-6 py-4 whitespace-nowrap text-xs text-gray-400 font-medium"><?php echo date('d M', strtotime($row['date'])); ?></td>
                            <td class="px-6 py-4 text-sm text-gray-900 dark:text-white font-bold"><?php echo htmlspecialchars($row['description']); ?></td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <span class="px-2 py-0.5 text-[9px] font-black uppercase rounded-md bg-gray-100 dark:bg-gray-700 text-gray-500 dark:text-gray-400 border border-gray-200 dark:border-gray-600">
                                    <?php echo htmlspecialchars($row['category']); ?>
                                </span>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-black text-gray-900 dark:text-white">₹<?php echo number_format($row['amount']); ?></td>
                        </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Loan Analytics -->
        <div class="bg-white dark:bg-gray-800 rounded-xl shadow border border-gray-100 dark:border-gray-700 p-6">
            <h3 class="text-sm font-bold text-gray-500 dark:text-gray-400 uppercase tracking-wider mb-6">Debt Progress</h3>
            <?php if($totalBorrowedPrincipal <= 0): ?>
                <div class="h-48 flex items-center justify-center text-gray-400 italic text-xs">No active debts.</div>
            <?php else: ?>
                <div class="h-48 relative">
                    <canvas id="loanChart"></canvas>
                </div>
                <div class="mt-8 space-y-3">
                    <div class="flex justify-between text-[10px] font-bold uppercase">
                        <span class="text-gray-400">Paid Back</span>
                        <span class="text-emerald-600">₹<?php echo number_format($totalBorrowedPaid); ?></span>
                    </div>
                    <div class="w-full bg-gray-100 dark:bg-gray-700 h-2 rounded-full overflow-hidden">
                        <div class="bg-emerald-500 h-full transition-all duration-500" style="width: <?php echo ($totalBorrowedPaid/$totalBorrowedPrincipal)*100; ?>%"></div>
                    </div>
                    <div class="flex justify-between text-[10px] font-bold uppercase">
                        <span class="text-gray-400">Remaining</span>
                        <span class="text-red-500">₹<?php echo number_format($borrowedRemaining); ?></span>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
const isDark = document.documentElement.classList.contains('dark');
const chartBorderColor = isDark ? '#1f2937' : '#ffffff';
const chartLegendColor = isDark ? '#9ca3af' : '#6b7280';

// 1. Financial Summary Chart
new Chart(document.getElementById('summaryChart'), {
    type: 'doughnut',
    data: {
        labels: ['Income', 'Expenses', 'Savings'],
        datasets: [{
            data: [<?php echo $summaryIncome; ?>, <?php echo $summaryExpense; ?>, <?php echo $summarySavings; ?>],
            backgroundColor: ['#10b981', '#ef4444', '#6366f1'],
            borderWidth: 0,
            hoverOffset: 4
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        cutout: '75%',
        plugins: {
            legend: { display: false }
        }
    }
});

<?php if(!empty($creditCardUsedValues)): ?>
// 2. Credit Card Chart
new Chart(document.getElementById('creditChart'), {
    type: 'pie',
    data: {
        labels: <?php echo json_encode($creditCardLabels); ?>,
        datasets: [{
            data: <?php echo json_encode($creditCardUsedValues); ?>,
            backgroundColor: ['#6366f1', '#8b5cf6', '#ec4899', '#ef4444', '#f59e0b', '#10b981', '#3b82f6'],
            borderWidth: 2,
            borderColor: chartBorderColor
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: { display: false }
        }
    }
});
<?php endif; ?>

<?php if($totalBorrowedPrincipal > 0): ?>
// 3. Loan Chart
new Chart(document.getElementById('loanChart'), {
    type: 'doughnut',
    data: {
        labels: ['Paid', 'Remaining'],
        datasets: [{
            data: [<?php echo $totalBorrowedPaid; ?>, <?php echo $borrowedRemaining; ?>],
            backgroundColor: ['#10b981', '#f3f4f6'],
            borderWidth: 0,
            cutout: '80%'
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: { legend: { display: false } }
    }
});
<?php endif; ?>
</script>

<?php require_once 'includes/header.php'; ?>
