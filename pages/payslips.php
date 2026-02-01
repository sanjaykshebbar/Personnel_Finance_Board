<?php
require_once '../config/database.php';
require_once '../includes/auth.php';
requireLogin();

$pageTitle = 'Payslip Vault';
require_once '../includes/header.php';

$userId = getCurrentUserId();

// Handle Form Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['upload_payslip'])) {
        $file = $_FILES['payslip'];
        $month = $_POST['payslip_month'];
        $uploadDir = __DIR__ . '/../uploads/payslips/';
        if (!is_dir($uploadDir)) mkdir($uploadDir, 0777, true);
        
        $fileName = "payslip_" . $userId . "_" . $month . "_" . time() . ".pdf";
        $targetPath = $uploadDir . $fileName;
        
        if (move_uploaded_file($file['tmp_name'], $targetPath)) {
            $stmt = $pdo->prepare("INSERT INTO payslips (user_id, month, file_path) VALUES (?, ?, ?)");
            $stmt->execute([$userId, $month, $fileName]);
            $_SESSION['flash_message'] = "Payslip stored in vault successfully!";
        } else {
            $_SESSION['flash_message'] = "Failed to store payslip.";
        }
    } elseif (isset($_POST['delete_payslip'])) {
        $stmt = $pdo->prepare("SELECT file_path FROM payslips WHERE id = ? AND user_id = ?");
        $stmt->execute([$_POST['delete_payslip'], $userId]);
        $fp = $stmt->fetchColumn();
        if ($fp) {
            @unlink(__DIR__ . '/../uploads/payslips/' . $fp);
            $stmt = $pdo->prepare("DELETE FROM payslips WHERE id = ? AND user_id = ?");
            $stmt->execute([$_POST['delete_payslip'], $userId]);
            $_SESSION['flash_message'] = "Payslip removed from vault.";
        }
    } elseif (isset($_POST['edit_payslip'])) {
        $id = $_POST['edit_payslip_id'];
        $newMonth = $_POST['edit_payslip_month'];
        $stmt = $pdo->prepare("UPDATE payslips SET month = ? WHERE id = ? AND user_id = ?");
        $stmt->execute([$newMonth, $id, $userId]);
        $_SESSION['flash_message'] = "Payslip month updated successfully.";
    }
    header("Location: payslips.php");
    exit;
}

?>

<style>
    .vault-blur {
        filter: blur(15px);
        opacity: 0.4;
        pointer-events: none;
        user-select: none;
        transition: all 0.5s ease-in-out;
    }
    .vault-unlocked {
        filter: blur(0);
        opacity: 1;
        pointer-events: auto;
        user-select: auto;
    }
    .glass-morphism {
        background: rgba(255, 255, 255, 0.05);
        backdrop-filter: blur(12px);
        -webkit-backdrop-filter: blur(12px);
        border: 1px solid rgba(255, 255, 255, 0.1);
    }
    .dark .glass-morphism {
        background: rgba(0, 0, 0, 0.2);
    }
    @keyframes pulse-ring {
        0% { transform: scale(.7); opacity: 0; }
        50% { opacity: 0.5; }
        100% { transform: scale(1.1); opacity: 0; }
    }
    .pulse-animation {
        animation: pulse-ring 2s cubic-bezier(0.4, 0, 0.6, 1) infinite;
    }
</style>

