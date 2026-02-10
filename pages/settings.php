<?php
require_once '../config/database.php';
require_once '../includes/auth.php';
requireLogin();

$userId = getCurrentUserId();
$message = '';
$error = '';

// Paths
$nodesFile = '../config/sync_nodes.json';
$secretFile = '../config/sync_secret.txt';

// Helper: Get Nodes
function getNodes() {
    global $nodesFile;
    if (!file_exists($nodesFile)) return [];
    return json_decode(file_get_contents($nodesFile), true) ?? [];
}

// Helper: Save Nodes
function saveNodes($nodes) {
    global $nodesFile;
    file_put_contents($nodesFile, json_encode($nodes, JSON_PRETTY_PRINT));
}

// Handle Actions (PRG Pattern)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        $redirectUrl = 'settings.php'; // Default redirect
        
        // --- EXISTING BACKUP/RESTORE ---
        if ($_POST['action'] === 'backup') {
            $zipName = 'finance_backup_' . date('Y-m-d_H-i-s') . '.zip';
            $zipPath = sys_get_temp_dir() . '/' . $zipName;
            
            $zip = new ZipArchive();
            if ($zip->open($zipPath, ZipArchive::CREATE) !== TRUE) {
                $error = "Could not create ZIP file.";
            } else {
                // Add Database
                $dbFile = '../db/finance.db';
                if (file_exists($dbFile)) {
                    $zip->addFile($dbFile, 'finance.db');
                }

                // Add Uploads
                $uploadDir = '../uploads/';
                if (is_dir($uploadDir)) {
                    $files = new RecursiveIteratorIterator(
                        new RecursiveDirectoryIterator($uploadDir),
                        RecursiveIteratorIterator::LEAVES_ONLY
                    );

                    foreach ($files as $name => $file) {
                        if (!$file->isDir()) {
                            $filePath = $file->getRealPath();
                            $relativePath = 'uploads/' . substr($filePath, strlen(realpath($uploadDir)) + 1);
                            $zip->addFile($filePath, $relativePath);
                        }
                    }
                }

                $zip->close();

                // Download
                header('Content-Type: application/zip');
                header('Content-disposition: attachment; filename=' . $zipName);
                header('Content-Length: ' . filesize($zipPath));
                readfile($zipPath);
                unlink($zipPath);
                exit; // Stop execution after file download
            }
        } elseif ($_POST['action'] === 'restore') {
            if (isset($_FILES['backup_zip'])) {
                $fileError = $_FILES['backup_zip']['error'];
                if ($fileError === UPLOAD_ERR_OK) {
                    $zip = new ZipArchive();
                    $res = $zip->open($_FILES['backup_zip']['tmp_name']);
                    if ($res === TRUE) {
                        $extractPath = sys_get_temp_dir() . '/finance_restore_' . uniqid();
                        mkdir($extractPath);
                        $zip->extractTo($extractPath);
                        $zip->close();

                        // 1. Restore Database
                        if (file_exists($extractPath . '/finance.db')) {
                            $pdo = null; 
                            copy($extractPath . '/finance.db', '../db/finance.db');
                            $message = "Database restored successfully. ";
                        }

                        // 2. Restore Uploads
                        if (is_dir($extractPath . '/uploads/')) {
                            $files = new RecursiveIteratorIterator(
                                new RecursiveDirectoryIterator($extractPath . '/uploads/'),
                                RecursiveIteratorIterator::LEAVES_ONLY
                            );

                            foreach ($files as $name => $file) {
                                if (!$file->isDir()) {
                                    $filePath = $file->getRealPath();
                                    $relativePath = substr($filePath, strlen(realpath($extractPath . '/uploads/')) + 1);
                                    $destPath = '../uploads/' . $relativePath;
                                    
                                    $destDir = dirname($destPath);
                                    if (!is_dir($destDir)) mkdir($destDir, 0777, true);
                                    
                                    copy($filePath, $destPath);
                                }
                            }
                            $message .= "Uploads synced.";
                        }
                        
                        $redirectUrl .= "?message=" . urlencode($message);
                    } else {
                        $redirectUrl .= "?error=" . urlencode("Invalid ZIP file (Error Code: $res).");
                    }
                } else {
                    $redirectUrl .= "?error=" . urlencode("Upload error (Code: $fileError).");
                }
            } else {
                $redirectUrl .= "?error=" . urlencode("Please select a backup file.");
            }
        
        // --- NODE ACTIONS ---
        } elseif ($_POST['action'] === 'add_node') {
            $nodes = getNodes();
            $nodes[] = [
                'name' => $_POST['name'],
                'url' => rtrim($_POST['url'], '/'),
                'key' => $_POST['key']
            ];
            saveNodes($nodes);
            $redirectUrl .= "?message=" . urlencode("Node added successfully.");

        } elseif ($_POST['action'] === 'delete_node') {
            $index = (int)$_POST['index'];
            $nodes = getNodes();
            if (isset($nodes[$index])) {
                array_splice($nodes, $index, 1);
                saveNodes($nodes);
                $redirectUrl .= "?message=" . urlencode("Node deleted successfully.");
            }

        } elseif ($_POST['action'] === 'update_secret') {
            file_put_contents($secretFile, $_POST['secret_key']);
            $redirectUrl .= "?message=" . urlencode("Secret key updated.");
        }
        
        // Redirect to clear POST data
        header("Location: " . $redirectUrl);
        exit;
    }
}

