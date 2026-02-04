<?php
require_once '../config/database.php';
require_once '../includes/auth.php';
requireLogin();

$userId = getCurrentUserId();

// Handle POST requests BEFORE any output
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if (isset($_POST['resolve_id'])) {
            $loanId = $_POST['resolve_id'];
            
            // 1. Fetch loan details first to see if it's 'Lent'
            $stmt = $pdo->prepare("SELECT * FROM loans WHERE id = ? AND user_id = ?");
            $stmt->execute([$loanId, $userId]);
            $loan = $stmt->fetch();
            
            if (!$loan) throw new Exception("Loan not found.");

            // 2. Mark as Settled
            $stmt = $pdo->prepare("UPDATE loans SET status = 'Settled', settlement_date = ? WHERE id = ? AND user_id = ?");
            $stmt->execute([date('Y-m-d'), $loanId, $userId]);

            // 3. If it was 'Lent', record the return as income
            if ($loan['type'] === 'Lent') {
                $amt = (float)$loan['amount'];
                $m = date('Y-m');
                $stmtInc = $pdo->prepare("SELECT id, other_income, total_income FROM income WHERE month = ? AND user_id = ?");
                $stmtInc->execute([$m, $userId]);
                $existing = $stmtInc->fetch();
                
                if ($existing) {
                    $newOther = $existing['other_income'] + $amt;
                    $newTotal = $existing['total_income'] + $amt;
                    $pdo->prepare("UPDATE income SET other_income = ?, total_income = ? WHERE id = ?")
                        ->execute([$newOther, $newTotal, $existing['id']]);
                } else {
                    $pdo->prepare("INSERT INTO income (user_id, month, other_income, total_income) VALUES (?, ?, ?, ?)")
                        ->execute([$userId, $m, $amt, $amt]);
                }
            }
            
            $_SESSION['flash_message'] = "Loan marked as settled and synchronized.";
        } elseif (isset($_POST['delete_id'])) {
            $loanId = $_POST['delete_id'];
            
            // Fetch loan details before deletion
            $loanStmt = $pdo->prepare("SELECT * FROM loans WHERE id = ? AND user_id = ?");
            $loanStmt->execute([$loanId, $userId]);
            $loan = $loanStmt->fetch();
            
            if ($loan) {
                // Delete the loan record (Cleanup of related entries is optional/risky, usually we keep history)
                $stmt = $pdo->prepare("DELETE FROM loans WHERE id = ? AND user_id = ?");
                $stmt->execute([$loanId, $userId]);
                $_SESSION['flash_message'] = "Loan record deleted.";
            } else {
                $_SESSION['flash_message'] = "Loan not found.";
            }
        } elseif (isset($_POST['update_paid'])) {
            try {
                $id = $_POST['loan_id'];
                // name="payment_amount" in form (line 459)
                $amt = filter_var($_POST['payment_amount'] ?? 0, FILTER_VALIDATE_FLOAT);
                // name="paid_months" in form (line 452)
                $months = (int)($_POST['paid_months'] ?? 0);
                $payDate = $_POST['payment_date'] ?: date('Y-m-d'); 

                if ($amt <= 0 && $months <= 0) throw new Exception("Please enter an amount or installments.");

                // Fetch current state
                $curr = $pdo->prepare("SELECT * FROM loans WHERE id = ? AND user_id = ?");
                $curr->execute([$id, $userId]);
                $loan = $curr->fetch();
                if (!$loan) throw new Exception("Loan not found.");

                $loanMonth = date('Y-m', strtotime($payDate));
                
                $pdo->beginTransaction();

                // Update Principal/Installments
                // FIXED: 'paid_months' from modal is the NEW ABSOLUTE TOTAL, not a delta to add.
                $stmt = $pdo->prepare("UPDATE loans SET paid_amount = paid_amount + ?, paid_months = ? WHERE id = ? AND user_id = ?");
                $stmt->execute([$amt, $months, $id, $userId]);
                
                // Check for Settlement (Status transition)
                $chk = $pdo->prepare("SELECT amount, paid_amount FROM loans WHERE id = ?");
                $chk->execute([$id]);
                $ln = $chk->fetch();

                $isSettled = false;
                if ($ln) {
                    // Settlement is ALWAYS based on Amount (Zero Balance)
                    if (round($ln['paid_amount'], 2) >= round($ln['amount'], 2)) {
                        $isSettled = true;
                    }
                }

                if ($isSettled) {
                    $pdo->prepare("UPDATE loans SET status = 'Settled', settlement_date = ? WHERE id = ?")->execute([date('Y-m-d'), $id]);
                }

                // Record Financial Footprint
                if ($amt > 0) {
                    if ($loan['type'] === 'Borrowed') {
                        $desc = "Repayment: " . ($loan['person_name'] ?? 'Loan');
                        $method = $_POST['payment_method'] ?? 'Bank Account';
                        if ($method === 'Credit Card' && !empty($_POST['credit_card_name'])) {
                            $method = $_POST['credit_card_name']; // Use the specific card name
                        }
                        $ins = $pdo->prepare("INSERT INTO expenses (user_id, date, category, description, amount, payment_method, linked_type, linked_id) VALUES (?, ?, 'EMI/Bills', ?, ?, ?, 'LOAN', ?)");
                        $ins->execute([$userId, $payDate, $desc, $amt, $method, $id]);
                        $newExpId = $pdo->lastInsertId();

                        $invDesc = "Debt Reduction: " . ($loan['person_name'] ?? 'Loan');
                        $invStmt = $pdo->prepare("INSERT INTO investments (user_id, investment_name, frequency, amount, status, due_date, expense_id) VALUES (?, ?, 'Monthly', ?, 'Paid', ?, ?)");
                        $invStmt->execute([$userId, $invDesc, $amt, $payDate, $newExpId]);
                    } else {
                        // Receiving Lent money = Other Income
                        $incStmt = $pdo->prepare("SELECT id, other_income, total_income FROM income WHERE month = ? AND user_id = ?");
                        $incStmt->execute([$loanMonth, $userId]);
                        $foundInc = $incStmt->fetch();

                        if ($foundInc) {
                            $newOther = $foundInc['other_income'] + $amt;
                            $newTotal = $foundInc['total_income'] + $amt;
                            $upd = $pdo->prepare("UPDATE income SET other_income = ?, total_income = ? WHERE id = ?");
                            $upd->execute([$newOther, $newTotal, $foundInc['id']]);
                        } else {
                            $ins = $pdo->prepare("INSERT INTO income (user_id, month, accounting_date, salary_income, other_income, total_income) VALUES (?, ?, ?, 0, ?, ?)");
                            $ins->execute([$userId, $loanMonth, $payDate, $amt, $amt]);
                        }
                    }
                }
                
                $pdo->commit();
                $_SESSION['flash_message'] = "Payment logged successfully.";
                if ($ln && $ln['paid_amount'] >= $ln['amount']) {
                    $_SESSION['flash_message'] .= " Loan is now fully Settled! 🎉";
                }
            } catch (Exception $e) {
                if ($pdo->inTransaction()) $pdo->rollBack();
                $_SESSION['flash_message'] = "Error updating payment: " . $e->getMessage();
            }
            header("Location: loans.php");
            exit;
        }
 elseif (isset($_POST['upload_doc'])) {
            $loanId = $_POST['loan_id'];
            $docType = $_POST['doc_type']; // 'sanction_doc' or 'clearance_doc'
            
            if (isset($_FILES['document']) && $_FILES['document']['error'] === 0) {
                $ext = pathinfo($_FILES['document']['name'], PATHINFO_EXTENSION);
                $fileName = "loan_{$loanId}_{$docType}_" . time() . "." . $ext;
                $uploadDir = '../uploads/loans/';
                if (!is_dir($uploadDir)) mkdir($uploadDir, 0777, true);
                
                $targetPath = $uploadDir . $fileName;
                if (move_uploaded_file($_FILES['document']['tmp_name'], $targetPath)) {
                    $stmt = $pdo->prepare("UPDATE loans SET $docType = ? WHERE id = ? AND user_id = ?");
                    $stmt->execute([$fileName, $loanId, $userId]);
                    $_SESSION['flash_message'] = "Document uploaded successfully.";
                }
            }
        } else {
            // 1. Add New
            $name = $_POST['person_name'];
            $type = $_POST['type']; // 'Lent' or 'Borrowed'
            $amount = (float)$_POST['amount'];
            $date = $_POST['date'];
            $institution = $_POST['source_institution'] ?? null;
            $tenure = (int)($_POST['tenure_months'] ?? 0);
            $interest = (float)($_POST['interest_rate'] ?? 0);
            $emi = (float)($_POST['emi_amount'] ?? 0);
            $paid = (int)($_POST['paid_months'] ?? 0);
            $paidAmt = (float)($_POST['paid_amount'] ?? ($emi * $paid)); // Initial paid amount estimation
            $accNo = $_POST['loan_account_no'] ?? null;
            
            $sanctionDoc = null;
            if (isset($_FILES['sanction_doc']) && $_FILES['sanction_doc']['error'] === 0) {
                $ext = pathinfo($_FILES['sanction_doc']['name'], PATHINFO_EXTENSION);
                $sanctionDoc = "loan_sanction_" . time() . "." . $ext;
                $uploadDir = '../uploads/loans/';
                if (!is_dir($uploadDir)) mkdir($uploadDir, 0777, true);
                move_uploaded_file($_FILES['sanction_doc']['tmp_name'], $uploadDir . $sanctionDoc);
            }

            $stmt = $pdo->prepare("INSERT INTO loans (user_id, person_name, type, amount, date, status, source_institution, tenure_months, interest_rate, emi_amount, paid_months, paid_amount, sanction_doc, loan_account_no) VALUES (?, ?, ?, ?, ?, 'Pending', ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([$userId, $name, $type, $amount, $date, $institution, $tenure, $interest, $emi, $paid, $paidAmt, $sanctionDoc, $accNo]);
            
            // 2. If 'Lent', record as Expense
            if ($type === 'Lent') {
                $desc = "Money Lent: " . $name;
                $ins = $pdo->prepare("INSERT INTO expenses (user_id, date, category, description, amount, payment_method, linked_type, linked_id) VALUES (?, ?, 'EMI/Bills', ?, ?, 'Bank Account', 'LOAN', ?)");
                $ins->execute([$userId, $date, $desc, $amount, $loanId]);
            }

            $_SESSION['flash_message'] = "New entry logged! Recorded ₹" . number_format($amount, 2);
        }
    } catch (Exception $e) {
        $errorMsg = $e->getMessage();
        if (strpos($errorMsg, 'CHECK constraint failed') !== false) {
            $errorMsg = "Database Error: A formatting rule was violated. (" . $errorMsg . ")";
        }
        $_SESSION['flash_message'] = "⚠️ ERROR: " . $errorMsg;
    }
    header("Location: loans.php");
    exit;
}

