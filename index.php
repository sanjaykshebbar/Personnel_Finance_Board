<?php
require_once 'config/database.php';
require_once 'includes/auth.php'; // Auth Middleware
requireLogin(); // Enforce Login function

$pageTitle = 'Dashboard';
require_once 'includes/header.php';

// --- Logic ---
$userId = getCurrentUserId();
$currentMonth = $_GET['month'] ?? date('Y-m');
// --- Constants for accounting ---
$startMonth = substr(SYSTEM_START_DATE, 0, 7); // "2026-02"

// 1. Total Income (Current Month)
// Fixed: Income is now attributed based on 'month' (User selected Budget Month)
$stmt = $pdo->prepare("SELECT SUM(total_income) as total FROM income WHERE month = ? AND user_id = ?");
$stmt->execute([$currentMonth, $userId]);
$incomeCurrent = $stmt->fetch()['total'] ?? 0;

// 2. Total Expenses (Current Month - Shifted)
// We exclude 'Investment' category here because we sum it separately in investmentsCurrent for the card, 
// OR we sum everything in expenses and don't sum investments separately. 
// Let's sum EVERYTHING in expenses and then show specific cards.
// FIXED: Exclude expenses that are 'converted_to_emi' (1) to avoid double counting (Initial + EMI installments)
$stmt = $pdo->prepare("SELECT SUM(amount) as total FROM expenses WHERE strftime('%Y-%m', date) = ? AND user_id = ? AND converted_to_emi = 0");
$stmt->execute([$currentMonth, $userId]);
$expensesCurrent = $stmt->fetch()['total'] ?? 0;

// 3. Total Investments (Current Month)
// To avoid double counting in Balance calculation, we keep this for the card view ONLY.
$stmt = $pdo->prepare("SELECT SUM(amount) as total FROM investments WHERE strftime('%Y-%m', due_date) = ? AND user_id = ?");
$stmt->execute([$currentMonth, $userId]);
$investmentsCurrent = $stmt->fetch()['total'] ?? 0;