// Get Messages from URL
if (isset($_GET['message'])) $message = $_GET['message'];
if (isset($_GET['error'])) $error = $_GET['error'];

$currentNodes = getNodes();
$currentSecret = file_exists($secretFile) ? file_get_contents($secretFile) : '';

$pageTitle = 'Settings & Maintenance';
require_once '../includes/header.php';

// Determine Server Role
$isPrimary = count($currentNodes) > 0;
$isBackup = !empty($currentSecret);
$serverRole = 'Standalone';
$roleBadgeClass = 'bg-gray-100 text-gray-600 dark:bg-gray-800 dark:text-gray-400';

if ($isPrimary && $isBackup) {
    $serverRole = 'Hybrid (Primary + Backup)';
    $roleBadgeClass = 'bg-purple-100 text-purple-700 dark:bg-purple-900/40 dark:text-purple-300';
} elseif ($isPrimary) {
    $serverRole = 'Primary Server';
    $roleBadgeClass = 'bg-blue-100 text-blue-700 dark:bg-blue-900/40 dark:text-blue-300';
} elseif ($isBackup) {
    $serverRole = 'Backup Node';
    $roleBadgeClass = 'bg-amber-100 text-amber-700 dark:bg-amber-900/40 dark:text-amber-300';
}
?>