<div class="max-w-4xl mx-auto space-y-6">
    

    <!-- Vault Header with Toggle -->
    <div class="flex flex-col md:flex-row justify-between items-center bg-white dark:bg-gray-800 p-6 rounded-2xl shadow-sm border border-gray-100 dark:border-gray-700">
        <div class="flex items-center space-x-4 mb-4 md:mb-0">
            <div id="lockStatusIcon" class="p-4 bg-brand-500 rounded-2xl text-white shadow-xl shadow-brand-500/20 transition-all duration-500">
                🔒
            </div>
            <div>
                <h2 class="text-2xl font-black text-gray-900 dark:text-white tracking-tight">Financial Vault</h2>
                <p id="lockStatusText" class="text-xs font-bold text-gray-400 uppercase tracking-widest">Vault Securely Locked</p>
            </div>
        </div>
        
        <label class="relative inline-flex items-center cursor-pointer group">
            <input type="checkbox" id="vaultToggle" class="sr-only peer">
            <div class="w-14 h-7 bg-gray-200 peer-focus:outline-none dark:bg-gray-700 peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-0.5 after:left-[4px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-6 after:w-6 after:transition-all dark:border-gray-600 peer-checked:bg-brand-600 rounded-full transition-all"></div>
            <span class="ml-3 text-sm font-bold text-gray-700 dark:text-gray-300 group-hover:text-brand-600 transition-colors uppercase tracking-tighter">Unlock Access</span>
        </label>
    </div>

    <!-- Main Content Area -->
    <div class="relative min-h-[500px]">
        <!-- Locked Overlay -->
        <div id="lockedOverlay" class="absolute inset-0 z-20 flex flex-col items-center justify-center text-center p-8 transition-all duration-500">
            <div class="relative">
                <div class="absolute inset-0 pulse-animation bg-brand-500 rounded-full opacity-0"></div>
                <div class="w-24 h-24 bg-brand-50 dark:bg-brand-900/20 rounded-full flex items-center justify-center text-5xl mb-6 relative z-10 border border-brand-200 dark:border-brand-800">
                    📂
                </div>
            </div>
            <h3 class="text-2xl font-bold text-gray-900 dark:text-white mb-2">Vault is Encrypted</h3>
            <p class="text-gray-500 max-w-xs mx-auto text-sm">Your financial documents are protected. Toggle the switch above to reveal your stored payslips.</p>
        </div>

        <!-- Content (Blurred by default) -->
        <div id="vaultContent" class="vault-blur grid grid-cols-1 lg:grid-cols-3 gap-6">
            <!-- Upload Form -->
            <div class="lg:col-span-1 bg-white dark:bg-gray-800 shadow rounded-2xl p-6 border border-gray-100 dark:border-gray-700 transition-colors h-fit sticky top-6">
                <div class="flex items-center justify-between mb-6">
                    <div>
                        <h3 class="text-lg font-bold text-gray-900 dark:text-white">Store New</h3>
                        <p class="text-[10px] text-gray-500 font-bold uppercase tracking-wider">Document Ingestion</p>
                    </div>
                </div>
                
                <form method="POST" enctype="multipart/form-data" class="space-y-4">
                    <input type="hidden" name="upload_payslip" value="1">
                    <div>
                        <label class="block text-xs font-bold text-gray-400 uppercase mb-2">Target Month</label>
                        <input type="month" name="payslip_month" required 
                               class="w-full text-sm rounded-xl border-gray-200 dark:border-gray-700 dark:bg-gray-900 dark:text-white p-3 outline-none focus:ring-2 focus:ring-brand-500 shadow-sm transition-all text-center font-bold">
                    </div>
                    <div class="group relative bg-gray-50 dark:bg-gray-900 border-2 border-dashed border-gray-200 dark:border-gray-800 p-8 rounded-2xl transition-all hover:border-brand-500/50 hover:bg-brand-50/10">
                        <input type="file" name="payslip" accept=".pdf" required 
                               class="absolute inset-0 w-full h-full opacity-0 cursor-pointer z-10">
                        <div class="text-center space-y-2">
                            <span class="text-3xl block transition-transform group-hover:scale-110">📤</span>
                            <span class="text-xs font-bold text-gray-500 block uppercase tracking-tight">Drop PDF or Click</span>
                        </div>
                    </div>
                    <button type="submit" class="w-full bg-brand-600 text-white py-4 rounded-xl text-sm font-bold hover:bg-brand-700 transition-all shadow-xl shadow-brand-600/20 active:scale-95">
                        Verify & Store →
                    </button>
                </form>
            </div>

            <!-- Files List -->
            <div class="lg:col-span-2 bg-white dark:bg-gray-800 shadow rounded-2xl p-6 border border-gray-100 dark:border-gray-700 transition-colors">
                <div class="flex items-center justify-between mb-6">
                    <div>
                        <h3 class="text-lg font-bold text-gray-900 dark:text-white">Vault Inventory</h3>
                        <p class="text-[10px] text-gray-400 font-bold uppercase tracking-wider">Encrypted Archive</p>
                    </div>
                    <div class="px-3 py-1 bg-gray-100 dark:bg-gray-900 rounded-full text-[10px] font-black text-gray-500 uppercase tracking-tighter">
                        <?php 
                        $countStmt = $pdo->prepare("SELECT COUNT(*) FROM payslips WHERE user_id = ?");
                        $countStmt->execute([$userId]);
                        echo $countStmt->fetchColumn(); 
                        ?> Items
                    </div>
                </div>
                
                <div class="space-y-3 custom-scrollbar">
                    <?php
                    $psStmt = $pdo->prepare("SELECT * FROM payslips WHERE user_id = ? ORDER BY month DESC");
                    $psStmt->execute([$userId]);
                    $payslips = $psStmt->fetchAll();
                    foreach ($payslips as $ps):
                    ?>
                    <div class="flex flex-col p-4 bg-gray-50 dark:bg-gray-900/50 border border-transparent dark:border-gray-800 rounded-2xl text-sm group transition-all hover:border-brand-500/50 hover:shadow-lg hover:shadow-brand-500/5">
                        <div class="flex justify-between items-center" id="view-ps-<?php echo $ps['id']; ?>">
                            <div class="flex items-center space-x-4">
                                <div class="w-12 h-12 bg-white dark:bg-gray-800 rounded-xl flex items-center justify-center text-xl shadow-sm border border-gray-100 dark:border-gray-700 transition-transform group-hover:rotate-6">
                                    📄
                                </div>
                                <div>
                                    <span class="block font-black text-gray-900 dark:text-white text-base tracking-tight"><?php echo date('F Y', strtotime($ps['month'])); ?></span>
                                    <span class="text-[10px] text-gray-400 font-bold tracking-tighter uppercase opacity-60"><?php echo htmlspecialchars($ps['file_path']); ?></span>
                                </div>
                            </div>
                            <div class="flex space-x-2">
                                <button onclick="togglePsEdit(<?php echo $ps['id']; ?>)" class="p-2.5 text-gray-400 hover:text-brand-600 hover:bg-brand-50 dark:hover:bg-brand-900/20 rounded-xl transition-all" title="Edit Metadata">
                                    ✏️
                                </button>
                                <a href="../uploads/payslips/<?php echo $ps['file_path']; ?>" download class="p-2.5 text-brand-600 hover:bg-brand-600 hover:text-white bg-brand-50 dark:bg-brand-900/20 dark:hover:bg-brand-600 rounded-xl transition-all" title="Secure Export">
                                    📥
                                </a>
                                <form method="POST" onsubmit="return confirm('Archive deletion is permanent. Continue?');" class="inline">
                                    <input type="hidden" name="delete_payslip" value="<?php echo $ps['id']; ?>">
                                    <button type="submit" class="p-2.5 text-red-400 hover:text-white hover:bg-red-500 rounded-xl transition-all" title="System Scrub">
                                        🗑️
                                    </button>
                                </form>
                            </div>
                        </div>
                        <!-- Inline Edit Form -->
                        <form method="POST" class="hidden flex items-center space-x-3 pt-4 mt-4 border-t border-gray-100 dark:border-gray-800" id="edit-ps-<?php echo $ps['id']; ?>">
                            <input type="hidden" name="edit_payslip" value="1">
                            <input type="hidden" name="edit_payslip_id" value="<?php echo $ps['id']; ?>">
                            <input type="month" name="edit_payslip_month" value="<?php echo $ps['month']; ?>" 
                                   class="flex-grow text-xs font-bold border-gray-100 dark:border-gray-700 dark:bg-gray-900 dark:text-white rounded-xl p-3 outline-none focus:ring-2 focus:ring-brand-500 shadow-inner">
                            <button type="submit" class="bg-brand-600 text-white px-6 py-3 rounded-xl text-xs font-black hover:bg-brand-700 transition shadow-lg shadow-brand-600/20 uppercase tracking-tighter">Save</button>
                            <button type="button" onclick="togglePsEdit(<?php echo $ps['id']; ?>)" class="text-[10px] font-bold text-gray-400 uppercase hover:text-gray-600 tracking-widest px-2">X</button>
                        </form>
                    </div>
                    <?php endforeach; ?>
                    
                    <?php if(empty($payslips)): ?>
                        <div class="text-center py-20">
                            <div class="text-6xl mb-6 opacity-20">📥</div>
                            <h4 class="text-lg font-bold text-gray-400 uppercase tracking-widest mb-1">Archive Empty</h4>
                            <p class="text-xs text-gray-400 italic">No records found for current user.</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
