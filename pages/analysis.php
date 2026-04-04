<?php
require_once '../config/database.php';
require_once '../includes/auth.php';
require_once '../includes/classification.php';

requireLogin();
$userId = getCurrentUserId();
$pageTitle = 'Analysis & Messages';

// --- Actions ---

// 1. Mark as Read
if (isset($_GET['mark_read'])) {
    $stmt = $pdo->prepare("UPDATE messages SET is_read = 1 WHERE id = ? AND user_id = ?");
    $stmt->execute([$_GET['mark_read'], $userId]);
    header("Location: analysis.php" . (isset($_GET['category']) ? "?category=" . $_GET['category'] : ""));
    exit;
}

// 2. Delete Message
if (isset($_POST['delete_id'])) {
    $stmt = $pdo->prepare("DELETE FROM messages WHERE id = ? AND user_id = ?");
    $stmt->execute([$_POST['delete_id'], $userId]);
    $_SESSION['flash_message'] = "Message deleted. 🗑️";
    header("Location: analysis.php");
    exit;
}

// 3. Clear All
if (isset($_POST['clear_all'])) {
    $stmt = $pdo->prepare("DELETE FROM messages WHERE user_id = ?");
    $stmt->execute([$userId]);
    $_SESSION['flash_message'] = "Inbox cleared. 🧹";
    header("Location: analysis.php");
    exit;
}

// 4. Convert to Expense (AJAX/POST)
if (isset($_POST['convert_expense'])) {
    $date = $_POST['date'] ?? date('Y-m-d');
    $category = $_POST['category'] ?? 'Other';
    $description = $_POST['description'] ?? '';
    $amount = filter_input(INPUT_POST, 'amount', FILTER_VALIDATE_FLOAT);
    $paymentMethod = $_POST['payment_method'] ?? 'Cash';
    $messageId = $_POST['message_id'] ?? null;

    if ($amount > 0) {
        $stmt = $pdo->prepare("INSERT INTO expenses (user_id, date, category, description, amount, payment_method) VALUES (?, ?, ?, ?, ?, ?)");
        if ($stmt->execute([$userId, $date, $category, $description, $amount, $paymentMethod])) {
            // Also mark message as read
            if ($messageId) {
                $pdo->prepare("UPDATE messages SET is_read = 1 WHERE id = ? AND user_id = ?")->execute([$messageId, $userId]);
            }
            $_SESSION['flash_message'] = "Expense recorded! 💸";
        }
    }
    header("Location: analysis.php");
    exit;
}

// 5. Convert to Income
if (isset($_POST['convert_income'])) {
    $date = $_POST['date'] ?? date('Y-m-d');
    $source = $_POST['source'] ?? 'Salary';
    $description = $_POST['description'] ?? '';
    $amount = filter_input(INPUT_POST, 'amount', FILTER_VALIDATE_FLOAT);
    $messageId = $_POST['message_id'] ?? null;

    if ($amount > 0) {
        $stmt = $pdo->prepare("INSERT INTO income (user_id, date, source, amount, description) VALUES (?, ?, ?, ?, ?)");
        if ($stmt->execute([$userId, $date, $source, $amount, $description])) {
            if ($messageId) {
                $pdo->prepare("UPDATE messages SET is_read = 1 WHERE id = ? AND user_id = ?")->execute([$messageId, $userId]);
            }
            $_SESSION['flash_message'] = "Income recorded! 💰";
        }
    }
    header("Location: analysis.php");
    exit;
}

// --- Data Fetching ---

$currentCategory = $_GET['category'] ?? 'all';
$search = $_GET['q'] ?? '';

$sql = "SELECT * FROM messages WHERE user_id = ?";
$params = [$userId];

if ($currentCategory !== 'all') {
    $sql .= " AND category = ?";
    $params[] = $currentCategory;
}

