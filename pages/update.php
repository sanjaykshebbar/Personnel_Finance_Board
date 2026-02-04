<?php
require_once '../config/database.php';
require_once '../includes/auth.php';
require_once '../config/version.php';
require_once '../includes/UpdateManager.php';

requireLogin();

$userId = getCurrentUserId();
$pageTitle = 'System Update';

// Initialize Update Manager
$updateManager = new UpdateManager();

// Handle AJAX requests for real-time updates
if (isset($_GET['ajax'])) {
    header('Content-Type: application/json');
    
    $action = $_GET['action'] ?? '';
    
    switch ($action) {
        case 'check':
            echo json_encode($updateManager->checkForUpdates());
            break;
        case 'system_info':
            echo json_encode($updateManager->getSystemInfo());
            break;
        case 'backups':
            echo json_encode($updateManager->getBackupList());
            break;
        default:
            echo json_encode(['error' => 'Invalid action']);
    }
    exit;
}

// Handle POST actions
$result = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    switch ($action) {
        case 'update':
            $result = $updateManager->performUpdate();
            break;
        case 'rollback':
            $backupIndex = intval($_POST['backup_index'] ?? 0);
            $restoreData = isset($_POST['restore_data']) && $_POST['restore_data'] === '1';
            $result = $updateManager->rollback($backupIndex, $restoreData);
            break;
        case 'check':
            $result = $updateManager->checkForUpdates();
            break;
    }
}

// Get system information
$systemInfo = $updateManager->getSystemInfo();
$backups = $updateManager->getBackupList();

require_once '../includes/header.php';
?>

