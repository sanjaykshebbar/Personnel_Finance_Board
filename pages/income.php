<?php
require_once '../config/database.php';
require_once '../includes/auth.php';
requireLogin();

$userId = getCurrentUserId();
$cutoffDate = '2026-01-20';

// Handle Form Submission BEFORE any output
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['delete_id'])) {
        $stmt = $pdo->prepare("DELETE FROM income WHERE id = ? AND user_id = ?");
        $stmt->execute([$_POST['delete_id'], $userId]);
        $_SESSION['message'] = "Record deleted successfully!";
    } else {
        $month = $_POST['month'];
        $accountingDate = $_POST['accounting_date'] ?: ($month . '-01'); // Default to month start if empty
        $salary = $_POST['salary_income'] ?: 0;
        $other = $_POST['other_income'] ?: 0;
        $total = $salary + $other;
        
        // Check if updating
        if (!empty($_POST['id'])) {
            $id = $_POST['id'];
            $stmt = $pdo->prepare("UPDATE income SET month=?, accounting_date=?, salary_income=?, other_income=?, total_income=? WHERE id=? AND user_id=?");
            try {
                $status = $stmt->execute([$month, $accountingDate, $salary, $other, $total, $id, $userId]);
                if ($status) {
                    $_SESSION['flash_message'] = "Income record updated successfully! ✅";
                } else {
                    $_SESSION['flash_message'] = "⚠️ Update failed - record might not exist or no changes made.";
                }
            } catch (PDOException $e) { 
                $_SESSION['flash_message'] = "❌ SQL Error: " . $e->getMessage(); 
            }
        } else {
            // Insert
            $stmt = $pdo->prepare("INSERT INTO income (user_id, month, accounting_date, salary_income, other_income, total_income) VALUES (?, ?, ?, ?, ?, ?)");
            try {
                $stmt->execute([$userId, $month, $accountingDate, $salary, $other, $total]);
                $_SESSION['flash_message'] = "Income added successfully! ✅";
            } catch (PDOException $e) { 
                $_SESSION['flash_message'] = "❌ Error: (Maybe duplicate month?) " . $e->getMessage(); 
            }
        }
    }
    header("Location: income.php");
    exit;
}

$pageTitle = 'Income Tracker';
require_once '../includes/header.php';

// Fetch all income
$stmt = $pdo->prepare("SELECT * FROM income WHERE user_id = ? ORDER BY month DESC");
$stmt->execute([$userId]);
$incomes = $stmt->fetchAll();

?>