// 4. Carry Forward (Previous Month Remaining Balance)
if ($currentMonth <= $startMonth) {
    $carryForward = 0;
} else {
    $stmt = $pdo->prepare("SELECT SUM(total_income) FROM income WHERE month < ? AND user_id = ?");
    $stmt->execute([$currentMonth, $userId]);
    $pastIncome = $stmt->fetchColumn() ?? 0;

    // FIXED: Carry forward should calculate 'Cash/Bank Position', so we exclude credit card spending (Liabilities).
    // This assumes Credit Card Bill Payments are recorded as separate expenses/transfers from Bank.
    $stmt = $pdo->prepare("
        SELECT SUM(amount) 
        FROM expenses 
        WHERE strftime('%Y-%m', date) < ? 
        AND user_id = ? 
        AND converted_to_emi = 0
        AND TRIM(LOWER(payment_method)) NOT IN (SELECT TRIM(LOWER(provider_name)) FROM credit_accounts WHERE user_id = ?)
    ");
    $stmt->execute([$currentMonth, $userId, $userId]);
    $pastExpenses = $stmt->fetchColumn() ?? 0;

    $carryForward = $pastIncome - $pastExpenses;
}

// 5. Total Balance Available (This Month)
$totalAvailable = $incomeCurrent + $carryForward;

// 6. Current Month EMIs - KEEPING for Display/Analytics if needed, but NOT for Balance Calculation
$stmt = $pdo->prepare("SELECT SUM(emi_amount) FROM emis WHERE status = 'Active' AND user_id = ? AND start_date <= ?");
$stmt->execute([$userId, $currentMonth . "-31"]);
$emisCurrent = $stmt->fetchColumn() ?? 0;

// 7. Remaining Savings (This Month)
// User Request: Remaining Balance = Generated Income + Carry Forward
$remainingSavings = $incomeCurrent + $carryForward;

// Asset Expenses calculation for charts/analytics
$stmt = $pdo->prepare("
    SELECT SUM(amount) as total 
    FROM expenses 
    WHERE strftime('%Y-%m', date) = ? 
    AND user_id = ? 
    AND converted_to_emi = 0
    AND TRIM(LOWER(payment_method)) NOT IN (SELECT TRIM(LOWER(provider_name)) FROM credit_accounts WHERE user_id = ?)
");
$stmt->execute([$currentMonth, $userId, $userId]);
$assetExpensesCurrent = $stmt->fetch()['total'] ?? 0;

$remainingSavings = $totalAvailable - $assetExpensesCurrent;

// 7. Spending by Category (Current Month)
$catStmt = $pdo->prepare("
    SELECT category, SUM(amount) as total 
    FROM expenses 
    WHERE strftime('%Y-%m', date) = ? 
    AND user_id = ? 
    AND converted_to_emi = 0
    AND category != 'Credit Card Bill'
    GROUP BY category 
    ORDER BY total DESC
");
$catStmt->execute([$currentMonth, $userId]);
$categorySpending = $catStmt->fetchAll();

$chartLabels = [];
$chartValues = [];
foreach($categorySpending as $c) {
    $chartLabels[] = $c['category'];
    $chartValues[] = $c['total'];
}


// 6. Credit Utilization Calculation (Dynamic Formula)
// Formula: Debt = InitialBase + Expenses - Payments + EMI_Outstanding
$creditStmt = $pdo->prepare("
    SELECT ca.*, 
    (SELECT IFNULL(SUM(amount), 0) FROM expenses WHERE TRIM(LOWER(payment_method)) = TRIM(LOWER(ca.provider_name)) AND converted_to_emi = 0 AND user_id = ca.user_id AND date >= '" . SYSTEM_START_DATE . "') as one_time_expenses,
    (SELECT IFNULL(SUM(amount), 0) FROM expenses WHERE category = 'Credit Card Bill' AND TRIM(LOWER(target_account)) = TRIM(LOWER(ca.provider_name)) AND user_id = ca.user_id AND date >= '" . SYSTEM_START_DATE . "') as bill_payments,
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
// Total Expenses for Insight (includes CC spends, excludes CC bill payments to avoid double counting)
$stmt = $pdo->prepare("SELECT SUM(amount) FROM expenses WHERE strftime('%Y-%m', date) = ? AND user_id = ? AND category != 'Credit Card Bill'");
$stmt->execute([$currentMonth, $userId]);
$totalInsightExpenses = $stmt->fetchColumn() ?? 0;

$summaryExpense = $totalInsightExpenses + $emisCurrent;
$summarySavings = max(0, $summaryIncome - $summaryExpense);

// 8. Loan Analytics
// 8.1 Borrowed (Liabilities)
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

// 8.2 Lent (Receivables)
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
        // FIXED: Use 'paid_amount' for live updates
        $totalLentReceived += ($l['paid_amount'] ?? 0);
    }
}
$lentRemaining = max(0, $totalLentPrincipal - $totalLentReceived);

// 9. Savings Breakdown (Total accumulated across all investments)
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
        
        <!-- Total Income -->
        <div class="bg-white dark:bg-gray-800 rounded-xl shadow p-6 border border-gray-100 dark:border-gray-700 transition-colors">
            <div class="flex items-center justify-between mb-4">
                <h3 class="text-sm font-medium text-gray-500 dark:text-gray-400">Gen. Income (<?php echo date('M Y', strtotime($currentMonth."-01")); ?>)</h3>
                <span class="p-2 bg-green-50 dark:bg-green-900/30 text-green-600 dark:text-green-400 rounded-full text-sm">💰</span>
            </div>
            <p class="text-2xl font-bold text-gray-900 dark:text-white">₹<?php echo number_format($incomeCurrent, 2); ?></p>
        </div>

        <!-- Carry Forward -->
        <div class="bg-white dark:bg-gray-800 rounded-xl shadow p-6 border border-gray-100 dark:border-gray-700 transition-colors">
            <div class="flex items-center justify-between mb-4">
                <h3 class="text-sm font-medium text-gray-500 dark:text-gray-400">Carry Forward</h3>
                <span class="p-2 bg-blue-50 dark:bg-blue-900/30 text-blue-600 dark:text-blue-400 rounded-full text-sm">↪️</span>
            </div>
            <p class="text-2xl font-bold <?php echo $carryForward < 0 ? 'text-red-600 dark:text-red-400' : 'text-gray-900 dark:text-white'; ?>">₹<?php echo number_format($carryForward, 2); ?></p>
        </div>

        <!-- Expenses -->
        <div class="bg-white dark:bg-gray-800 rounded-xl shadow p-6 border border-gray-100 dark:border-gray-700 transition-colors">
            <div class="flex items-center justify-between mb-4">
                <h3 class="text-sm font-medium text-gray-500 dark:text-gray-400">Expenses (<?php echo date('M Y', strtotime($currentMonth."-01")); ?>)</h3>
                <span class="p-2 bg-red-50 dark:bg-red-900/30 text-red-600 dark:text-red-400 rounded-full text-sm">💸</span>
            </div>
            <p class="text-2xl font-bold text-gray-900 dark:text-white">₹<?php echo number_format($expensesCurrent, 2); ?></p>
        </div>

        <!-- Remaining -->
        <div class="bg-white dark:bg-gray-800 rounded-xl shadow p-6 border border-gray-100 dark:border-gray-700 transition-colors">
            <div class="flex items-center justify-between mb-4">
                <h3 class="text-sm font-medium text-gray-500 dark:text-gray-400">Remaining Balance</h3>
                <span class="p-2 bg-indigo-50 dark:bg-indigo-900/30 text-indigo-600 dark:text-indigo-400 rounded-full text-sm">🏦</span>
            </div>
            <p class="text-2xl font-bold <?php echo $remainingSavings >= 0 ? 'text-gray-900 dark:text-white' : 'text-red-600 dark:text-red-400'; ?>">
                ₹<?php echo number_format($remainingSavings, 2); ?>
            </p>
        </div>
    </div>

    <!-- Charts & Analytics -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-2 xl:grid-cols-4 gap-6">
        <!-- Financial Summary Pie Chart -->
        <div class="bg-white dark:bg-gray-800 rounded-xl shadow border border-gray-100 dark:border-gray-700 p-6 flex flex-col items-center transition-colors">
            <h3 class="text-sm font-bold text-gray-500 dark:text-gray-400 uppercase tracking-wider mb-4 w-full text-center">Financial Summary</h3>
            <div class="h-48 w-full relative">
                <canvas id="summaryChart"></canvas>
            </div>
            <div class="mt-4 grid grid-cols-2 gap-4 w-full text-[10px] font-bold text-gray-400 uppercase">
                <div class="flex items-center"><span class="w-2 h-2 rounded-full bg-emerald-500 mr-2"></span> Inc: ₹<?php echo number_format($summaryIncome/1000, 1); ?>k</div>
                <div class="flex items-center"><span class="w-2 h-2 rounded-full bg-red-500 mr-2"></span> Exp: ₹<?php echo number_format($summaryExpense/1000, 1); ?>k</div>
            </div>
        </div>

        <!-- Category Pie Chart -->
        <div class="bg-white dark:bg-gray-800 rounded-xl shadow border border-gray-100 dark:border-gray-700 p-6 flex flex-col items-center transition-colors">
            <h3 class="text-sm font-bold text-gray-500 dark:text-gray-400 uppercase tracking-wider mb-4 w-full text-center">Category Spending</h3>
            <?php if(empty($chartValues)): ?>
                <div class="h-48 flex items-center justify-center text-gray-400 italic text-xs">No data.</div>
            <?php else: ?>
                <div class="h-48 w-full">
                    <canvas id="categoryChart"></canvas>
                </div>
            <?php endif; ?>
        </div>

        <!-- Debt vs Assets (Loans) -->
        <div class="bg-white dark:bg-gray-800 rounded-xl shadow border border-gray-100 dark:border-gray-700 p-6 flex flex-col items-center transition-colors">
            <h3 class="text-sm font-bold text-gray-500 dark:text-gray-400 uppercase tracking-wider mb-4 w-full text-center">Borrowed vs Lent</h3>
            <?php if($totalBorrowedPrincipal <= 0 && $totalLentPrincipal <= 0): ?>
                <div class="h-48 flex items-center justify-center text-gray-400 italic text-xs">No active loans.</div>
            <?php else: ?>
                <div class="h-48 w-full relative">
                    <canvas id="loanCompareChart"></canvas>
                </div>
                <div class="mt-4 grid grid-cols-2 gap-4 w-full text-[10px] font-bold text-gray-400 uppercase">
                    <div class="flex items-center"><span class="w-2 h-2 rounded-full bg-red-500 mr-2"></span> Debt: ₹<?php echo number_format($borrowedRemaining/1000, 1); ?>k</div>
                    <div class="flex items-center"><span class="w-2 h-2 rounded-full bg-blue-500 mr-2"></span> Lent: ₹<?php echo number_format($lentRemaining/1000, 1); ?>k</div>
                </div>
            <?php endif; ?>
        </div>

        <!-- Loan Progress -->
        <div class="bg-white dark:bg-gray-800 rounded-xl shadow border border-gray-100 dark:border-gray-700 p-6 flex flex-col items-center transition-colors">
            <h3 class="text-sm font-bold text-gray-500 dark:text-gray-400 uppercase tracking-wider mb-4 w-full text-center">Debt Repayment</h3>
            <?php if($totalBorrowedPrincipal <= 0): ?>
                <div class="h-48 flex items-center justify-center text-gray-400 italic text-xs">No debts.</div>
            <?php else: ?>
                <div class="h-48 w-full relative">
                    <canvas id="loanChart"></canvas>
                </div>
                <div class="mt-4 flex justify-between w-full text-[10px] font-bold text-gray-400 uppercase">
                    <span>Paid: <?php echo round(($totalBorrowedPaid/$totalBorrowedPrincipal)*100); ?>%</span>
                    <span>₹<?php echo number_format($borrowedRemaining/1000, 1); ?>k left</span>
                </div>
            <?php endif; ?>
        </div>

        <!-- Total Savings Breakdown Chart -->
        <div class="bg-white dark:bg-gray-800 rounded-xl shadow border border-gray-100 dark:border-gray-700 p-6 flex flex-col items-center transition-colors">
            <h3 class="text-sm font-bold text-gray-500 dark:text-gray-400 uppercase tracking-wider mb-4 w-full text-center">Total Savings Breakdown</h3>
            <?php if(empty($savingsValues)): ?>
                <div class="h-48 flex items-center justify-center text-gray-400 italic text-xs">No savings data.</div>
            <?php else: ?>
                <div class="h-48 w-full">
                    <canvas id="savingsBreakdownChart"></canvas>
                </div>
                <div class="mt-4 flex flex-col items-center w-full text-[10px] font-bold text-gray-400 uppercase">
                    <span>Total Portfolio: ₹<?php echo number_format(array_sum($savingsValues)); ?></span>
                </div>
            <?php endif; ?>
        </div>

        <!-- Credit Card Usage Chart -->
        <div class="bg-white dark:bg-gray-800 rounded-xl shadow border border-gray-100 dark:border-gray-700 p-6 flex flex-col items-center transition-colors">
            <h3 class="text-sm font-bold text-gray-500 dark:text-gray-400 uppercase tracking-wider mb-4 w-full text-center">Credit Card Usage</h3>
            <?php if(empty($creditCardUsedValues)): ?>
                <div class="h-48 flex items-center justify-center text-gray-400 italic text-xs">No credit card usage.</div>
            <?php else: ?>
                <div class="h-48 w-full relative">
                    <canvas id="creditChart"></canvas>
                </div>
                <div class="mt-4 flex flex-wrap justify-center gap-4 w-full text-[10px] font-bold text-gray-400 uppercase">
                    <?php foreach($creditAccounts as $idx => $acc): 
                        $used = $acc['used_amount'] + $acc['one_time_expenses'] - ($acc['bill_payments'] ?? 0) + $acc['emi_outstanding'];
                        if($used <= 0) continue;
                    ?>
                        <div class="flex items-center">
                            <span class="w-2 h-2 rounded-full mr-2" style="background-color: <?php echo ['#6366f1', '#8b5cf6', '#ec4899', '#ef4444', '#f59e0b', '#10b981', '#3b82f6'][$idx % 7]; ?>"></span>
                            <?php echo htmlspecialchars($acc['provider_name']); ?>: ₹<?php echo number_format($used/1000, 1); ?>k
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Recent Transactions (Expenses) -->
    <div class="bg-white dark:bg-gray-800 rounded-xl shadow border border-gray-100 dark:border-gray-700 overflow-hidden transition-colors">
        <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700 flex justify-between items-center">
            <h3 class="text-lg font-bold text-gray-900 dark:text-white">Recent Expenses</h3>
            <a href="pages/expenses.php" class="text-sm text-brand-600 hover:text-brand-800 dark:text-brand-400">View All &rarr;</a>
        </div>
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                <thead class="bg-gray-50 dark:bg-gray-900/50">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Date</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Description</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Category</th>
                        <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Amount</th>
                    </tr>
                </thead>
                <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                    <?php
                    $recStmt = $pdo->prepare("SELECT * FROM expenses WHERE user_id = ? AND strftime('%Y-%m', date) = ? ORDER BY date DESC LIMIT 5");
                    $recStmt->execute([$userId, $currentMonth]);
                    while ($row = $recStmt->fetch()):
                    ?>
                    <tr class="hover:bg-gray-25 dark:hover:bg-gray-900/30 transition-colors">
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">
                            <?php echo htmlspecialchars($row['date']); ?>
                            <?php if ($row['date'] < SYSTEM_START_DATE): ?>
                                <div class="text-[8px] text-amber-600 font-bold uppercase">Pre-Active</div>
                            <?php endif; ?>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 dark:text-white"><?php echo htmlspecialchars($row['description']); ?></td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                            <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-gray-100 dark:bg-gray-700 text-gray-800 dark:text-gray-200">
                                <?php echo htmlspecialchars($row['category']); ?>
                            </span>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-right font-medium text-gray-900 dark:text-white">
                            ₹<?php echo number_format($row['amount'], 2); ?>
                        </td>
                    </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
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
            borderWidth: 2,
            borderColor: chartBorderColor
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        cutout: '70%',
        plugins: {
            legend: {
                position: 'bottom',
                labels: { usePointStyle: true, color: chartLegendColor, font: { size: 9 } }
            }
        }
    }
});