<div class="space-y-6">
    <!-- System Information -->
    <div class="bg-white dark:bg-gray-800 p-6 rounded-xl shadow border border-gray-100 dark:border-gray-700 transition-colors">
        <h3 class="font-bold text-lg mb-4 text-gray-900 dark:text-white flex items-center">
            <span class="mr-2">ℹ️</span> System Information
        </h3>
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
            <div>
                <div class="text-[10px] font-bold text-gray-400 uppercase mb-1">Application</div>
                <div class="text-sm font-bold text-gray-900 dark:text-white"><?php echo APP_NAME; ?></div>
            </div>
            <div>
                <div class="text-[10px] font-bold text-gray-400 uppercase mb-1">Version</div>
                <div class="text-sm font-bold text-gray-900 dark:text-white"><?php echo APP_VERSION; ?></div>
            </div>
            <div>
                <div class="text-[10px] font-bold text-gray-400 uppercase mb-1">Current Commit</div>
                <div class="text-sm font-mono font-bold text-brand-600 dark:text-brand-400"><?php echo htmlspecialchars($systemInfo['current_commit']); ?></div>
            </div>
            <div>
                <div class="text-[10px] font-bold text-gray-400 uppercase mb-1">Branch</div>
                <div class="text-sm font-bold text-gray-900 dark:text-white"><?php echo htmlspecialchars($systemInfo['current_branch']); ?></div>
            </div>
        </div>
        
        <div class="mt-4 pt-4 border-t border-gray-100 dark:border-gray-700">
            <div class="grid grid-cols-2 md:grid-cols-4 gap-4 text-xs">
                <div>
                    <span class="text-gray-400">OS:</span>
                    <span class="ml-2 font-bold text-gray-900 dark:text-white"><?php echo ucfirst($systemInfo['os']); ?></span>
                </div>
                <div>
                    <span class="text-gray-400">PHP:</span>
                    <span class="ml-2 font-bold text-gray-900 dark:text-white"><?php echo $systemInfo['php_version']; ?></span>
                </div>
                <div>
                    <span class="text-gray-400">Docker:</span>
                    <span class="ml-2 font-bold <?php echo $systemInfo['is_docker'] ? 'text-green-600' : 'text-gray-500'; ?>">
                        <?php echo $systemInfo['is_docker'] ? 'Yes' : 'No'; ?>
                    </span>
                </div>
                <div>
                    <span class="text-gray-400">Git:</span>
                    <span class="ml-2 font-bold <?php echo $systemInfo['git_available'] ? 'text-green-600' : 'text-red-600'; ?>">
                        <?php echo $systemInfo['git_available'] ? 'Available' : 'Not Found'; ?>
                    </span>
                </div>
            </div>
        </div>
        
        <?php if (!$systemInfo['git_available']): ?>
        <div class="mt-4 p-4 bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 rounded-lg">
            <p class="text-sm text-red-800 dark:text-red-200">
                <strong>⚠️ Git Not Available:</strong> Git is required for OTA updates. Please install Git on your system.
            </p>
        </div>
        <?php elseif (!$systemInfo['is_git_repo']): ?>
        <div class="mt-4 p-4 bg-yellow-50 dark:bg-yellow-900/20 border border-yellow-200 dark:border-yellow-800 rounded-lg">
            <p class="text-sm text-yellow-800 dark:text-yellow-200">
                <strong>⚠️ Not a Git Repository:</strong> This directory is not initialized as a Git repository. OTA updates require Git.
            </p>
        </div>
        <?php endif; ?>
    </div>

    <!-- Update Actions -->
    <div class="bg-white dark:bg-gray-800 p-6 rounded-xl shadow border border-gray-100 dark:border-gray-700 transition-colors">
        <h3 class="font-bold text-lg mb-4 text-gray-900 dark:text-white flex items-center">
            <span class="mr-2">🔄</span> Update Actions
        </h3>
        
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
            <button onclick="checkForUpdates()" id="checkBtn" class="w-full bg-blue-600 text-white py-3 px-4 rounded-lg font-bold hover:bg-blue-700 transition shadow-md flex items-center justify-center space-x-2">
                <span>🔍</span>
                <span>Check for Updates</span>
            </button>
            
            <button onclick="performUpdate()" id="updateBtn" class="w-full bg-green-600 text-white py-3 px-4 rounded-lg font-bold hover:bg-green-700 transition shadow-md flex items-center justify-center space-x-2">
                <span>⬇️</span>
                <span>Update Now</span>
            </button>
            
            <button onclick="showRollbackModal()" class="w-full bg-amber-600 text-white py-3 px-4 rounded-lg font-bold hover:bg-amber-700 transition shadow-md flex items-center justify-center space-x-2">
                <span>↩️</span>
                <span>Rollback</span>
            </button>
        </div>
        
        <!-- Update Status -->
        <div id="updateStatus" class="mt-4 hidden">
            <div class="flex items-center space-x-2 text-sm text-gray-600 dark:text-gray-400">
                <div class="animate-spin rounded-full h-4 w-4 border-b-2 border-brand-600"></div>
                <span id="statusText">Processing...</span>
            </div>
        </div>
    </div>

    <!-- Output Display -->
    <?php if ($result !== null): ?>
    <div class="bg-white dark:bg-gray-800 p-6 rounded-xl shadow border border-gray-100 dark:border-gray-700 transition-colors">
        <h3 class="font-bold text-lg mb-4 text-gray-900 dark:text-white">
            <?php if ($result['success']): ?>
                ✅ Operation Successful
            <?php else: ?>
                ❌ Operation Failed
            <?php endif; ?>
        </h3>
        
        <?php if (isset($result['log'])): ?>
        <pre class="bg-gray-900 text-green-400 p-4 rounded-lg overflow-x-auto text-xs font-mono max-h-96"><?php echo htmlspecialchars($result['log']); ?></pre>
        <?php elseif (isset($result['error'])): ?>
        <div class="bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 rounded-lg p-4">
            <p class="text-sm text-red-800 dark:text-red-200"><?php echo htmlspecialchars($result['error']); ?></p>
            <?php if (isset($result['output'])): ?>
            <pre class="mt-2 text-xs text-red-700 dark:text-red-300"><?php echo htmlspecialchars($result['output']); ?></pre>
            <?php endif; ?>
        </div>
        <?php else: ?>
        <div class="bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-800 rounded-lg p-4">
            <p class="text-sm text-blue-800 dark:text-blue-200">
                <?php if (isset($result['available'])): ?>
                    <?php if ($result['available']): ?>
                        <strong>Updates Available:</strong> <?php echo $result['count']; ?> commit(s) behind remote
                    <?php else: ?>
                        <strong>System is up to date!</strong>
                    <?php endif; ?>
                <?php endif; ?>
            </p>
        </div>
        <?php endif; ?>
        
        <?php if ($result['success']): ?>
        <div class="mt-4">
            <a href="../index.php" class="inline-block bg-brand-600 text-white py-2 px-6 rounded-lg font-bold hover:bg-brand-700 transition">
                Return to Dashboard
            </a>
        </div>
        <?php endif; ?>
    </div>
    <?php endif; ?>

    <!-- Update History -->
    <?php if (!empty($backups)): ?>
    <div class="bg-white dark:bg-gray-800 p-6 rounded-xl shadow border border-gray-100 dark:border-gray-700 transition-colors">
        <h3 class="font-bold text-lg mb-4 text-gray-900 dark:text-white flex items-center">
            <span class="mr-2">📜</span> Update History
        </h3>
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                <thead class="bg-gray-50 dark:bg-gray-900/50">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Timestamp</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Commit</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Backup Size</th>
                    </tr>
                </thead>
                <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                    <?php foreach ($backups as $backup): ?>
                    <tr class="hover:bg-gray-50 dark:hover:bg-gray-900/30 transition-colors">
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 dark:text-white"><?php echo htmlspecialchars($backup['timestamp']); ?></td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm font-mono text-brand-600 dark:text-brand-400"><?php echo htmlspecialchars($backup['commit']); ?></td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400"><?php echo number_format($backup['size'] / 1024, 2); ?> KB</td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php endif; ?>
</div>