<div class="max-w-4xl mx-auto space-y-6">
    

    <!-- Manual Entry Form -->
    <div class="bg-white dark:bg-gray-800 shadow-xl rounded-2xl p-8 border border-gray-100 dark:border-gray-700 transition-all">
        <div class="flex items-center space-x-3 mb-6">
            <div class="p-3 bg-brand-500 rounded-xl text-white shadow-lg shadow-brand-500/20">
                💰
            </div>
            <div>
                <h2 class="text-xl font-bold text-gray-900 dark:text-white">Add Monthly Income</h2>
                <p class="text-xs text-gray-500 dark:text-gray-400">Record your earnings for accurate tracking</p>
            </div>
        </div>

        <form method="POST" action="income.php" class="grid grid-cols-1 md:grid-cols-3 gap-6 items-end">
            
            <div class="space-y-1">
                <label class="block text-[10px] font-bold text-gray-400 uppercase tracking-wider">Budget Month (Target)</label>
                <input type="month" name="month" required 
                       class="w-full rounded-xl border-gray-200 dark:border-gray-700 dark:bg-gray-900 dark:text-white shadow-sm focus:ring-2 focus:ring-brand-500 outline-none p-3 text-sm transition-all"
                       value="<?php echo date('Y-m'); ?>">
            </div>

            <div class="space-y-1">
                <label class="block text-[10px] font-bold text-gray-400 uppercase tracking-wider">Date Received</label>
                <input type="date" name="accounting_date" 
                       class="w-full rounded-xl border-gray-200 dark:border-gray-700 dark:bg-gray-900 dark:text-white shadow-sm focus:ring-2 focus:ring-brand-500 outline-none p-3 text-sm transition-all"
                       value="<?php echo date('Y-m-d'); ?>">
            </div>

            <div class="space-y-1">
                <label class="block text-[10px] font-bold text-gray-400 uppercase tracking-wider">Salary (Net Credit)</label>
                <div class="relative">
                    <span class="absolute left-3 top-1/2 -translate-y-1/2 text-gray-400 text-sm">₹</span>
                    <input type="number" step="0.01" name="salary_income" required 
                           class="w-full pl-7 rounded-xl border-gray-200 dark:border-gray-700 dark:bg-gray-900 dark:text-white shadow-sm focus:ring-2 focus:ring-brand-500 outline-none p-3 text-sm transition-all"
                           placeholder="0.00"
                           value="">
                </div>
            </div>

            <div class="space-y-1">
                <label class="block text-[10px] font-bold text-gray-400 uppercase tracking-wider">Other Income</label>
                <div class="relative">
                    <span class="absolute left-3 top-1/2 -translate-y-1/2 text-gray-400 text-sm">₹</span>
                    <input type="number" step="0.01" name="other_income" 
                           class="w-full pl-7 rounded-xl border-gray-200 dark:border-gray-700 dark:bg-gray-900 dark:text-white shadow-sm focus:ring-2 focus:ring-brand-500 outline-none p-3 text-sm transition-all"
                           placeholder="0.00"
                           value="">
                </div>
            </div>

                <button type="submit" class="bg-brand-600 hover:bg-brand-700 text-white font-bold py-3 px-8 rounded-xl shadow-lg shadow-brand-600/20 transition-all active:scale-95">
                    Save Income
                </button>
        </form>
    </div>


    <script>
    document.addEventListener('DOMContentLoaded', function() {
        const monthInput = document.querySelector('input[name="month"]');
        const budgetDateInput = document.querySelector('input[name="accounting_date"]');
        
        // We need a 'Received Date' separate from 'Budget Month' to make this smart logic work effectively.
        // Currently, we only have 'Month' and 'Accounting Date'. 
        // Let's assume the user picks the 'Accounting Date' as the effective date.
        // Actually, the user asked: "If salary is credited on Jan month then income jan month should be shown in Feb".
        // This implies we need a 'Date Received' field to compare against.
        // Looking at the form, we have 'month' (YYYY-MM) and 'accounting_date' (YYYY-MM-DD).
        
        // Let's add a listener to 'accounting_date'. If the user picks > 20th, we shift the 'month' input to next month.
        
        if (budgetDateInput && monthInput) {
            budgetDateInput.addEventListener('change', function() {
                const dateVal = new Date(this.value);
                if (!isNaN(dateVal.getTime())) {
                    const day = dateVal.getDate();
                    
                    // If date is > 20th, suggest next month
                    if (day > 20) {
                        // Calculate next month
                        const nextMonthDate = new Date(dateVal.getFullYear(), dateVal.getMonth() + 1, 1);
                        const yyyy = nextMonthDate.getFullYear();
                        const mm = String(nextMonthDate.getMonth() + 1).padStart(2, '0');
                        const nextMonthStr = `${yyyy}-${mm}`;
                        
                        // Check if we should auto-update (only if it matches the current month logic)
                        // Simple approach: Always suggest next month if > 20th
                        if (monthInput.value !== nextMonthStr) {
                             // Optional: Flash a message or just do it
                             monthInput.value = nextMonthStr;
                             
                             // Also update the UI to show feedback
                             const feedback = document.createElement('div');
                             feedback.className = 'fixed bottom-4 right-4 bg-gray-900 text-white px-4 py-2 rounded-lg shadow-lg text-xs font-bold animate-bounce-short z-50';
                             feedback.innerText = '💡 Budget Month auto-shifted to ' + nextMonthDate.toLocaleString('default', { month: 'long' });
                             document.body.appendChild(feedback);
                             setTimeout(() => feedback.remove(), 3000);
                        }
                    } else {
                        // Reset to current month of the selected date if <= 20
                        const yyyy = dateVal.getFullYear();
                        const mm = String(dateVal.getMonth() + 1).padStart(2, '0');
                        monthInput.value = `${yyyy}-${mm}`;
                    }
                }
            });
        }
    });
    </script>

    <!-- Table -->
    <div class="bg-white shadow rounded-lg overflow-hidden">
        <div class="px-6 py-4 border-b border-gray-200">
            <h3 class="text-lg font-medium text-gray-900">Income History</h3>
        </div>
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Month</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Received On</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Salary</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Other</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Total</th>
                        <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">Actions</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    <?php foreach ($incomes as $row): ?>
                    <tr>
                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                            <?php echo date('F Y', strtotime($row['month'])); ?>
                            <?php if ($row['accounting_date'] < $cutoffDate): ?>
                                <div class="text-[8px] text-amber-600 font-bold uppercase">Reference Only</div>
                            <?php endif; ?>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                            <?php echo date('d M Y', strtotime($row['accounting_date'])); ?>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">₹<?php echo number_format($row['salary_income'], 2); ?></td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">₹<?php echo number_format($row['other_income'], 2); ?></td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm font-bold text-gray-900">₹<?php echo number_format($row['total_income'], 2); ?></td>
                        <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                            <form method="POST" class="inline-block" onsubmit="return confirm('Are you sure?');">
                                <input type="hidden" name="delete_id" value="<?php echo $row['id']; ?>">
                                <button type="submit" class="text-red-600 hover:text-red-900">Delete</button>
                            </form>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>


<?php require_once '../includes/footer.php'; ?>
