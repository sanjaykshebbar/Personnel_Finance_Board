<?php
require_once '../config/database.php';
require_once '../includes/auth.php';
requireLogin();

$pageTitle = 'Reports & Analytics';
require_once '../includes/header.php';

$userId = getCurrentUserId();
$currentYear = $_GET['year'] ?? date('Y');
$focusMonth = $_GET['month'] ?? date('Y-m');
$cutoffDate = '2026-01-20';
$monthShift = '+5 days';

// Synchronization Logic: Ensure focus month year matches the selected year view
if (substr($focusMonth, 0, 4) !== (string)$currentYear) {
    // If year was explicitly changed via dropdown, update the year part of focus month
    // Otherwise if month was changed via chart, update currentYear to match
    if (isset($_GET['year']) && !isset($_GET['month'])) {
         $focusMonth = $currentYear . "-" . date('m');
    } else {
         $focusMonth = $currentYear . "-" . substr($focusMonth, 5, 2);
    }
}

// --- Combined Monthly Summary for Year (Unified Trend) ---
$trendData = [];
$monthNames = [
    '01'=>'Jan','02'=>'Feb','03'=>'Mar','04'=>'Apr','05'=>'May','06'=>'Jun',
    '07'=>'Jul','08'=>'Aug','09'=>'Sep','10'=>'Oct','11'=>'Nov','12'=>'Dec'
];

$maxCombined = 0;