<!-- Rollback Modal -->
<div id="rollbackModal" class="fixed inset-0 bg-black/50 backdrop-blur-sm z-50 hidden flex items-center justify-center p-4">
    <div class="bg-white dark:bg-gray-800 rounded-2xl w-full max-w-md overflow-hidden shadow-2xl">
        <div class="p-6 border-b border-gray-100 dark:border-gray-700 flex justify-between items-center">
            <h3 class="font-black text-gray-900 dark:text-white">Select Backup to Restore</h3>
            <button onclick="hideRollbackModal()" class="text-gray-400 hover:text-gray-600">✕</button>
        </div>
        <div class="p-6">
            <?php if (!empty($backups)): ?>
            <form method="POST" onsubmit="return confirmRollback()">
                <input type="hidden" name="action" value="rollback">
                <div class="space-y-2 max-h-64 overflow-y-auto">
                    <?php foreach ($backups as $index => $backup): ?>
                    <label class="flex items-center p-3 bg-gray-50 dark:bg-gray-900 rounded-lg hover:bg-gray-100 dark:hover:bg-gray-800 cursor-pointer transition">
                        <input type="radio" name="backup_index" value="<?php echo $index; ?>" <?php echo $index === 0 ? 'checked' : ''; ?> class="mr-3">
                        <div class="flex-grow">
                            <div class="text-sm font-bold text-gray-900 dark:text-white"><?php echo htmlspecialchars($backup['timestamp']); ?></div>
                            <div class="text-xs text-gray-500 dark:text-gray-400 font-mono"><?php echo htmlspecialchars($backup['commit']); ?></div>
                        </div>
                    </label>
                    <?php endforeach; ?>
                </div>
                <div class="mt-4 p-3 bg-blue-50 dark:bg-blue-900/20 rounded-lg border border-blue-100 dark:border-blue-800">
                    <label class="flex items-start gap-2 cursor-pointer">
                        <input type="checkbox" name="restore_data" value="1" id="restoreDataCheck" class="mt-1">
                        <div>
                            <span class="block text-sm font-bold text-gray-900 dark:text-gray-100">Restore Data (Dangerous)</span>
                            <span class="block text-xs text-gray-500 dark:text-gray-400">If checked, your <strong>Database & Uploads</strong> will be reverted to the state of this backup. Uncheck to only downgrade the application code.</span>
                        </div>
                    </label>
                </div>

                <button type="submit" class="w-full mt-4 bg-amber-600 text-white py-3 rounded-lg font-bold hover:bg-amber-700 transition">
                    Perform Rollback
                </button>
            </form>
            <?php else: ?>
            <p class="text-gray-500 dark:text-gray-400 text-center py-8">No backups available</p>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
function showRollbackModal() {
    document.getElementById('rollbackModal').classList.remove('hidden');
}

function hideRollbackModal() {
    document.getElementById('rollbackModal').classList.add('hidden');
}

function confirmRollback() {
    const isDataRestore = document.getElementById('restoreDataCheck').checked;
    if (isDataRestore) {
        return confirm('WARNING: You have chosen to RESTORE DATA.\n\nThis will OVERWRITE your current database and files with old data.\n\nAre you sure you want to proceed?');
    } else {
        return confirm('This will downgrade the application code to the selected version.\n\nYour current Data (Database & Uploads) will remain UNTOUCHED.\n\nProceed?');
    }
}

function showStatus(text) {
    document.getElementById('updateStatus').classList.remove('hidden');
    document.getElementById('statusText').textContent = text;
}

function hideStatus() {
    document.getElementById('updateStatus').classList.add('hidden');
}

function disableButtons() {
    document.getElementById('checkBtn').disabled = true;
    document.getElementById('updateBtn').disabled = true;
}

function enableButtons() {
    document.getElementById('checkBtn').disabled = false;
    document.getElementById('updateBtn').disabled = false;
}

async function checkForUpdates() {
    showStatus('Checking for updates...');
    disableButtons();
    
    try {
        const response = await fetch('?ajax=1&action=check');
        const result = await response.json();
        
        hideStatus();
        enableButtons();
        
        if (result.error) {
            alert('Error: ' + result.error);
        } else if (result.available) {
            alert(`Updates available!\n\n${result.count} commit(s) behind remote.\n\nClick "Update Now" to install updates.`);
        } else {
            alert('System is up to date!');
        }
    } catch (error) {
        hideStatus();
        enableButtons();
        alert('Failed to check for updates: ' + error.message);
    }
}

async function performUpdate() {
    if (!confirm('This will update the application from the remote repository. A database backup will be created automatically.\n\nContinue?')) {
        return;
    }
    
    showStatus('Performing update... This may take a minute.');
    disableButtons();
    
    // Use form submission for update to show full output
    const form = document.createElement('form');
    form.method = 'POST';
    form.innerHTML = '<input type="hidden" name="action" value="update">';
    document.body.appendChild(form);
    form.submit();
}
</script>

<?php require_once '../includes/footer.php'; ?>