<div class="max-w-6xl mx-auto space-y-8 pb-12">
    
    <!-- Title Section -->
    <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
        <div>
            <div class="flex items-center gap-3">
                <h1 class="text-3xl font-bold text-gray-900 dark:text-white tracking-tight">System Maintenance</h1>
                <span class="px-3 py-1 rounded-full text-xs font-bold uppercase tracking-wider <?php echo $roleBadgeClass; ?>">
                    <?php echo $serverRole; ?>
                </span>
            </div>
            <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">Manage backups, restore data, and configure sync nodes.</p>
        </div>
        <div class="text-xs font-mono bg-gray-100 dark:bg-gray-800 px-3 py-1.5 rounded-lg text-gray-500">
            v2.4.1 • SQLite
        </div>
    </div>

    <!-- Alert Messages -->
    <?php if ($message): ?>
        <div class="bg-green-50 border border-green-200 text-green-800 px-4 py-3 rounded-xl flex items-center shadow-sm" role="alert">
            <svg class="w-5 h-5 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
            <span class="text-sm font-medium"><?php echo htmlspecialchars($message); ?></span>
        </div>
    <?php endif; ?>

    <?php if ($error): ?>
        <div class="bg-red-50 border border-red-200 text-red-800 px-4 py-3 rounded-xl flex items-center shadow-sm" role="alert">
            <svg class="w-5 h-5 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
            <span class="text-sm font-medium"><?php echo htmlspecialchars($error); ?></span>
        </div>
    <?php endif; ?>

    <!-- Main Grid -->
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
        
        <!-- Left Column: Local Data Operations -->
        <div class="space-y-6">
            <h2 class="text-lg font-bold text-gray-900 dark:text-white flex items-center gap-2">
                <span class="w-8 h-8 rounded-lg bg-indigo-100 dark:bg-indigo-900/50 text-indigo-600 dark:text-indigo-400 flex items-center justify-center text-sm">💾</span>
                Local Data
            </h2>

            <!-- Backup Card -->
            <div class="bg-white dark:bg-gray-800 rounded-2xl p-6 shadow-sm border border-gray-100 dark:border-gray-700 hover:shadow-md transition-shadow">
                <h3 class="font-bold text-gray-900 dark:text-white mb-2">Download Backup</h3>
                <p class="text-xs text-gray-500 dark:text-gray-400 mb-6 leading-relaxed">
                    Create a full ZIP archive containing your database and all uploaded files (receipts, payslips).
                </p>
                <form method="POST">
                    <input type="hidden" name="action" value="backup">
                    <button type="submit" class="w-full flex items-center justify-center gap-2 bg-indigo-50 hover:bg-indigo-100 dark:bg-indigo-900/30 dark:hover:bg-indigo-900/50 text-indigo-700 dark:text-indigo-300 py-2.5 rounded-xl text-sm font-bold transition">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>
                        Download Archive
                    </button>
                </form>
            </div>

            <!-- Restore Card -->
            <div class="bg-white dark:bg-gray-800 rounded-2xl p-6 shadow-sm border border-gray-100 dark:border-gray-700 hover:shadow-md transition-shadow">
                <h3 class="font-bold text-gray-900 dark:text-white mb-2">Restore Backup</h3>
                <p class="text-xs text-gray-500 dark:text-gray-400 mb-4 leading-relaxed">
                    Restore data from a ZIP file. <strong class="text-red-500">Warning: Overwrites existing data.</strong>
                </p>
                <form method="POST" enctype="multipart/form-data" onsubmit="return confirm('⚠️ CRITICAL WARNING ⚠️\n\nThis will permanently DELETE your current data and replace it with the backup.\n\nAre you sure you want to proceed?');">
                    <input type="hidden" name="action" value="restore">
                    <div class="relative mb-4">
                        <input type="file" name="backup_zip" accept=".zip" required class="block w-full text-xs text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-full file:border-0 file:text-xs file:font-semibold file:bg-indigo-50 file:text-indigo-700 hover:file:bg-indigo-100 cursor-pointer">
                    </div>
                    <button type="submit" class="w-full flex items-center justify-center gap-2 bg-white border-2 border-amber-500 text-amber-600 hover:bg-amber-50 py-2.5 rounded-xl text-sm font-bold transition">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
                        Restore Data
                    </button>
                </form>
            </div>

            <!-- Import Card -->
            <div class="bg-gradient-to-br from-indigo-600 to-blue-600 rounded-2xl p-6 shadow-lg text-white">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="font-bold">Bulk Import</h3>
                    <span class="p-1 bg-white/20 rounded text-xs opacity-80">CSV</span>
                </div>
                <p class="text-xs text-indigo-100 mb-6 leading-relaxed opacity-90">
                    Migrating from another tool? Import expenses in bulk using CSV files.
                </p>
                <a href="import.php" class="block w-full text-center bg-white/20 hover:bg-white/30 backdrop-blur-sm py-2.5 rounded-xl text-sm font-bold transition border border-white/20">
                    Import Wizard
                </a>
            </div>
        </div>

        <!-- Right Column: Sync Cluster -->
        <div class="lg:col-span-2 space-y-6">
            <div class="flex items-center justify-between">
                <h2 class="text-lg font-bold text-gray-900 dark:text-white flex items-center gap-2">
                    <span class="w-8 h-8 rounded-lg bg-emerald-100 dark:bg-emerald-900/50 text-emerald-600 dark:text-emerald-400 flex items-center justify-center text-sm">🔄</span>
                    Sync Cluster
                </h2>
                <button onclick="triggerSync()" id="syncBtn" class="flex items-center gap-2 bg-emerald-600 hover:bg-emerald-700 text-white px-4 py-2 rounded-xl text-sm font-bold shadow-lg shadow-emerald-200 dark:shadow-none transition-all hover:scale-105 active:scale-95 disabled:opacity-50 disabled:scale-100">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/></svg>
                    <span>Trigger Sync Now</span>
                </button>
            </div>
            
            <!-- Live Status Container (Hidden initially) -->
            <div id="syncStatusContainer" class="hidden bg-gray-900 text-gray-300 rounded-2xl p-6 font-mono text-xs border border-gray-700 shadow-inner">
                <div class="flex items-center gap-3 mb-4 border-b border-gray-700 pb-3">
                    <div class="relative w-3 h-3">
                        <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-emerald-400 opacity-75"></span>
                        <span class="relative inline-flex rounded-full h-3 w-3 bg-emerald-500"></span>
                    </div>
                    <span class="font-bold text-white uppercase tracking-wider">Live Sync Status</span>
                </div>
                <div id="syncLog" class="space-y-2 max-h-48 overflow-y-auto pr-2">
                    <!-- Dynamic logs will appear here -->
                </div>
            </div>

            <!-- Nodes Config -->
            <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-sm border border-gray-100 dark:border-gray-700 overflow-hidden">
                <div class="p-6 border-b border-gray-100 dark:border-gray-700 flex justify-between items-center">
                    <div>
                        <h3 class="font-bold text-gray-900 dark:text-white">Active Nodes</h3>
                        <p class="text-xs text-gray-500 mt-1">Servers configured to receive data.</p>
                    </div>
                    <button onclick="checkAllNodes()" class="text-xs font-bold text-indigo-600 hover:text-indigo-800 dark:text-indigo-400 flex items-center gap-1">
                        <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
                        Refresh Status
                    </button>
                </div>

                <!-- Existing Nodes List -->
                <div class="divide-y divide-gray-100 dark:divide-gray-700">
                    <?php if (count($currentNodes) === 0): ?>
                        <div class="p-8 text-center text-gray-400 text-sm italic">
                            No backup nodes configured. Add one below.
                        </div>
                    <?php else: ?>
                        <?php foreach ($currentNodes as $index => $node): ?>
                        <div class="p-4 flex items-center justify-between hover:bg-gray-50 dark:hover:bg-gray-700/50 transition">
                            <div class="flex items-center gap-4">
                                <div class="w-10 h-10 rounded-full bg-gray-100 dark:bg-gray-700 flex items-center justify-center text-lg">
                                    🖥️
                                </div>
                                <div>
                                    <div class="flex items-center gap-2">
                                        <h4 class="font-bold text-sm text-gray-900 dark:text-white"><?php echo htmlspecialchars($node['name']); ?></h4>
                                        <span id="status-<?php echo $index; ?>" class="text-[10px] font-bold uppercase tracking-wide text-gray-400 bg-gray-100 dark:bg-gray-900 px-1.5 py-0.5 rounded">Unknown</span>
                                    </div>
                                    <div class="text-[10px] text-gray-500 font-mono mt-0.5 max-w-[200px] truncate" title="<?php echo htmlspecialchars($node['url']); ?>">
                                        <?php echo htmlspecialchars($node['url']); ?>
                                    </div>
                                </div>
                            </div>
                            <div class="flex items-center gap-3">
                                <!-- Ping Button -->
                                <button onclick="checkNode(<?php echo $index; ?>, '<?php echo htmlspecialchars($node['url']); ?>')" class="text-gray-400 hover:text-indigo-600 transition" title="Test Connection">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/></svg>
                                </button>
                                <!-- Delete Button -->
                                <form method="POST" onsubmit="return confirm('Remove this node?');" class="m-0">
                                    <input type="hidden" name="action" value="delete_node">
                                    <input type="hidden" name="index" value="<?php echo $index; ?>">
                                    <button type="submit" class="text-gray-400 hover:text-red-500 transition" title="Remove Node">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                                    </button>
                                </form>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>

                <!-- Add Node Form -->
                <div class="bg-gray-50 dark:bg-gray-900/30 p-6 border-t border-gray-100 dark:border-gray-700">
                    <h4 class="text-xs font-bold uppercase tracking-widest text-gray-500 mb-4">Add New Node</h4>
                    <form method="POST" class="grid grid-cols-1 md:grid-cols-12 gap-3">
                        <input type="hidden" name="action" value="add_node">
                        
                        <div class="md:col-span-3">
                            <input type="text" name="name" placeholder="Name (e.g. Pi)" required class="w-full bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-500 outline-none">
                        </div>
                        <div class="md:col-span-4">
                            <input type="url" name="url" placeholder="http://192.168.1.5:8000" required class="w-full bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-500 outline-none">
                        </div>
                        <div class="md:col-span-3 relative">
                            <input type="password" name="key" id="newNodeKey" placeholder="Secret Key" required class="w-full bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-500 outline-none pr-8">
                            <button type="button" onclick="toggleVisibility('newNodeKey')" class="absolute right-2 top-2.5 text-gray-400 hover:text-gray-600">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" /><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" /></svg>
                            </button>
                        </div>
                        <div class="md:col-span-2">
                            <button type="submit" class="w-full bg-indigo-600 hover:bg-indigo-700 text-white py-2 rounded-lg text-sm font-bold shadow-sm transition">
                                Add
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Receiver Node Config -->
             <div class="bg-gray-50 dark:bg-gray-900/20 border border-dashed border-gray-300 dark:border-gray-700 rounded-2xl p-6 relative">
                 <div class="absolute top-0 right-0 p-3">
                     <span class="text-[10px] font-bold uppercase tracking-widest text-gray-400 bg-white dark:bg-gray-800 px-2 py-1 rounded shadow-sm border border-gray-100 dark:border-gray-700">This Device</span>
                 </div>
                 <div class="flex items-center gap-3 mb-4">
                     <div class="p-2 bg-indigo-100 dark:bg-indigo-900 text-indigo-600 dark:text-indigo-400 rounded-lg">🛡️</div>
                     <div>
                         <h3 class="font-bold text-gray-900 dark:text-white">Receiver Configuration</h3>
                         <p class="text-xs text-gray-500">If this is a Backup Node, set the secret key here.</p>
                     </div>
                 </div>
                 
                 <form method="POST" class="flex gap-3 items-center">
                    <input type="hidden" name="action" value="update_secret">
                    <div class="relative flex-grow">
                        <input type="password" name="secret_key" id="receiverKey" value="<?php echo htmlspecialchars($currentSecret); ?>" class="w-full bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-lg px-4 py-2 text-sm font-mono focus:ring-2 focus:ring-indigo-500 outline-none pr-10" placeholder="Set a secure key...">
                        <button type="button" onclick="toggleVisibility('receiverKey')" class="absolute right-3 top-2.5 text-gray-400 hover:text-gray-600">
                             <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" /><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" /></svg>
                        </button>
                    </div>
                    <button type="submit" class="bg-gray-900 dark:bg-gray-700 text-white px-4 py-2 rounded-lg text-sm font-bold shadow-sm hover:bg-gray-800 transition">
                        Update
                    </button>
                 </form>
             </div>

        </div>
    </div>