const vaultToggle = document.getElementById('vaultToggle');
const vaultContent = document.getElementById('vaultContent');
const lockedOverlay = document.getElementById('lockedOverlay');
const lockStatusIcon = document.getElementById('lockStatusIcon');
const lockStatusText = document.getElementById('lockStatusText');

vaultToggle.addEventListener('change', function() {
    if (this.checked) {
        vaultContent.classList.add('vault-unlocked');
        lockedOverlay.classList.add('opacity-0', 'scale-95', 'pointer-events-none');
        lockStatusIcon.innerHTML = '🔓';
        lockStatusIcon.classList.remove('bg-brand-500');
        lockStatusIcon.classList.add('bg-emerald-500');
        lockStatusText.innerText = 'Vault Access Granted';
        lockStatusText.classList.remove('text-gray-400');
        lockStatusText.classList.add('text-emerald-500');
        
        // Micro-interaction: temporarily highlight items
        setTimeout(() => {
            const items = document.querySelectorAll('.group');
            items.forEach((item, index) => {
                setTimeout(() => {
                    item.classList.add('scale-[1.02]');
                    setTimeout(() => item.classList.remove('scale-[1.02]'), 200);
                }, index * 50);
            });
        }, 300);

    } else {
        vaultContent.classList.remove('vault-unlocked');
        lockedOverlay.classList.remove('opacity-0', 'scale-95', 'pointer-events-none');
        lockStatusIcon.innerHTML = '🔒';
        lockStatusIcon.classList.remove('bg-emerald-500');
        lockStatusIcon.classList.add('bg-brand-500');
        lockStatusText.innerText = 'Vault Securely Locked';
        lockStatusText.classList.remove('text-emerald-500');
        lockStatusText.classList.add('text-gray-400');
    }
});

function togglePsEdit(id) {
    const view = document.getElementById('view-ps-' + id);
    const edit = document.getElementById('edit-ps-' + id);
    view.classList.toggle('hidden');
    edit.classList.toggle('hidden');
}
</script>

<?php require_once '../includes/footer.php'; ?>