$pageTitle = 'Lending & Borrowing';
require_once '../includes/header.php';

// --- Constants for accounting ---
// global SYSTEM_START_DATE used

// Fetch
// Fetch
$showSettled = !empty($_GET['show_settled']);
$query = "SELECT * FROM loans WHERE user_id = ? ";
if (!$showSettled) {
    $query .= "AND status = 'Pending' ";
}
$query .= "ORDER BY status ASC, date DESC";

$stmt = $pdo->prepare($query);
$stmt->execute([$userId]);
$loans = $stmt->fetchAll();

// Fetch Credit Accounts for the modal dropdown
$cardStmt = $pdo->prepare("SELECT provider_name FROM credit_accounts WHERE user_id = ? ORDER BY provider_name ASC");
$cardStmt->execute([$userId]);
$creditCards = $cardStmt->fetchAll();

// Calculate Totals (Pending only + Active)
$totalLent = 0;
$totalBorrowed = 0;
foreach($loans as $l) {
    if ($l['status'] === 'Pending') {
        $outstanding = $l['amount'] - ($l['paid_amount'] ?? 0);
        if ($l['type'] === 'Lent') $totalLent += $outstanding;
        else $totalBorrowed += $outstanding;
    }
}
?>

<div class="space-y-6">
    <!-- Summaries -->
    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
        <div class="bg-white dark:bg-gray-800 p-6 rounded-2xl shadow-sm border border-red-100 dark:border-red-900/30 flex justify-between items-center transition-all">
            <div>
                <h3 class="text-red-600 dark:text-red-400 font-black uppercase text-[10px] tracking-widest">Active Liabilities</h3>
                <p class="text-3xl font-black text-gray-900 dark:text-white mt-1">₹<?php echo number_format($totalBorrowed, 2); ?></p>
            </div>
            <div class="text-4xl opacity-20">🏦</div>
        </div>
        <div class="bg-white dark:bg-gray-800 p-6 rounded-2xl shadow-sm border border-emerald-100 dark:border-emerald-900/30 flex justify-between items-center transition-all">
            <div>
                <h3 class="text-emerald-600 dark:text-emerald-400 font-black uppercase text-[10px] tracking-widest">Personal Receivables</h3>
                <p class="text-3xl font-black text-gray-900 dark:text-white mt-1">₹<?php echo number_format($totalLent, 2); ?></p>
            </div>
            <div class="text-4xl opacity-20">🤝</div>
        </div>
    </div>


    <!-- Form Area -->
    <div class="bg-white dark:bg-gray-800 p-8 rounded-2xl shadow-sm border border-gray-100 dark:border-gray-700">
        <div class="flex items-center justify-between mb-8">
            <div class="flex items-center space-x-3">
                <div class="bg-brand-500 text-white p-2 rounded-lg text-lg">➕</div>
                <h2 class="text-xl font-black text-gray-900 dark:text-white tracking-tight">Financial Commitment</h2>
            </div>
            <div class="flex items-center space-x-2">
                <input type="checkbox" id="toggleSettled" <?php echo $showSettled ? 'checked' : ''; ?> onchange="window.location.href='?show_settled='+(this.checked?1:0)" class="rounded text-brand-600 focus:ring-brand-500 bg-gray-100 border-gray-300">
                <label for="toggleSettled" class="text-xs font-bold text-gray-500 uppercase tracking-wider cursor-pointer select-none">Show Settled</label>
            </div>
        </div>
        
        <form method="POST" enctype="multipart/form-data" id="loanForm" class="space-y-6">
            <div class="grid grid-cols-1 md:grid-cols-4 gap-6 items-end">
                <div class="md:col-span-1">
                    <label class="block text-[10px] font-black text-gray-400 uppercase tracking-widest mb-2">Loan Category</label>
                    <select name="type" id="loanType" class="w-full bg-gray-50 dark:bg-gray-900 border-none rounded-xl p-3 text-sm font-bold focus:ring-2 focus:ring-brand-500 dark:text-white">
                        <option value="Lent">Lent (Personal)</option>
                        <option value="Borrowed">Borrowed (Personal)</option>
                        <option value="Borrowed" data-institutional="true">Institutional / Bank Loan</option>
                    </select>
                </div>
                <div class="md:col-span-1">
                    <label class="block text-[10px] font-black text-gray-400 uppercase tracking-widest mb-2">Identify / Purpose</label>
                    <input type="text" name="person_name" placeholder="E.g. HDFC Home Loan" required class="w-full bg-gray-50 dark:bg-gray-900 border-none rounded-xl p-3 text-sm font-bold focus:ring-2 focus:ring-brand-500 dark:text-white">
                </div>
                <div class="md:col-span-1">
                    <label class="block text-[10px] font-black text-gray-400 uppercase tracking-widest mb-2">Institution Code</label>
                    <input type="text" name="source_institution" placeholder="Optional" class="w-full bg-gray-50 dark:bg-gray-900 border-none rounded-xl p-3 text-sm font-bold focus:ring-2 focus:ring-brand-500 dark:text-white">
                </div>
                <div class="md:col-span-1">
                    <label class="block text-[10px] font-black text-gray-400 uppercase tracking-widest mb-2">Principal Value</label>
                    <div class="relative">
                        <span class="absolute left-3 top-3 text-gray-400 font-bold">₹</span>
                        <input type="number" step="0.01" name="amount" id="loanAmount" placeholder="0.00" required class="w-full bg-gray-50 dark:bg-gray-900 border-none rounded-xl pl-8 p-3 text-sm font-bold focus:ring-2 focus:ring-brand-500 dark:text-white">
                    </div>
                </div>
            </div>

            <!-- Institutional Context -->
            <div id="advancedFields" class="p-6 bg-brand-50/50 dark:bg-brand-900/10 rounded-2xl border border-brand-100 dark:border-brand-900/30 hidden">
                <div class="grid grid-cols-1 md:grid-cols-5 gap-6">
                    <div>
                        <label class="block text-[10px] font-black text-brand-600 uppercase tracking-widest mb-2">Tenure (Months)</label>
                        <input type="number" name="tenure_months" id="loanTenure" placeholder="12, 24, 36..." class="w-full bg-white dark:bg-gray-900 border-none rounded-xl p-3 text-sm font-bold focus:ring-2 focus:ring-brand-500 dark:text-white">
                    </div>
                    <div>
                        <label class="block text-[10px] font-black text-brand-600 uppercase tracking-widest mb-2">Annual Int. (%)</label>
                        <input type="number" step="0.01" name="interest_rate" id="loanInterest" placeholder="0.00" class="w-full bg-white dark:bg-gray-900 border-none rounded-xl p-3 text-sm font-bold focus:ring-2 focus:ring-brand-500 dark:text-white">
                    </div>
                    <div>
                        <label class="block text-[10px] font-black text-brand-600 uppercase tracking-widest mb-2">Computed EMI</label>
                        <input type="number" step="0.01" name="emi_amount" id="loanEmi" readonly class="w-full bg-brand-100 dark:bg-brand-900/50 border-none rounded-xl p-3 text-sm font-black text-brand-700 dark:text-brand-400">
                    </div>
                    <div>
                        <label class="block text-[10px] font-black text-brand-600 uppercase tracking-widest mb-2">Account Number</label>
                        <input type="text" name="loan_account_no" placeholder="Loan A/C No" class="w-full bg-white dark:bg-gray-900 border-none rounded-xl p-3 text-sm font-bold focus:ring-2 focus:ring-brand-500 dark:text-white">
                    </div>
                    <div>
                        <label class="block text-[10px] font-black text-brand-600 uppercase tracking-widest mb-2">Sanction Certificate</label>
                        <input type="file" name="sanction_doc" class="w-full text-xs text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-full file:border-0 file:text-xs file:font-semibold file:bg-brand-100 file:text-brand-700 hover:file:bg-brand-200">
                    </div>
                </div>
            </div>

            <div class="flex flex-col md:flex-row gap-6 items-center">
                <div class="flex-grow">
                    <label class="block text-[10px] font-black text-gray-400 uppercase tracking-widest mb-2">Engagement Date</label>
                    <input type="date" name="date" required value="<?php echo date('Y-m-d'); ?>" class="w-full bg-gray-50 dark:bg-gray-900 border-none rounded-xl p-3 text-sm font-bold focus:ring-2 focus:ring-brand-500 dark:text-white">
                </div>
                <button class="bg-brand-600 text-white px-12 py-3 rounded-xl font-black hover:bg-brand-700 transition shadow-lg shadow-brand-500/20 text-sm whitespace-nowrap">AUTHENTICATE & LOG</button>
            </div>
        </form>
    </div>

    <!-- Active Ledgers -->
    <div class="grid grid-cols-1 gap-6">
        <?php foreach ($loans as $row): 
            // FIXED LOGIC: 'Institutional' means it has a defined TENURE > 0.
            // If tenure is 0, it's a personal/flat loan, even if "Hospital Bill" is in the source field.
            $isInst = $row['tenure_months'] > 0;
            
            $paidMonths = $row['paid_months'] ?? 0;
            $totalMonths = $row['tenure_months'] ?? 0;
            $progress = ($totalMonths > 0) ? round(($paidMonths / $totalMonths) * 100) : 0;
        ?>
        <div class="bg-white dark:bg-gray-800 p-6 rounded-2xl shadow-sm border border-gray-100 dark:border-gray-700 hover:border-brand-500/30 transition-all group">
            <div class="flex flex-col lg:flex-row justify-between lg:items-center gap-6">
                <!-- Details -->
                <div class="flex items-start space-x-4">
                    <div class="p-4 rounded-2xl <?php echo $row['type'] === 'Lent' ? 'bg-emerald-50 dark:bg-emerald-900/20 text-emerald-600' : 'bg-red-50 dark:bg-red-900/20 text-red-600'; ?> font-black text-xl">
                        <?php echo $row['type'] === 'Lent' ? '📤' : '📥'; ?>
                    </div>
                    <div>
                        <div class="text-[10px] font-black text-gray-400 uppercase tracking-widest mb-1"><?php echo $row['type'] === 'Lent' ? 'Invested with' : 'Liability to'; ?></div>
                        <h4 class="text-lg font-black text-gray-900 dark:text-white tracking-tight leading-none"><?php echo htmlspecialchars($row['person_name']); ?></h4>
                        <?php if ($row['date'] < SYSTEM_START_DATE): ?>
                            <span class="mt-1 inline-block px-1.5 py-0.5 bg-amber-50 dark:bg-amber-900/20 text-amber-600 dark:text-amber-400 rounded text-[8px] font-bold uppercase tracking-widest border border-amber-100 dark:border-amber-900/30">Reference Only (Pre-Active)</span>
                        <?php endif; ?>
                        <?php if(!empty($row['source_institution']) || !empty($row['loan_account_no'])): ?>
                            <div class="flex flex-wrap gap-2 mt-2">
                                <?php if(!empty($row['source_institution'])): ?>
                                    <span class="px-2 py-0.5 bg-gray-100 dark:bg-gray-900 text-gray-500 rounded text-[9px] font-bold uppercase tracking-tighter ring-1 ring-gray-200 dark:ring-gray-800"><?php echo htmlspecialchars($row['source_institution']); ?></span>
                                <?php endif; ?>
                                <?php if(!empty($row['loan_account_no'])): ?>
                                    <span class="px-2 py-0.5 bg-brand-50 dark:bg-brand-900/30 text-brand-600 rounded text-[9px] font-black uppercase tracking-tighter ring-1 ring-brand-100 dark:ring-brand-900/50">A/C: <?php echo htmlspecialchars($row['loan_account_no']); ?></span>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Financials -->
                <div class="flex flex-wrap items-center gap-8">
                    <div class="text-center">
                        <span class="block text-[9px] font-black text-gray-400 uppercase tracking-widest mb-1">Exposure</span>
                        <p class="text-xl font-black text-gray-900 dark:text-white">₹<?php echo number_format($row['amount'], 0); ?></p>
                    </div>
                    <?php if($isInst): ?>
                    <div class="text-center">
                        <span class="block text-[9px] font-black text-brand-600 uppercase tracking-widest mb-1">Monthly EMI</span>
                        <p class="text-xl font-black text-brand-600">₹<?php echo number_format($row['emi_amount'], 0); ?></p>
                    </div>
                    
                    <?php if($row['emi_amount'] > 0): 
                        $remAmt = round($row['amount'] - $row['paid_amount'], 2);
                        // If fully paid, force 0
                        $estMonths = ($remAmt <= 0) ? 0 : ceil($remAmt / $row['emi_amount']);
                    ?>
                    <div class="text-center">
                        <span class="block text-[9px] font-black text-gray-400 uppercase tracking-widest mb-1">Est. Left</span>
                        <p class="text-xl font-black text-gray-600 dark:text-gray-400"><?php echo max(0, $estMonths); ?> <span class="text-xs">mths</span></p>
                    </div>
                    <?php endif; ?>
                    <?php endif; ?>
                    
                    <!-- Progress (Unified Logic) -->
                    <?php 
                        $paidVal = $isInst ? $paidMonths : number_format($row['paid_amount'], 0);
                        $totalVal = $isInst ? $totalMonths . ' mths' : '₹'.number_format($row['amount'], 0);
                        $progress = $isInst 
                            ? ($totalMonths > 0 ? round(($paidMonths / $totalMonths) * 100) : 0) 
                            : ($row['amount'] > 0 ? round(($row['paid_amount'] / $row['amount']) * 100) : 0); 
                    ?>
                    <div class="w-48">
                        <div class="flex justify-between items-end mb-2">
                            <span class="text-[9px] font-black text-gray-400 uppercase tracking-widest">
                                <?php echo $isInst ? "Cycle: $paidVal/$totalVal" : "Paid: ₹$paidVal / $totalVal"; ?>
                            </span>
                            <span class="text-[10px] font-black <?php echo $progress >= 100 ? 'text-emerald-500' : 'text-brand-500'; ?>"><?php echo min(100, $progress); ?>%</span>
                        </div>
                        <div class="w-full bg-gray-100 dark:bg-gray-900 rounded-full h-2 overflow-hidden ring-1 ring-gray-200 dark:ring-gray-800">
                            <div class="<?php echo $progress >= 100 ? 'bg-emerald-500' : 'bg-brand-500'; ?> h-full rounded-full transition-all duration-1000" style="width: <?php echo min(100, $progress); ?>%"></div>
                        </div>
                    </div>
                </div>

                <!-- Docs & Actions -->
                <div class="flex items-center space-x-3">
                    <!-- Sanction Doc -->
                    <?php if(!empty($row['sanction_doc'])): ?>
                        <a href="../uploads/loans/<?php echo $row['sanction_doc']; ?>" target="_blank" class="p-3 bg-gray-50 dark:bg-gray-900 text-gray-500 rounded-xl hover:bg-brand-50 hover:text-brand-600 transition ring-1 ring-gray-100 dark:ring-gray-800" title="View Sanction Doc">📄</a>
                    <?php else: ?>
                        <button onclick="openUploadModal(<?php echo $row['id']; ?>, 'sanction_doc')" class="p-3 bg-gray-50 dark:bg-gray-900 text-gray-300 rounded-xl hover:text-brand-500 transition ring-1 ring-gray-100 dark:ring-gray-800" title="Upload Sanction Doc">📁</button>
                    <?php endif; ?>

                    <!-- Clearance Doc -->
                    <?php if(!empty($row['clearance_doc'])): ?>
                        <a href="../uploads/loans/<?php echo $row['clearance_doc']; ?>" target="_blank" class="p-3 bg-emerald-50 dark:bg-emerald-900/20 text-emerald-600 rounded-xl hover:bg-emerald-100 transition ring-1 ring-emerald-100 dark:ring-emerald-900/30" title="View Clearance Cert">📜</a>
                    <?php elseif($row['status'] === 'Settled'): ?>
                        <button onclick="openUploadModal(<?php echo $row['id']; ?>, 'clearance_doc')" class="p-3 bg-emerald-50 dark:bg-emerald-900/20 text-emerald-300 rounded-xl hover:text-emerald-500 transition ring-1 ring-emerald-100 dark:ring-emerald-900/30" title="Upload Clearance Cert">📁</button>
                    <?php endif; ?>

                    <!-- Logic Actions -->
                    <?php if($row['status'] === 'Pending'): ?>
                        <button onclick="openPayModal(<?php echo $row['id']; ?>, <?php echo $paidMonths; ?>, <?php echo $totalMonths; ?>, <?php echo $row['emi_amount']; ?>, '<?php echo $row['type']; ?>', '<?php echo addslashes($row['person_name']); ?>', <?php echo $isInst?1:0; ?>)" class="px-4 py-2 bg-brand-600 text-white rounded-xl font-black text-[10px] uppercase shadow-lg shadow-brand-500/20 hover:scale-105 transition">Update Pay</button>
                    <?php else: ?>
                        <span class="px-4 py-2 bg-gray-100 dark:bg-gray-900 text-gray-400 rounded-xl font-black text-[10px] uppercase ring-1 ring-gray-200 dark:ring-gray-800 cursor-not-allowed">Settled</span>
                    <?php endif; ?>
                    
                    <form method="POST" onsubmit="return confirm('Purge record?')" class="inline">
                        <input type="hidden" name="delete_id" value="<?php echo $row['id']; ?>">
                        <button class="p-2 text-red-300 hover:text-red-500 transition text-xl">🗑️</button>
                    </form>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