if ($search) {
    $sql .= " AND (message_text LIKE ? OR sender LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

$sql .= " ORDER BY timestamp DESC, id DESC";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$messages = $stmt->fetchAll();

// Get category counts
$countStmt = $pdo->prepare("SELECT category, COUNT(*) as count FROM messages WHERE user_id = ? GROUP BY category");
$countStmt->execute([$userId]);
$categoryCounts = $countStmt->fetchAll(PDO::FETCH_KEY_PAIR);
$totalUnread = $pdo->query("SELECT COUNT(*) FROM messages WHERE user_id = $userId AND is_read = 0")->fetchColumn();

require_once '../includes/header.php';
?>

<div class="h-[calc(100vh-12rem)] flex flex-col md:flex-row bg-white dark:bg-gray-900 rounded-3xl overflow-hidden border border-gray-100 dark:border-gray-800 shadow-xl">
    
    <!-- Left Column: Navigation & Filters (Outlook style) -->
    <div class="w-full md:w-64 border-r border-gray-100 dark:border-gray-800 flex flex-col bg-gray-50/50 dark:bg-gray-900/50">
        <div class="p-4 border-b border-gray-100 dark:border-gray-800">
            <h3 class="text-xs font-black text-gray-400 uppercase tracking-widest mb-4">Mailboxes</h3>
            <nav class="space-y-1">
                <a href="?category=all" class="flex items-center justify-between px-3 py-2 rounded-xl text-sm font-bold <?php echo $currentCategory === 'all' ? 'bg-brand-500 text-white shadow-lg shadow-brand-500/20' : 'text-gray-600 dark:text-gray-400 hover:bg-gray-100 dark:hover:bg-gray-800'; ?>">
                    <span class="flex items-center gap-2">📥 All Inbox</span>
                    <?php if ($totalUnread > 0): ?>
                        <span class="px-2 py-0.5 rounded-full bg-brand-600 text-[10px] text-white"><?php echo $totalUnread; ?></span>
                    <?php endif; ?>
                </a>
            </nav>
        </div>
        
        <div class="flex-1 overflow-y-auto p-4">
            <h3 class="text-xs font-black text-gray-400 uppercase tracking-widest mb-4">Smart Categories</h3>
            <nav class="space-y-1">
                <?php 
                $allCats = ['Bank_Transactions', 'Credit_Card', 'OTP', 'Loan_EMI', 'Investment_Trading', 'Utility_Bills', 'Spam_Promotions', 'UNCATEGORIZED'];
                foreach ($allCats as $cat): 
                    $count = $categoryCounts[$cat] ?? 0;
                    if ($count === 0 && $currentCategory !== $cat) continue;
                ?>
                <a href="?category=<?php echo $cat; ?>" class="flex items-center justify-between px-3 py-2 rounded-xl text-sm font-medium <?php echo $currentCategory === $cat ? 'bg-gray-200 dark:bg-gray-800 text-gray-900 dark:text-white' : 'text-gray-600 dark:text-gray-400 hover:bg-gray-100 dark:hover:bg-gray-800'; ?>">
                    <span class="flex items-center gap-2">
                        <?php 
                        switch($cat) {
                            case 'Bank_Transactions': echo '🏦'; break;
                            case 'Credit_Card': echo '💳'; break;
                            case 'OTP': echo '🔑'; break;
                            case 'Loan_EMI': echo '🏠'; break;
                            case 'Investment_Trading': echo '📈'; break;
                            case 'Utility_Bills': echo '⚡'; break;
                            case 'Spam_Promotions': echo '🏷️'; break;
                            default: echo '✉️';
                        }
                        ?>
                        <?php echo str_replace('_', ' ', $cat); ?>
                    </span>
                    <span class="text-[10px] font-bold text-gray-400"><?php echo $count; ?></span>
                </a>
                <?php endforeach; ?>
            </nav>
        </div>

        <!-- Actions Footer -->
        <div class="p-4 border-t border-gray-100 dark:border-gray-800 bg-white dark:bg-gray-900">
            <button onclick="document.getElementById('uploadModal').classList.remove('hidden')" class="w-full flex items-center justify-center gap-2 px-4 py-2 bg-brand-500 hover:bg-brand-600 text-white rounded-xl text-xs font-bold transition-all active:scale-95 shadow-lg shadow-brand-500/20">
                📤 Import History
            </button>
            <form method="POST" onsubmit="return confirm('Clear all messages?')">
                <button type="submit" name="clear_all" class="w-full mt-2 px-4 py-2 border border-gray-200 dark:border-gray-700 text-gray-500 hover:text-red-500 hover:border-red-500 rounded-xl text-[10px] font-black uppercase tracking-wider transition-all">
                    Clear Inbox
                </button>
            </form>
        </div>
    </div>

    <!-- Middle Column: Message List -->
    <div class="flex-1 border-r border-gray-100 dark:border-gray-800 flex flex-col bg-white dark:bg-gray-900">
        <div class="p-4 border-b border-gray-100 dark:border-gray-800 flex items-center gap-2">
            <form method="GET" class="flex-1 relative">
                <input type="hidden" name="category" value="<?php echo $currentCategory; ?>">
                <input type="text" name="q" value="<?php echo htmlspecialchars($search); ?>" placeholder="Search messages..." class="w-full pl-9 pr-4 py-2 bg-gray-50 dark:bg-gray-800 border-none rounded-2xl text-sm focus:ring-2 focus:ring-brand-500">
                <span class="absolute left-3 top-1/2 -translate-y-1/2 text-gray-400">🔍</span>
            </form>
        </div>

        <div class="flex-1 overflow-y-auto divide-y divide-gray-50 dark:divide-gray-800">
            <?php if (empty($messages)): ?>
                <div class="flex flex-col items-center justify-center h-full text-gray-400 p-8 text-center">
                    <span class="text-4xl mb-4">🏝️</span>
                    <p class="font-bold">No messages found</p>
                    <p class="text-xs mt-1">Try uploading your SMS backup or check other categories.</p>
                </div>
            <?php else: ?>
                <?php foreach ($messages as $msg): ?>
                    <div onclick="selectMessage(<?php echo htmlspecialchars(json_encode($msg)); ?>)" 
                         class="p-4 cursor-pointer transition-all hover:bg-brand-50/50 dark:hover:bg-brand-900/10 <?php echo !$msg['is_read'] ? 'border-l-4 border-brand-500 bg-brand-50/30 dark:bg-brand-900/5' : ''; ?> group relative">
                        <div class="flex justify-between items-start mb-1">
                            <span class="font-bold text-gray-900 dark:text-white truncate max-w-[140px]"><?php echo htmlspecialchars($msg['sender']); ?></span>
                            <span class="text-[10px] text-gray-400"><?php echo date('H:i', strtotime($msg['timestamp'])); ?></span>
                        </div>
                        <p class="text-xs text-gray-500 dark:text-gray-400 line-clamp-2 leading-relaxed"><?php echo htmlspecialchars($msg['message_text']); ?></p>
                        <div class="mt-2 flex items-center gap-2">
                            <span class="text-[9px] px-1.5 py-0.5 rounded-md font-bold uppercase tracking-wider bg-gray-100 dark:bg-gray-800 text-gray-500"><?php echo str_replace('_', ' ', $msg['category']); ?></span>
                            <?php if ($msg['is_staged']): ?>
                                <span class="text-[9px] px-1.5 py-0.5 rounded-md font-bold uppercase tracking-wider bg-amber-100 dark:bg-amber-900/30 text-amber-600 dark:text-amber-400">⚡ Staged</span>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>

    <!-- Right Column: Detail View (Outlook style) -->
    <div id="detailView" class="hidden md:flex w-full md:w-[400px] xl:w-[500px] flex-col bg-white dark:bg-gray-900">
        <div id="noDetail" class="flex flex-col items-center justify-center h-full text-gray-300 dark:text-gray-700">
            <span class="text-6xl mb-4">📖</span>
            <p class="font-bold">Select a message to view</p>
        </div>

        <div id="detailContent" class="hidden h-full flex-col">
            <div class="p-6 border-b border-gray-100 dark:border-gray-800">
                <div class="flex justify-between items-start mb-6">
                    <div class="flex items-center gap-4">
                        <div class="h-12 w-12 rounded-2xl bg-brand-100 dark:bg-brand-900/30 flex items-center justify-center text-brand-600 dark:text-brand-400 text-xl font-bold" id="detailIcon">
                            S
                        </div>
                        <div>
                            <h2 class="text-xl font-black text-gray-900 dark:text-white" id="detailSender">Sender</h2>
                            <p class="text-xs text-gray-500" id="detailDate">Date</p>
                        </div>
                    </div>
                    <div class="flex gap-2">
                        <a id="markReadBtn" href="#" class="p-2 text-gray-400 hover:text-brand-500 transition-colors" title="Mark as Read">✔️</a>
                        <form method="POST" onsubmit="return confirm('Delete message?')" class="inline">
                            <input type="hidden" name="delete_id" id="deleteId">
                            <button type="submit" class="p-2 text-gray-400 hover:text-red-500 transition-colors">🗑️</button>
                        </form>
                    </div>
                </div>

                <div class="bg-gray-50 dark:bg-gray-800/50 p-6 rounded-3xl mb-6">
                    <p class="text-sm text-gray-800 dark:text-gray-200 leading-relaxed font-medium" id="detailText">
                        Select a message...
                    </p>
                </div>

                <div class="flex items-center gap-2">
                    <span class="text-xs font-bold text-gray-400 uppercase tracking-widest">Category:</span>
                    <span id="detailCategory" class="px-3 py-1 bg-brand-500 text-white text-[10px] font-black rounded-lg shadow-lg shadow-brand-500/20">OTP</span>
                </div>
            </div>

            <!-- Smart Actions Panel -->
            <div class="flex-1 p-6 overflow-y-auto space-y-6">
                <!-- Staging Alert -->
                <div id="stagedAlert" class="hidden p-4 bg-amber-50 dark:bg-amber-900/10 border border-amber-100 dark:border-amber-900/30 rounded-2xl flex items-center gap-3">
                    <span class="text-xl">⚡</span>
                    <div>
                        <p class="text-xs font-bold text-amber-800 dark:text-amber-300">Staged in Quick Update</p>
                        <p class="text-[10px] text-amber-600 dark:text-amber-400">This transaction is pending verification. <a href="quick_add.php" class="underline font-bold">Go to Quick Update</a></p>
                    </div>
                </div>

                <div id="expensePanel" class="bg-brand-50 dark:bg-brand-900/10 border border-brand-100 dark:border-brand-900/30 rounded-3xl p-6">
                    <div class="flex border-b border-brand-100 dark:border-brand-900/30 mb-6">
                        <button onclick="toggleAction('expense')" id="tabExpense" class="flex-1 py-2 text-xs font-black uppercase tracking-widest border-b-2 border-brand-500 text-brand-600">Expense</button>
                        <button onclick="toggleAction('income')" id="tabIncome" class="flex-1 py-2 text-xs font-black uppercase tracking-widest border-b-2 border-transparent text-gray-400">Income</button>
                    </div>

                    <!-- Expense Form -->
                    <form id="formExpense" method="POST" action="analysis.php" class="space-y-4">
                        <input type="hidden" name="convert_expense" value="1">
                        <input type="hidden" name="message_id" id="expenseMsgId">
                        
                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label class="block text-[10px] font-black text-gray-400 uppercase mb-1">Amount</label>
                                <div class="relative">
                                    <span class="absolute left-3 top-1/2 -translate-y-1/2 text-brand-600 font-bold">₹</span>
                                    <input type="number" step="0.01" name="amount" id="expenseAmount" class="w-full pl-8 py-2 bg-white dark:bg-gray-900 border-none rounded-xl text-sm font-bold focus:ring-2 focus:ring-brand-500 shadow-sm" required>
                                </div>
                            </div>
                            <div>
                                <label class="block text-[10px] font-black text-gray-400 uppercase mb-1">Date</label>
                                <input type="date" name="date" id="expenseDate" class="w-full py-2 bg-white dark:bg-gray-900 border-none rounded-xl text-sm focus:ring-2 focus:ring-brand-500 shadow-sm" required>
                            </div>
                        </div>

                        <div>
                            <label class="block text-[10px] font-black text-gray-400 uppercase mb-1">Category</label>
                            <select name="category" id="expenseCategory" class="w-full py-2 bg-white dark:bg-gray-900 border-none rounded-xl text-sm focus:ring-2 focus:ring-brand-500 shadow-sm">
                                <option value="Food & Dining">Food & Dining</option>
                                <option value="Shopping">Shopping</option>
                                <option value="Bills & Utilities">Bills & Utilities</option>
                                <option value="Investments">Investments</option>
                                <option value="Travel">Travel</option>
                                <option value="Entertainment">Entertainment</option>
                                <option value="Other">Other</option>
                            </select>
                        </div>

                        <div>
                            <label class="block text-[10px] font-black text-gray-400 uppercase mb-1">Description</label>
                            <input type="text" name="description" id="expenseDesc" class="w-full py-2 bg-white dark:bg-gray-900 border-none rounded-xl text-sm focus:ring-2 focus:ring-brand-500 shadow-sm">
                        </div>

                        <button type="submit" class="w-full py-3 bg-brand-600 hover:bg-brand-700 text-white font-bold rounded-2xl shadow-xl shadow-brand-500/20 transition-all active:scale-95">
                            Record Expense 💸
                        </button>
                    </form>

                    <!-- Income Form -->
                    <form id="formIncome" method="POST" action="analysis.php" class="space-y-4 hidden">
                        <input type="hidden" name="convert_income" value="1">
                        <input type="hidden" name="message_id" id="incomeMsgId">
                        
                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label class="block text-[10px] font-black text-gray-400 uppercase mb-1">Amount</label>
                                <div class="relative">
                                    <span class="absolute left-3 top-1/2 -translate-y-1/2 text-emerald-600 font-bold">₹</span>
                                    <input type="number" step="0.01" name="amount" id="incomeAmount" class="w-full pl-8 py-2 bg-white dark:bg-gray-900 border-none rounded-xl text-sm font-bold focus:ring-2 focus:ring-emerald-500 shadow-sm" required>
                                </div>
                            </div>
                            <div>
                                <label class="block text-[10px] font-black text-gray-400 uppercase mb-1">Date</label>
                                <input type="date" name="date" id="incomeDate" class="w-full py-2 bg-white dark:bg-gray-900 border-none rounded-xl text-sm focus:ring-2 focus:ring-emerald-500 shadow-sm" required>
                            </div>
                        </div>

                        <div>
                            <label class="block text-[10px] font-black text-gray-400 uppercase mb-1">Source</label>
                            <input type="text" name="source" id="incomeSource" placeholder="Salary, Bonus, Refund..." class="w-full py-2 bg-white dark:bg-gray-900 border-none rounded-xl text-sm font-bold focus:ring-2 focus:ring-emerald-500 shadow-sm">
                        </div>

                        <div>
                            <label class="block text-[10px] font-black text-gray-400 uppercase mb-1">Description</label>
                            <input type="text" name="description" id="incomeDesc" class="w-full py-2 bg-white dark:bg-gray-900 border-none rounded-xl text-sm focus:ring-2 focus:ring-emerald-500 shadow-sm">
                        </div>

                        <button type="submit" class="w-full py-3 bg-emerald-600 hover:bg-emerald-700 text-white font-bold rounded-2xl shadow-xl shadow-emerald-500/20 transition-all active:scale-95">
                            Record Income 💰
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Upload Modal -->
<div id="uploadModal" class="hidden fixed inset-0 z-50 flex items-center justify-center bg-black/60 backdrop-blur-sm p-4">
    <div class="bg-white dark:bg-gray-900 rounded-[2.5rem] w-full max-w-md overflow-hidden shadow-2xl">
        <div class="p-8 border-b border-gray-100 dark:border-gray-800 flex justify-between items-center">
            <div>
                <h2 class="text-2xl font-black text-gray-900 dark:text-white">Import History</h2>
                <p class="text-xs text-gray-500">JSON/CSV Message Backup</p>
            </div>
            <button onclick="document.getElementById('uploadModal').classList.add('hidden')" class="p-2 text-gray-400 hover:text-red-500 transition-colors">✕</button>
        </div>
        <div class="p-8 space-y-6">
            <div class="p-12 border-2 border-dashed border-gray-200 dark:border-gray-700 rounded-3xl flex flex-col items-center text-center group hover:border-brand-500 transition-all">
                <span class="text-4xl mb-4 group-hover:scale-110 transition-transform">📂</span>
                <p class="text-sm font-bold text-gray-600 dark:text-gray-400">Drag or click to select file</p>
                <input type="file" id="fileInput" class="hidden" onchange="handleFileUpload(this)">
                <button onclick="document.getElementById('fileInput').click()" class="mt-4 px-6 py-2 bg-gray-100 dark:bg-gray-800 rounded-xl text-xs font-bold hover:bg-gray-200 transition-colors">Select File</button>
            </div>
            <p id="uploadStatus" class="text-center text-xs font-bold text-brand-600 hidden">Processing...</p>
        </div>
    </div>
</div>

<script>
function selectMessage(msg) {
    document.getElementById('noDetail').classList.add('hidden');
    document.getElementById('detailContent').classList.remove('hidden');
    document.getElementById('detailView').classList.remove('hidden'); // For mobile if we toggle

    document.getElementById('detailSender').innerText = msg.sender;
    document.getElementById('detailText').innerText = msg.message_text;
    document.getElementById('detailDate').innerText = new Date(msg.timestamp).toLocaleString();
    document.getElementById('detailCategory').innerText = msg.category.replace('_', ' ');
    document.getElementById('detailIcon').innerText = msg.sender.substring(0, 1).toUpperCase();
    document.getElementById('deleteId').value = msg.id;
    document.getElementById('markReadBtn').href = '?mark_read=' + msg.id + '&category=<?php echo $currentCategory; ?>';

    // Auto-fill forms
    document.getElementById('expenseMsgId').value = msg.id;
    document.getElementById('incomeMsgId').value = msg.id;
    document.getElementById('expenseDate').value = msg.timestamp.split(' ')[0];
    document.getElementById('incomeDate').value = msg.timestamp.split(' ')[0];
    document.getElementById('expenseDesc').value = msg.sender + ': ' + msg.message_text.substring(0, 30) + '...';
    document.getElementById('incomeDesc').value = msg.sender + ': ' + msg.message_text.substring(0, 30) + '...';
    document.getElementById('incomeSource').value = msg.sender;
    
    // Try to parse amount from text
    const amountMatch = msg.message_text.match(/(?:Rs|INR|₹)\s?(\d+(?:\.\d+)?)/i) || msg.message_text.match(/(\d+(?:\.\d+)?)\s?(?:debited|credited)/i);
    const amount = amountMatch ? amountMatch[1] : '';
    document.getElementById('expenseAmount').value = amount;
    document.getElementById('incomeAmount').value = amount;

    // Smart category mapping
    if (msg.category === 'Food & Dining') document.getElementById('expenseCategory').value = 'Food & Dining';
    else if (msg.category === 'Utility_Bills') document.getElementById('expenseCategory').value = 'Bills & Utilities';
    else if (msg.category === 'Investment_Trading') document.getElementById('expenseCategory').value = 'Investments';
    else document.getElementById('expenseCategory').value = 'Other';

    // Staging Alert
    if (msg.is_staged) {
        document.getElementById('stagedAlert').classList.remove('hidden');
    } else {
        document.getElementById('stagedAlert').classList.add('hidden');
    }

    // Default to Income tab if "credited" is found
    if (msg.message_text.toLowerCase().includes('credited')) {
        toggleAction('income');
    } else {
        toggleAction('expense');
    }
}

function toggleAction(action) {
    if (action === 'expense') {
        document.getElementById('formExpense').classList.remove('hidden');
        document.getElementById('formIncome').classList.add('hidden');
        document.getElementById('tabExpense').className = 'flex-1 py-2 text-xs font-black uppercase tracking-widest border-b-2 border-brand-500 text-brand-600';
        document.getElementById('tabIncome').className = 'flex-1 py-2 text-xs font-black uppercase tracking-widest border-b-2 border-transparent text-gray-400';
    } else {
        document.getElementById('formExpense').classList.add('hidden');
        document.getElementById('formIncome').classList.remove('hidden');
        document.getElementById('tabExpense').className = 'flex-1 py-2 text-xs font-black uppercase tracking-widest border-b-2 border-transparent text-gray-400';
        document.getElementById('tabIncome').className = 'flex-1 py-2 text-xs font-black uppercase tracking-widest border-b-2 border-emerald-500 text-emerald-600';
    }
}

function handleFileUpload(input) {
    if (!input.files || !input.files[0]) return;
    
    const file = input.files[0];
    const status = document.getElementById('uploadStatus');
    status.innerText = 'Reading file...';
    status.classList.remove('hidden');

    const reader = new FileReader();
    reader.onload = function(e) {
        try {
            const data = JSON.parse(e.target.result);
            ingestMessages(data);
        } catch (err) {
            // Try simple CSV if JSON fails (stub for now)
            status.innerText = 'Error: Invalid JSON format. Please use Exported JSON.';
            status.classList.add('text-red-500');
        }
    };
    reader.readAsText(file);
}

function ingestMessages(data) {
    const status = document.getElementById('uploadStatus');
    status.innerText = 'Ingesting ' + (data.length || 1) + ' messages...';
    
    fetch('../api/ingest_messages.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(data)
    })
    .then(res => res.json())
    .then(result => {
        if (result.status === 'success') {
            status.innerText = 'Successfully imported ' + result.inserted + ' messages! Reloading...';
            setTimeout(() => window.location.reload(), 1500);
        } else {
            throw new Error(result.error || 'Upload failed');
        }
    })
    .catch(err => {
        status.innerText = 'Error: ' + err.message;
        status.classList.add('text-red-500');
    });
}
</script>

<?php require_once '../includes/footer.php'; ?>