</div>

<script>
// --- Toggle Password Visibility ---
function toggleVisibility(id) {
    const input = document.getElementById(id);
    if (input.type === 'password') {
        input.type = 'text';
    } else {
        input.type = 'password';
    }
}

// --- Live Node Status Check ---
const nodes = <?php echo json_encode($currentNodes); ?>;

function checkNode(index, url) {
    const badge = document.getElementById(`status-${index}`);
    badge.className = "text-[10px] font-bold uppercase tracking-wide text-amber-600 bg-amber-50 dark:bg-amber-900/30 px-1.5 py-0.5 rounded animate-pulse";
    badge.innerText = "Checking...";

    fetch(`${url}/api/sync_receive.php?ping=1`, { method: 'GET', cache: 'no-store' })
        .then(response => {
            if (response.ok) return response.text();
            throw new Error('Server Error ' + response.status);
        })
        .then(data => {
            if (data.trim() === 'PONG') {
                badge.className = "text-[10px] font-bold uppercase tracking-wide text-emerald-600 bg-emerald-50 dark:bg-emerald-900/30 px-1.5 py-0.5 rounded";
                badge.innerText = "Online";
            } else {
                throw new Error('Invalid Response');
            }
        })
        .catch(err => {
            badge.className = "text-[10px] font-bold uppercase tracking-wide text-red-600 bg-red-50 dark:bg-red-900/30 px-1.5 py-0.5 rounded cursor-help";
            badge.innerText = "Error";
            badge.title = err.message;
            console.error("Node Check Error:", err);
        });
}

