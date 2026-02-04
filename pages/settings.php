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

// Handle Actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
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
                exit;
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
                            // Close PDO to allow file replace
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
                        
                        header("Location: settings.php?message=" . urlencode($message));
                        exit;
                    } else {
                        $error = "Invalid ZIP file (Error Code: $res). The file might be corrupted or incomplete.";
                    }
                } else {
                    switch ($fileError) {
                        case UPLOAD_ERR_INI_SIZE:
                            $max = ini_get('upload_max_filesize');
                            $error = "File is too large. Current PHP limit is $max. Please increase 'upload_max_filesize' and 'post_max_size' in your php.ini.";
                            break;
                        case UPLOAD_ERR_PARTIAL:
                            $error = "The file was only partially uploaded. Please try again.";
                            break;
                        case UPLOAD_ERR_NO_FILE:
                            $error = "No file was selected.";
                            break;
                        default:
                            $error = "Upload error (Code: $fileError). Check your server logs.";
                    }
                }
            } else {
                $error = "Please select a backup file.";
            }
        // --- NEW SYNC ACTIONS ---
        } elseif ($_POST['action'] === 'add_node') {
            $nodes = getNodes();
            $nodes[] = [
                'name' => $_POST['name'],
                'url' => rtrim($_POST['url'], '/'),
                'key' => $_POST['key']
            ];
            saveNodes($nodes);
            $message = "Node added successfully.";
        } elseif ($_POST['action'] === 'delete_node') {
            $index = (int)$_POST['index'];
            $nodes = getNodes();
            if (isset($nodes[$index])) {
                array_splice($nodes, $index, 1);
                saveNodes($nodes);
                $message = "Node deleted successfully.";
            }
        } elseif ($_POST['action'] === 'update_secret') {
            file_put_contents($secretFile, $_POST['secret_key']);
            $message = "Secret key updated.";
        }
    }
}

if (isset($_GET['message'])) $message = $_GET['message'];

$currentNodes = getNodes();
$currentSecret = file_exists($secretFile) ? file_get_contents($secretFile) : '';

$pageTitle = 'Settings & Maintenance';
require_once '../includes/header.php';
?>