</div>

<!-- Pay Update Modal -->
<div id="payModal" class="fixed inset-0 bg-black/60 backdrop-blur-sm hidden flex items-center justify-center p-4 z-50">
    <div class="bg-white dark:bg-gray-800 rounded-3xl shadow-2xl w-full max-w-sm p-8 border border-gray-100 dark:border-gray-700 animate-scale-in">
        <h3 class="text-xl font-black mb-6 text-gray-900 dark:text-white tracking-tight">Update Installments</h3>
        <form method="POST" class="space-y-6">
            <input type="hidden" name="update_paid" value="1">
            <input type="hidden" name="loan_id" id="modalLoanId">
            <div>
                <label class="block text-[10px] font-black text-gray-400 uppercase tracking-widest mb-3">Installments Cleared</label>
                <div class="flex items-center space-x-4">
                    <input type="range" id="paidRange" min="0" max="120" step="1" class="w-full h-2 bg-gray-100 dark:bg-gray-900 rounded-lg appearance-none cursor-pointer accent-brand-500" oninput="document.getElementById('paidVal').value = this.value">
                    <input type="number" name="paid_months" id="paidVal" class="w-16 bg-gray-50 dark:bg-gray-900 border-none rounded-xl p-2 text-sm font-black text-center dark:text-white">
                </div>
            </div>
            <div>
                <label class="block text-[10px] font-black text-gray-400 uppercase tracking-widest mb-3">Amount Deducted (₹)</label>
                <div class="relative">
                    <span class="absolute left-3 top-3 text-gray-400 font-bold">₹</span>
                    <input type="number" step="0.01" name="payment_amount" id="modalPaymentAmount" placeholder="0.00" class="w-full bg-gray-50 dark:bg-gray-900 border-none rounded-xl pl-8 p-3 text-sm font-bold focus:ring-2 focus:ring-brand-500 dark:text-white">
                </div>
                <p id="emiHint" class="text-[9px] text-brand-500 font-bold mt-1 pl-1"></p>
            </div>
            <div>
                <label class="block text-[10px] font-black text-gray-400 uppercase tracking-widest mb-3">Transaction Date</label>
                <input type="date" name="payment_date" value="<?php echo date('Y-m-d'); ?>" required class="w-full bg-gray-50 dark:bg-gray-900 border-none rounded-xl p-3 text-sm font-bold focus:ring-2 focus:ring-brand-500 dark:text-white">
            </div>
                <select name="payment_method" id="modalPaymentMethod" onchange="toggleCardSelector()" class="w-full bg-gray-50 dark:bg-gray-900 border-none rounded-xl p-3 text-sm font-bold focus:ring-2 focus:ring-brand-500 dark:text-white">
                    <option value="Bank Account">Bank Account</option>
                    <option value="Credit Card">Credit Card</option>
                    <option value="Cash">Cash</option>
                </select>
            </div>
            <div id="cardSelector" class="hidden">
                <label class="block text-[10px] font-black text-brand-600 uppercase tracking-widest mb-3">Select Credit Card</label>
                <select name="credit_card_name" class="w-full bg-brand-50 dark:bg-brand-900/20 border-none rounded-xl p-3 text-sm font-bold focus:ring-2 focus:ring-brand-500 dark:text-white">
                    <option value="">-- Choose Card --</option>
                    <?php foreach ($creditCards as $card): ?>
                        <option value="<?php echo htmlspecialchars($card['provider_name']); ?>"><?php echo htmlspecialchars($card['provider_name']); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="flex justify-end items-center space-x-3 pt-4 border-t border-gray-100 dark:border-gray-700">
                <button type="button" onclick="closePayModal()" class="px-5 py-2.5 rounded-xl text-xs font-bold text-gray-500 hover:bg-gray-100 dark:text-gray-400 dark:hover:bg-gray-700/50 transition">Discard</button>
                <button type="submit" class="px-5 py-2.5 bg-gray-900 dark:bg-white dark:text-gray-900 text-white rounded-xl text-xs font-black tracking-wide shadow-lg shadow-gray-200 dark:shadow-none hover:scale-105 transition">SECURE LOG</button>
            </div>
        </form>
    </div>