<?php if(!empty($chartValues)): ?>
// 2. Category Chart
new Chart(document.getElementById('categoryChart'), {
    type: 'pie',
    data: {
        labels: <?php echo json_encode($chartLabels); ?>,
        datasets: [{
            data: <?php echo json_encode($chartValues); ?>,
            backgroundColor: ['#6366f1', '#10b981', '#f59e0b', '#ef4444', '#8b5cf6', '#ec4899', '#06b6d4'],
            borderWidth: 2,
            borderColor: chartBorderColor
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: {
                position: 'bottom',
                labels: { usePointStyle: true, color: chartLegendColor, font: { size: 9 } }
            }
        }
    }
});
<?php endif; ?>

<?php if($totalBorrowedPrincipal > 0 || $totalLentPrincipal > 0): ?>
// 3. Comparison Chart (Borrowed vs Lent)
new Chart(document.getElementById('loanCompareChart'), {
    type: 'bar',
    data: {
        labels: ['Liabilities', 'Receivables'],
        datasets: [{
            label: 'Amount (₹)',
            data: [<?php echo $borrowedRemaining; ?>, <?php echo $lentRemaining; ?>],
            backgroundColor: ['#ef4444', '#3b82f6'],
            borderRadius: 6
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: { display: false }
        },
        scales: {
            y: { beginAtZero: true, ticks: { display: false }, grid: { display: false } },
            x: { grid: { display: false }, ticks: { color: chartLegendColor } }
        }
    }
});
<?php endif; ?>

<?php if($totalBorrowedPrincipal > 0): ?>
// 4. Loan Progress Chart
new Chart(document.getElementById('loanChart'), {
    type: 'doughnut',
    data: {
        labels: ['Paid', 'Remaining'],
        datasets: [{
            data: [<?php echo $totalBorrowedPaid; ?>, <?php echo $borrowedRemaining; ?>],
            backgroundColor: ['#4f46e5', '#e5e7eb'],
            borderWidth: 2,
            borderColor: chartBorderColor
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        cutout: '60%',
        plugins: {
            legend: {
                position: 'bottom',
                labels: { usePointStyle: true, color: chartLegendColor, font: { size: 9 } }
            }
        }
    }
});
<?php endif; ?>

<?php if(!empty($savingsValues)): ?>
// 5. Savings Breakdown Chart
new Chart(document.getElementById('savingsBreakdownChart'), {
    type: 'pie',
    data: {
        labels: <?php echo json_encode($savingsLabels); ?>,
        datasets: [{
            data: <?php echo json_encode($savingsValues); ?>,
            backgroundColor: ['#6366f1', '#10b981', '#f59e0b', '#ec4899', '#06b6d4', '#8b5cf6', '#f43f5e'],
            borderWidth: 2,
            borderColor: chartBorderColor
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: {
                position: 'bottom',
                labels: { usePointStyle: true, color: chartLegendColor, font: { size: 9 } }
            },
            tooltip: {
                callbacks: {
                    label: function(context) {
                        const fullNames = <?php echo json_encode($savingsFullNames); ?>;
                        const label = fullNames[context.dataIndex] || context.label;
                        const value = context.parsed;
                        const total = context.dataset.data.reduce((a, b) => a + b, 0);
                        const percentage = ((value / total) * 100).toFixed(1);
                        return `${label}: ₹${value.toLocaleString()} (${percentage}%)`;
                    }
                }
            }
        }
    }
});
<?php endif; ?>

<?php if(!empty($creditCardUsedValues)): ?>
// 6. Credit Card Usage Chart
new Chart(document.getElementById('creditChart'), {
    type: 'doughnut',
    data: {
        labels: <?php echo json_encode($creditCardLabels); ?>,
        datasets: [{
            data: <?php echo json_encode($creditCardUsedValues); ?>,
            backgroundColor: [
                '#6366f1', '#8b5cf6', '#ec4899', '#ef4444', '#f59e0b', '#10b981', '#3b82f6'
            ],
            borderWidth: 2,
            borderColor: chartBorderColor
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        cutout: '65%',
        plugins: {
            legend: {
                position: 'bottom',
                labels: { usePointStyle: true, color: chartLegendColor, font: { size: 9 } }
            },
            tooltip: {
                callbacks: {
                    label: function(context) {
                        return `${context.label}: ₹${context.raw.toLocaleString()}`;
                    }
                }
            }
        }
    }
});
<?php endif; ?>
</script>

<?php require_once 'includes/footer.php'; ?>