foreach ($monthNames as $m => $name) {
    $mFull = $currentYear . "-" . $m;
    $mStart = $mFull . "-01";
    $mEnd = $mFull . "-31";
    
    // Fetch Total Expense for month (Shifted & Cutoff)
    // We sum Expenses (which now includes Investments) and EMIs separately.
    $trendStmt = $pdo->prepare("
        SELECT SUM(amount) as total 
        FROM (
            SELECT amount FROM expenses 
            WHERE date(date, ?) LIKE ? AND date >= ? AND user_id = ? AND converted_to_emi = 0
            UNION ALL
            SELECT emi_amount as amount FROM emis
            WHERE user_id = ? AND status = 'Active' 
            AND start_date <= ? AND date(start_date, '+' || tenure_months || ' months') > ?
            AND start_date >= ?
        )
    ");
    $trendStmt->execute([$monthShift, $mFull . '%', $cutoffDate, $userId, $userId, $mEnd, $mStart, $cutoffDate]);
    $mExpense = $trendStmt->fetchColumn() ?? 0;

    // Fetch Total Income for month (Cutoff)
    $incStmt = $pdo->prepare("SELECT SUM(total_income) FROM income WHERE month = ? AND user_id = ? AND month >= '2026-01'");
    $incStmt->execute([$mFull, $userId]);
    $mIncome = $incStmt->fetchColumn() ?? 0;

    $trendData[$name] = [
        'expense' => $mExpense,
        'income' => $mIncome,
        'savings' => max(0, $mIncome - $mExpense),
        'month_key' => $mFull
    ];

    if ($mIncome > $maxCombined) $maxCombined = $mIncome;
    if ($mExpense > $maxCombined) $maxCombined = $mExpense;
}

// --- FOCUS MONTH DRILL-DOWN DATA ---
$fStart = $focusMonth . "-01";
$fEnd = $focusMonth . "-31";

// 1. Transactions In (Income)
$incomeStmt = $pdo->prepare("SELECT * FROM income WHERE month = ? AND user_id = ? AND month >= '2026-01'");
$incomeStmt->execute([$focusMonth, $userId]);
$focusIncomes = $incomeStmt->fetchAll();

// 2. Transactions Out (Expenses, EMIs)
// Note: Investments are already in expenses table as 'Investment' category.
$outStmt = $pdo->prepare("
    SELECT * FROM (
        SELECT 
            CASE WHEN category = 'Investment' THEN 'Investment' ELSE 'Expense' END as type, 
            category, amount, date as tr_date, description FROM expenses 
        WHERE date(date, ?) LIKE ? AND date >= ? AND user_id = ? AND converted_to_emi = 0
        UNION ALL
        SELECT 'EMI/Bill' as type, 'Financial' as category, emi_amount as amount, ? || '-01' as tr_date, name as description FROM emis
        WHERE user_id = ? AND status = 'Active' 
        AND start_date <= ? AND date(start_date, '+' || tenure_months || ' months') > ?
        AND start_date >= ?
    )
    ORDER BY tr_date DESC
");
$outStmt->execute([$monthShift, $focusMonth . '%', $cutoffDate, $userId, $focusMonth, $userId, $fEnd, $fStart, $cutoffDate]);
$focusOutgoings = $outStmt->fetchAll();

$totalIn = array_sum(array_column($focusIncomes, 'total_income'));
$totalOut = array_sum(array_column($focusOutgoings, 'amount'));
$netDrift = $totalIn - $totalOut;

?>

<div class="space-y-6">
    <!-- Analysis Scope Header -->
    <div class="bg-white dark:bg-gray-800 p-8 rounded-3xl shadow-sm border border-gray-100 dark:border-gray-700 transition-colors">
        <div class="flex flex-col lg:flex-row justify-between items-start lg:items-center gap-8">
            <div>
                <h1 class="text-3xl font-black text-gray-900 dark:text-white tracking-tighter">Financial Audit</h1>
                <p class="text-xs text-gray-400 font-bold uppercase tracking-[0.2em] opacity-60 mt-1">Interactive Performance Analytics</p>
            </div>
            
            <form method="GET" class="flex items-center gap-4 bg-gray-50 dark:bg-gray-900/50 p-2 rounded-2xl border border-gray-100 dark:border-gray-800">
                <span class="pl-4 text-[10px] font-black text-gray-400 uppercase tracking-widest leading-none">Fiscal Year</span>
                <select name="year" onchange="this.form.submit()" 
                        class="border-none bg-white dark:bg-gray-800 dark:text-white rounded-xl px-5 py-2 text-sm font-black shadow-sm ring-1 ring-gray-200 dark:ring-gray-700 focus:ring-2 focus:ring-brand-500 outline-none cursor-pointer">
                    <?php 
                    $thisYear = (int)date('Y');
                    for($yr = $thisYear; $yr >= 2015; $yr--) {
                        echo "<option value='$yr' ".($currentYear==$yr?'selected':'').">$yr</option>";
                    }
                    ?>
                </select>
            </form>
        </div>

        <!-- Trajectory Map -->
        <div class="mt-12">
            <div class="flex items-end justify-between h-56 gap-2 px-2">
                <?php foreach($trendData as $name => $data): 
                    $hExp = $maxCombined > 0 ? round(($data['expense'] / $maxCombined) * 100) : 0;
                    $hSav = $maxCombined > 0 ? round(($data['savings'] / $maxCombined) * 100) : 0;
                    $isFocus = $focusMonth === $data['month_key'];
                ?>
                <a href="?year=<?php echo $currentYear; ?>&month=<?php echo $data['month_key']; ?>" 
                   class="flex-1 flex flex-col items-center group relative h-full justify-end transition-all <?php echo $isFocus ? 'opacity-100' : 'opacity-40 hover:opacity-100'; ?>">
                    
                    <div class="w-full h-full flex flex-col justify-end space-y-[1px] relative">
                        <!-- Selection Ring -->
                        <?php if($isFocus): ?>
                            <div class="absolute -inset-x-1 -bottom-2 -top-4 rounded-xl ring-2 ring-brand-500/50 bg-brand-50/10 dark:bg-brand-900/10 z-0"></div>
                        <?php endif; ?>

                        <!-- Tooltip -->
                        <div class="absolute bottom-[<?php echo max($hExp, $hSav + $hExp) + 10; ?>%] opacity-0 group-hover:opacity-100 transition-all bg-gray-900 dark:bg-white text-white dark:text-black text-[9px] font-black px-3 py-2 rounded-lg shadow-2xl z-20 whitespace-nowrap mb-2 transform -translate-y-2 group-hover:translate-y-0 text-center">
                            ₹<?php echo number_format($data['income'], 0); ?> In<br>
                            ₹<?php echo number_format($data['expense'], 0); ?> Out
                        </div>

                        <!-- Savings (Green) -->
                        <?php if($data['savings'] > 0): ?>
                        <div class="w-full bg-emerald-500 rounded-t-sm z-10 transition-all group-hover:bg-emerald-400" style="height: <?php echo $hSav; ?>%"></div>
                        <?php endif; ?>
                        
                        <!-- Expense (Red) -->
                        <div class="w-full bg-red-500 <?php echo $data['savings'] <= 0 ? 'rounded-t-sm' : ''; ?> z-10 transition-all group-hover:bg-red-400" style="height: <?php echo $hExp; ?>%"></div>
                    </div>
                    
                    <span class="text-[9px] font-black <?php echo $isFocus ? 'text-brand-600' : 'text-gray-400'; ?> mt-4 uppercase tracking-tighter"><?php echo $name; ?></span>
                </a>
                <?php endforeach; ?>
            </div>
            <div class="mt-8 flex justify-center space-x-8">
                <div class="flex items-center space-x-2">
                    <div class="w-3 h-3 bg-red-500 rounded-full"></div>
                    <span class="text-[9px] font-black text-gray-400 uppercase tracking-widest">Capital Drain</span>
                </div>
                <div class="flex items-center space-x-2">
                    <div class="w-3 h-3 bg-emerald-500 rounded-full"></div>
                    <span class="text-[9px] font-black text-gray-400 uppercase tracking-widest">Retained Savings</span>
                </div>
            </div>
        </div>
    </div>

    <!-- Drill-Down Ledger -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
        
        <!-- Money IN -->
        <div class="bg-white dark:bg-gray-800 p-8 rounded-3xl shadow-sm border border-gray-100 dark:border-gray-700">
            <div class="flex justify-between items-center mb-8 pb-4 border-b border-gray-50 dark:border-gray-900">
                <div>
                    <h3 class="text-xl font-black text-gray-900 dark:text-white tracking-tight">Capital Inflow</h3>
                    <p class="text-[10px] text-emerald-500 font-black uppercase tracking-widest"><?php echo date('F Y', strtotime($focusMonth."-01")); ?></p>
                </div>
                <div class="text-right">
                    <span class="block text-[9px] font-black text-gray-400 uppercase tracking-widest mb-1">Total In</span>
                    <p class="text-2xl font-black text-emerald-600 tracking-tighter">₹<?php echo number_format($totalIn, 0); ?></p>
                </div>
            </div>

            <div class="space-y-4">
                <?php foreach($focusIncomes as $inc): ?>
                <div class="p-4 bg-emerald-50/30 dark:bg-emerald-900/10 rounded-2xl flex justify-between items-center group hover:bg-emerald-50/50 transition-all">
                    <div class="flex items-center space-x-4">
                        <div class="w-10 h-10 bg-emerald-100 dark:bg-emerald-900/30 text-emerald-600 rounded-full flex items-center justify-center font-bold">📥</div>
                        <div>
                            <span class="block text-sm font-black text-gray-900 dark:text-white leading-tight">Income Resource</span>
                            <span class="text-[10px] text-gray-400 font-bold uppercase tracking-tighter">Automated Deposit</span>
                        </div>
                    </div>
                    <span class="text-base font-black text-emerald-600">₹<?php echo number_format($inc['total_income'], 0); ?></span>
                </div>
                <?php endforeach; ?>
                
                <?php if(empty($focusIncomes)): ?>
                <div class="text-center py-12 opacity-30 italic text-sm">No recorded inflows for this period.</div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Money OUT -->
        <div class="bg-white dark:bg-gray-800 p-8 rounded-3xl shadow-sm border border-gray-100 dark:border-gray-700">
            <div class="flex justify-between items-center mb-8 pb-4 border-b border-gray-50 dark:border-gray-900">
                <div>
                    <h3 class="text-xl font-black text-gray-900 dark:text-white tracking-tight">Capital Outflow</h3>
                    <p class="text-[10px] text-red-500 font-black uppercase tracking-widest"><?php echo date('F Y', strtotime($focusMonth."-01")); ?></p>
                </div>
                <div class="text-right">
                    <span class="block text-[9px] font-black text-gray-400 uppercase tracking-widest mb-1">Total Out</span>
                    <p class="text-2xl font-black text-red-600 tracking-tighter">₹<?php echo number_format($totalOut, 0); ?></p>
                </div>
            </div>

            <div class="space-y-4 max-h-[500px] overflow-y-auto pr-2 custom-scrollbar">
                <?php foreach($focusOutgoings as $out): 
                    $colorClass = $out['type'] === 'EMI/Bill' ? 'text-brand-600' : ($out['type'] === 'Investment' ? 'text-indigo-600' : 'text-gray-900 dark:text-white');
                    $bgClass = $out['type'] === 'EMI/Bill' ? 'bg-brand-50/30 dark:bg-brand-900/10' : ($out['type'] === 'Investment' ? 'bg-indigo-50/30 dark:bg-indigo-900/10' : 'bg-gray-50 dark:bg-gray-900/30');
                ?>
                <div class="p-4 <?php echo $bgClass; ?> rounded-2xl flex justify-between items-center transition-all hover:scale-[1.01]">
                    <div class="flex items-center space-x-4">
                        <div class="w-10 h-10 bg-white dark:bg-gray-800 rounded-xl flex items-center justify-center text-lg shadow-sm border border-gray-100 dark:border-gray-700">
                            <?php echo $out['type'] === 'EMI/Bill' ? '🗓️' : ($out['type'] === 'Investment' ? '💎' : '🛒'); ?>
                        </div>
                        <div>
                            <span class="block text-sm font-black <?php echo $colorClass; ?> leading-tight"><?php echo htmlspecialchars($out['description'] ?: $out['category']); ?></span>
                            <div class="flex items-center space-x-2 mt-1">
                                <span class="text-[9px] text-gray-400 font-black uppercase tracking-tighter"><?php echo $out['type']; ?></span>
                                <span class="w-1 h-1 bg-gray-300 rounded-full"></span>
                                <span class="text-[9px] text-gray-400 font-bold"><?php echo date('d M', strtotime($out['tr_date'])); ?></span>
                            </div>
                        </div>
                    </div>
                    <span class="text-base font-black <?php echo $colorClass; ?>">₹<?php echo number_format($out['amount'], 0); ?></span>
                </div>
                <?php endforeach; ?>
            </div>
        </div>

    </div>

    <!-- Monthly Summary Footer -->
    <div class="bg-gray-900 text-white p-8 rounded-3xl flex flex-col md:flex-row justify-between items-center gap-8 shadow-2xl">
        <div class="flex items-center space-x-6">
            <div class="bg-white/10 p-5 rounded-2xl text-4xl">🔬</div>
            <div>
                <h4 class="text-xl font-black tracking-tight">Focus Analysis: <?php echo date('F Y', strtotime($focusMonth."-01")); ?></h4>
                <p class="text-[10px] text-gray-500 font-black uppercase tracking-[0.2em] mt-1">Synthesized Financial Pulse</p>
            </div>
        </div>
        <div class="flex items-center gap-12">
            <div class="text-center">
                <span class="block text-[9px] text-gray-500 font-black uppercase tracking-widest mb-1">Efficiency Ratio</span>
                <p class="text-3xl font-black text-brand-400"><?php echo $totalIn > 0 ? round(($totalOut / $totalIn) * 100) : 0; ?>%</p>
            </div>
            <div class="text-right">
                <span class="block text-[9px] text-gray-500 font-black uppercase tracking-widest mb-1">Surplus Delta</span>
                <p class="text-3xl font-black <?php echo $netDrift >= 0 ? 'text-emerald-400' : 'text-red-400'; ?>">₹<?php echo number_format($netDrift, 0); ?></p>
            </div>
        </div>
    </div>
</div>

<style>
.custom-scrollbar::-webkit-scrollbar { width: 4px; }
.custom-scrollbar::-webkit-scrollbar-track { background: transparent; }
.custom-scrollbar::-webkit-scrollbar-thumb { background: #e2e8f0; border-radius: 20px; }
.dark .custom-scrollbar::-webkit-scrollbar-thumb { background: #334155; }
</style>

<?php require_once '../includes/footer.php'; ?>