<div class="max-w-2xl mx-auto space-y-6">
    <div class="bg-white dark:bg-gray-800 p-8 rounded-xl shadow-sm border border-gray-100 dark:border-gray-700 transition-colors">
        <h1 class="text-2xl font-bold text-gray-900 dark:text-white mb-2">Systems & Maintenance</h1>
        <p class="text-sm text-gray-500 dark:text-gray-400 mb-8">Manage your data portability and backups.</p>

        <?php if ($message): ?>
            <div class="bg-green-50 border border-green-100 text-green-700 p-4 rounded-lg mb-6 text-sm flex items-center">
                <span class="mr-2">✅</span> <?php echo $message; ?>
            </div>
        <?php endif; ?>

        <?php if ($error): ?>
            <div class="bg-red-50 border border-red-100 text-red-700 p-4 rounded-lg mb-6 text-sm flex items-center">
                <span class="mr-2">⚠️</span> <?php echo $error; ?>
            </div>
        <?php endif; ?>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-8 items-stretch mb-8">
            <!-- Backup -->
            <div class="bg-gray-50 dark:bg-gray-900/50 p-6 rounded-xl border border-gray-100 dark:border-gray-700 flex flex-col">
                <div class="h-10 w-10 bg-indigo-100 dark:bg-indigo-900 text-indigo-600 dark:text-indigo-400 rounded-lg flex items-center justify-center text-lg mb-4">
                    📥
                </div>
                <h3 class="font-bold text-gray-900 dark:text-white mb-2">Backup Data</h3>
                <p class="text-xs text-gray-500 dark:text-gray-400 leading-relaxed flex-grow mb-6">
                    Download a full snapshot of your financial records including the database and all uploaded payslips.
                </p>
                <form method="POST" class="mt-auto">
                    <input type="hidden" name="action" value="backup">
                    <button type="submit" class="w-full bg-indigo-600 hover:bg-indigo-700 text-white py-2.5 rounded-lg text-sm font-bold transition shadow-sm">
                        Download .ZIP Backup
                    </button>
                </form>
            </div>

            <!-- Restore -->
            <div class="bg-gray-50 dark:bg-gray-900/50 p-6 rounded-xl border border-gray-100 dark:border-gray-700 flex flex-col">
                <div class="h-10 w-10 bg-amber-100 dark:bg-amber-900 text-amber-600 dark:text-amber-400 rounded-lg flex items-center justify-center text-lg mb-4">
                    🔄
                </div>
                <h3 class="font-bold text-gray-900 dark:text-white mb-2">Restore Data</h3>
                <p class="text-xs text-gray-500 dark:text-gray-400 leading-relaxed flex-grow mb-4">
                    Upload a previously downloaded backup ZIP to restore your data. <span class="text-red-500 font-bold">Caution: This will overwrite current data.</span>
                </p>
                <form method="POST" enctype="multipart/form-data" onsubmit="return confirm('WARNING: This will overwrite your current data. Are you sure?');" class="mt-auto">
                    <input type="hidden" name="action" value="restore">
                    <div class="mb-3">
                        <input type="file" name="backup_zip" accept=".zip" required 
                               class="w-full text-[10px] text-gray-500 dark:text-gray-400 file:mr-2 file:py-1 file:px-2 file:rounded file:border-0 file:bg-amber-100 dark:file:bg-amber-900 file:text-amber-700 dark:file:text-amber-400 cursor-pointer">
                    </div>
                    <button type="submit" class="w-full bg-amber-600 hover:bg-amber-700 text-white py-2.5 rounded-lg text-sm font-bold transition shadow-sm">
                        Upload & Restore
                    </button>
                </form>
            </div>
        </div>

        <!-- Sync Configuration Section -->
        <div class="border-t border-gray-100 dark:border-gray-700 pt-8">
            <div class="flex items-center justify-between mb-6">
                <h2 class="text-xl font-bold text-gray-900 dark:text-white">HA Sync Configuration</h2>
                <div class="flex gap-2">
                    <button onclick="checkAllNodes()" class="px-3 py-1.5 bg-gray-100 hover:bg-gray-200 dark:bg-gray-700 dark:hover:bg-gray-600 text-gray-600 dark:text-gray-300 text-xs font-bold rounded flex items-center gap-1 transition">
                        <span>🔄</span> Refresh Status
                    </button>
                    <!-- TODO: Implement Trigger Sync -->
                    <button class="px-3 py-1.5 bg-blue-600 hover:bg-blue-700 text-white text-xs font-bold rounded flex items-center gap-1 transition shadow-sm">
                        <span>🚀</span> Trigger Sync Now
                    </button>
                </div>
            </div>

            <!-- List Configured Nodes -->
            <div class="space-y-4 mb-8">
                <div class="flex justify-between items-center text-sm text-gray-500 dark:text-gray-400 mb-2">
                    <span>Configured Nodes: <strong><?php echo count($currentNodes); ?></strong> (Max 6 recom.)</span>
                </div>
                
                <?php foreach ($currentNodes as $index => $node): ?>
                <div class="bg-gray-50 dark:bg-gray-900/50 border border-gray-200 dark:border-gray-700 rounded-lg p-4 flex items-center justify-between group">
                    <div>
                        <div class="flex items-center gap-2 mb-1">
                            <h4 class="font-bold text-gray-900 dark:text-white"><?php echo htmlspecialchars($node['name']); ?></h4>
                            <span id="status-<?php echo $index; ?>" class="px-1.5 py-0.5 bg-gray-200 text-gray-600 text-[10px] font-bold uppercase rounded">Unknown</span>
                        </div>
                        <div class="text-xs text-gray-500 font-mono"><?php echo htmlspecialchars($node['url']); ?>/api/sync_receive.php</div>
                    </div>
                    <div class="flex gap-2">
                         <form method="POST" onsubmit="return confirm('Remove this node?');">
                            <input type="hidden" name="action" value="delete_node">
                            <input type="hidden" name="index" value="<?php echo $index; ?>">
                            <button type="submit" class="p-2 text-gray-400 hover:text-red-500 transition">🗑️</button>
                        </form>
                    </div>
                </div>
                <?php endforeach; ?>

                <!-- Add Node Form -->
                <form method="POST" class="flex gap-3 items-end bg-gray-50 dark:bg-gray-900/50 p-4 rounded-lg border border-dashed border-gray-300 dark:border-gray-700">
                    <input type="hidden" name="action" value="add_node">
                    <div class="flex-grow space-y-1">
                        <label class="text-[10px] font-bold text-gray-500 uppercase">Name (e.g. Pi Backup)</label>
                        <input type="text" name="name" required class="w-full bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded px-3 py-2 text-sm focus:ring-1 focus:ring-blue-500 outline-none">
                    </div>
                    <div class="flex-grow-[2] space-y-1">
                        <label class="text-[10px] font-bold text-gray-500 uppercase">Server URL (e.g. http://10.0.0.5:8000)</label>
                        <input type="url" name="url" required class="w-full bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded px-3 py-2 text-sm focus:ring-1 focus:ring-blue-500 outline-none">
                    </div>
                    <div class="flex-grow space-y-1">
                        <label class="text-[10px] font-bold text-gray-500 uppercase">Secret Key</label>
                        <input type="password" name="key" required class="w-full bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded px-3 py-2 text-sm focus:ring-1 focus:ring-blue-500 outline-none">
                    </div>
                    <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded text-sm font-bold shadow-sm transition h-[38px]">ADD NODE</button>
                </form>
            </div>

            <!-- Receiver Configuration -->
            <div class="mt-8">
                <div class="flex items-center gap-2 mb-4">
                    <div class="p-1.5 bg-indigo-100 dark:bg-indigo-900 text-indigo-600 dark:text-indigo-400 rounded">🛡️</div>
                    <h3 class="font-bold text-gray-900 dark:text-white">Receiver Node Configuration</h3>
                </div>
                <div class="bg-gray-50 dark:bg-gray-900/50 border border-gray-200 dark:border-gray-700 rounded-lg p-6">
                    <p class="text-xs text-gray-500 dark:text-gray-400 mb-4">
                        If this server is a <strong>Backup Node</strong>, set the Secret Key here to allow the Primary Server to sync data to it.
                    </p>
                    <form method="POST" class="flex gap-4">
                        <input type="hidden" name="action" value="update_secret">
                        <input type="text" name="secret_key" value="<?php echo htmlspecialchars($currentSecret); ?>" class="flex-grow bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded px-4 py-2 text-sm font-mono focus:ring-2 focus:ring-indigo-500 outline-none" placeholder="Generate a strong random string...">
                        <button type="submit" class="bg-indigo-600 hover:bg-indigo-700 text-white px-6 py-2 rounded-lg text-sm font-bold shadow-sm transition">Save Key</button>
                    </form>
                </div>
            </div>
        </div>

        <div class="mt-12 pt-8 border-t border-gray-100 italic text-[10px] text-gray-400 text-center">
            Finance Board v2.0 • Data is stored locally in SQLite and Uploads directory.
        </div>
    </div>
</div>

<script>
const nodes = <?php echo json_encode($currentNodes); ?>;

function checkAllNodes() {
    nodes.forEach((node, index) => {
        const badge = document.getElementById(`status-${index}`);
        badge.className = "px-1.5 py-0.5 bg-yellow-100 text-yellow-700 text-[10px] font-bold uppercase rounded animate-pulse";
        badge.innerText = "Checking...";

        fetch(`${node.url}/api/sync_receive.php?ping=1`, {
            method: 'GET',
        })
        .then(response => {
            if (response.ok) return response.text();
            throw new Error('Server Error');
        })
        .then(data => {
            if (data.trim() === 'PONG') {
                badge.className = "px-1.5 py-0.5 bg-green-100 text-green-700 text-[10px] font-bold uppercase rounded";
                badge.innerText = "Online";
            } else {
                throw new Error('Invalid Response');
            }
        })
        .catch(err => {
            badge.className = "px-1.5 py-0.5 bg-red-100 text-red-700 text-[10px] font-bold uppercase rounded cursor-help";
            badge.innerText = "Error"; // Changed from Offline to Error to signal debug info
            badge.title = err.message; // Show details on hover
            console.error(err);
        });
    });
}

// Auto-check on load
document.addEventListener('DOMContentLoaded', checkAllNodes);
</script>

<?php require_once '../includes/footer.php'; ?>