</div>

<!-- Doc Upload Modal -->
<div id="uploadModal" class="fixed inset-0 bg-black/60 backdrop-blur-sm hidden flex items-center justify-center p-4 z-50">
    <div class="bg-white dark:bg-gray-800 rounded-3xl shadow-2xl w-full max-w-sm p-8 border border-gray-100 dark:border-gray-700 animate-scale-in">
        <h3 class="text-xl font-black mb-2 text-gray-900 dark:text-white tracking-tight">Upload Document</h3>
        <p id="docTypeLabel" class="text-[10px] font-black text-brand-500 uppercase tracking-widest mb-8"></p>
        <form method="POST" enctype="multipart/form-data" class="space-y-6">
            <input type="hidden" name="upload_doc" value="1">
            <input type="hidden" name="loan_id" id="uploadLoanId">
            <input type="hidden" name="doc_type" id="uploadDocType">
            <div class="p-8 border-2 border-dashed border-gray-100 dark:border-gray-700 rounded-2xl text-center group hover:border-brand-500 transition-all cursor-pointer relative">
                <input type="file" name="document" required class="absolute inset-0 opacity-0 cursor-pointer">
                <div class="text-4xl mb-4 group-hover:scale-110 transition-transform">📂</div>
                <p class="text-[10px] font-black text-gray-400 uppercase tracking-widest">Strike to Upload PDF/JPG</p>
            </div>
            <div class="flex justify-end space-x-4">
                <button type="button" onclick="closeUploadModal()" class="px-6 py-3 text-gray-400 font-bold hover:text-gray-600 transition">Abort</button>
                <button type="submit" class="px-8 py-3 bg-brand-600 text-white rounded-2xl font-black shadow-lg shadow-brand-500/20 hover:scale-105 transition">FINALIZE</button>
            </div>
        </form>
    </div>