function checkAllNodes() {
    nodes.forEach((node, index) => {
        checkNode(index, node.url);
    });
}

// Auto-check on load
document.addEventListener('DOMContentLoaded', checkAllNodes);


// --- Sync Orchestrator ---
async function triggerSync() {
    if(!confirm("Start synchronization to all online nodes?")) return;

    const btn = document.getElementById('syncBtn');
    const container = document.getElementById('syncStatusContainer');
    const log = document.getElementById('syncLog');

    btn.disabled = true;
    container.classList.remove('hidden');
    log.innerHTML = ''; // Clear previous logs

    const appendLog = (msg, type = 'info') => {
        const div = document.createElement('div');
        div.className = "flex justify-between items-center py-1";
        
        let colorClass = "text-gray-300";
        let icon = "🔹";
        
        if (type === 'success') { colorClass = "text-emerald-400"; icon = "✅"; }
        if (type === 'error') { colorClass = "text-red-400"; icon = "❌"; }
        if (type === 'pending') { colorClass = "text-amber-400"; icon = "⏳"; }

        div.innerHTML = `<span class="${colorClass}">${icon} ${msg}</span><span class="text-gray-600 text-[10px]">${new Date().toLocaleTimeString()}</span>`;
        log.appendChild(div);
        log.scrollTop = log.scrollHeight;
    };

    try {
        // Step 1: Create Backup
        appendLog("Creating local backup package...", "pending");
        
        // Fix: Use current folder path for API
        const backupRes = await fetch('../api/trigger_sync.php?step=create_backup');
        const backupData = await backupRes.json();
        console.log(backupData);

        if (backupData.status !== 'success') throw new Error(backupData.message || "Backup failed");
        
        const zipPath = backupData.zip_path;
        appendLog("Backup package created successfully.", "success");

        // Step 2: Push to each node
        for (let i = 0; i < nodes.length; i++) {
            const node = nodes[i];
            appendLog(`Syncing to ${node.name}...`, "pending");
            
            try {
                const formData = new FormData();
                formData.append('zip_path', zipPath);
                formData.append('node_index', i);

                const pushRes = await fetch('../api/trigger_sync.php?step=push_node', {
                    method: 'POST',
                    body: formData
                });
                const pushData = await pushRes.json();

                if (pushData.status === 'success') {
                    appendLog(`Synced to ${node.name} successfully.`, "success");
                } else {
                    appendLog(`Failed to sync ${node.name}: ${pushData.message}`, "error");
                }
            } catch (nodeErr) {
                appendLog(`Connection error with ${node.name}`, "error");
            }
        }

        // Step 3: Cleanup
        appendLog("Cleaning up temporary files...", "info");
        const cleanupData = new FormData();
        cleanupData.append('zip_path', zipPath);
        await fetch('../api/trigger_sync.php?step=cleanup', { method: 'POST', body: cleanupData });
        
        appendLog("Sync sequence completed.", "success");

    } catch (e) {
        appendLog(`Critical Error: ${e.message}`, "error");
    } finally {
        btn.disabled = false;
    }
}
</script>

<?php require_once '../includes/footer.php'; ?>