</div>

<script>
document.getElementById('loanType').addEventListener('change', function() {
    const isInst = this.options[this.selectedIndex].dataset.institutional === 'true';
    const advanced = document.getElementById('advancedFields');
    if (isInst) {
        advanced.classList.remove('hidden');
    } else {
        advanced.classList.add('hidden');
    }
});

function calculateEMI() {
    const p = parseFloat(document.getElementById('loanAmount').value) || 0;
    const r = parseFloat(document.getElementById('loanInterest').value) || 0;
    const n = parseInt(document.getElementById('loanTenure').value) || 0;
    
    if (p > 0 && n > 0) {
        if (r > 0) {
            const monthlyRate = (r / 100) / 12;
            const emi = (p * monthlyRate * Math.pow(1 + monthlyRate, n)) / (Math.pow(1 + monthlyRate, n) - 1);
            document.getElementById('loanEmi').value = emi.toFixed(2);
        } else {
            document.getElementById('loanEmi').value = (p / n).toFixed(2);
        }
    } else {
        document.getElementById('loanEmi').value = 0;
    }
}

['loanAmount', 'loanInterest', 'loanTenure'].forEach(id => {
    document.getElementById(id).addEventListener('input', calculateEMI);
});

function openPayModal(id, current, total, emi, type, name, isInst) {
    document.getElementById('modalLoanId').value = id;
    document.getElementById('paidVal').value = current;
    document.getElementById('modalPaymentAmount').value = emi > 0 ? emi : '';
    document.getElementById('emiHint').innerText = emi > 0 ? "Default EMI: ₹" + emi.toLocaleString() + " for " + name : "";
    
    // Hide Month Slider & EMI Hint for Personal Loans
    const sliderContainer = document.getElementById('paidRange').closest('div').parentElement; 
    // The structure is: <div> <label>...</label> <div> <input range>... </div> </div>
    // paidRange.parentElement is the flex container.
    // paidRange.parentElement.parentElement is the container div with the label.

    if (!isInst) {
        document.getElementById('paidRange').parentElement.parentElement.classList.add('hidden');
        document.getElementById('emiHint').style.display = 'none';
        // Also ensure the label is hidden if we used closest
    } else {
        document.getElementById('paidRange').parentElement.parentElement.classList.remove('hidden');
        document.getElementById('emiHint').style.display = 'block';
    }
    
    const range = document.getElementById('paidRange');
    range.max = total || current + 12;
    range.value = current;
    
    // Reset Credit Card selector
    document.getElementById('modalPaymentMethod').value = 'Bank Account';
    toggleCardSelector();
    
    document.getElementById('payModal').classList.remove('hidden');
}
function toggleCardSelector() {
    const method = document.getElementById('modalPaymentMethod').value;
    const selector = document.getElementById('cardSelector');
    if (method === 'Credit Card') {
        selector.classList.remove('hidden');
    } else {
        selector.classList.add('hidden');
    }
}
function closePayModal() { document.getElementById('payModal').classList.add('hidden'); }

function openUploadModal(id, type) {
    document.getElementById('uploadLoanId').value = id;
    document.getElementById('uploadDocType').value = type;
    document.getElementById('docTypeLabel').innerText = type.replace('_', ' ');
    document.getElementById('uploadModal').classList.remove('hidden');
}
function closeUploadModal() { document.getElementById('uploadModal').classList.add('hidden'); }
</script>

<style>
@keyframes scaleIn {
    from { transform: scale(0.9); opacity: 0; }
    to { transform: scale(1); opacity: 1; }
}
.animate-scale-in { animation: scaleIn 0.3s ease-out forwards; }
@keyframes bounceShort {
    0%, 100% { transform: translateY(0); }
    50% { transform: translateY(-5px); }
}
.animate-bounce-short { animation: bounceShort 2s infinite; }
</style>
</script>

<?php require_once '../includes/footer.php'; ?>
