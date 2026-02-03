<?php
require_once '../config/database.php';
require_once '../includes/auth.php';
require_once '../includes/SyncManager.php';
requireLogin();

$userId = getCurrentUserId();
$message = '';
$error = '';

// Handle Actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        if ($_POST['action'] === 'add_node') {
            $sm = new SyncManager();
            $sm->addNode($_POST['node_name'], $_POST['node_url'], $_POST['node_secret']);
            $message = "Backup node added successfully.";
        } elseif ($_POST['action'] === 'remove_node') {
            $sm = new SyncManager();
            $sm->removeNode($_POST['node_id']);
            $message = "Backup node removed.";
        } elseif ($_POST['action'] === 'trigger_sync') {
            $sm = new SyncManager();
            $res = $sm->triggerSync();
            if ($res['status'] === 'success') {
                $message = "Sync Report: <br>" . implode("<br>", $res['results']);
            } else {
                $error = "Sync Failed: " . $res['message'];
            }
        } elseif ($_POST['action'] === 'trigger_sync') {
            $sm = new SyncManager();
            $res = $sm->triggerSync();
            if ($res['status'] === 'success') {
                $message = "Sync Report: <br>" . implode("<br>", $res['results']);
            } else {
                $error = "Sync Failed: " . $res['message'];
            }
        } elseif ($_POST['action'] === 'set_receiver_secret') {
            $secret = trim($_POST['receiver_secret']);
            if (!empty($secret)) {
                $configDir = '../config';
                if (!is_dir($configDir)) mkdir($configDir, 0755, true);
                if (file_put_contents($configDir . '/sync_secret.txt', $secret)) {
                    $message = "Receiver Secret Key updated successfully.";
                } else {
                    $error = "Failed to save secret key. Check permissions.";
                }
            } else {
                $error = "Secret key cannot be empty.";
            }
        } elseif ($_POST['action'] === 'backup') {
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
                $uploadDir = '../uploads';
                if (is_dir($uploadDir)) {
                    $realUploadDir = realpath($uploadDir);
                    $files = new RecursiveIteratorIterator(
                        new RecursiveDirectoryIterator($realUploadDir, RecursiveDirectoryIterator::SKIP_DOTS),
                        RecursiveIteratorIterator::LEAVES_ONLY
                    );

                    foreach ($files as $name => $file) {
                        $filePath = $file->getRealPath();
                        $relativePath = 'uploads/' . substr($filePath, strlen($realUploadDir) + 1);
                        $zip->addFile($filePath, $relativePath);
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

                        // 2. Restore Uploads (Vault)
                        // Note: We use the same recursive copy logic to ensure the vault is fully restored
                        if (is_dir($extractPath . '/uploads')) {
                            $src = $extractPath . '/uploads';
                            $dst = '../uploads';
                            
                            // Ensure destination exists
                            if (!is_dir($dst)) @mkdir($dst, 0777, true);
                            
                            $iterator = new RecursiveIteratorIterator(
                                new RecursiveDirectoryIterator($src, RecursiveDirectoryIterator::SKIP_DOTS),
                                RecursiveIteratorIterator::SELF_FIRST
                            );
                            
                            foreach ($iterator as $item) {
                                $target = $dst . DIRECTORY_SEPARATOR . $iterator->getSubPathName();
                                if ($item->isDir()) {
                                    if (!is_dir($target)) @mkdir($target, 0777, true);
                                } else {
                                    copy($item->getRealPath(), $target);
                                }
                            }
                            $message .= "Document Vault restored successfully.";
                        }
                        
                        // Cleanup temp
                        $this_recursiveRemove = function($dir) use (&$this_recursiveRemove) {
                            if (!is_dir($dir)) return;
                            $files = array_diff(scandir($dir), array('.','..'));
                            foreach ($files as $file) {
                                (is_dir("$dir/$file")) ? $this_recursiveRemove("$dir/$file") : unlink("$dir/$file");
                            }
                            return rmdir($dir);
                        };
                        $this_recursiveRemove($extractPath);
                        
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
        }
    }
}

if (isset($_GET['message'])) $message = $_GET['message'];

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

        <div class="grid grid-cols-1 md:grid-cols-2 gap-8 items-stretch">
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

        <!-- High Availability Sync -->
        <?php 
        $sm = new SyncManager();
        $nodes = $sm->getNodes();
        ?>
        <div class="mt-12">
            <h3 class="font-bold text-gray-900 dark:text-white mb-4 flex items-center gap-2">
                <span class="bg-blue-100 text-blue-600 p-1 rounded text-sm">📡</span> High Availability Sync Cluster
                <a href="maintenance_sync.php" class="ml-auto text-xs font-medium text-blue-600 hover:underline flex items-center gap-1">
                    How to Connect/Setup? <span>↗</span>
                </a>
            </h3>
            
            <div class="bg-white dark:bg-gray-900 rounded-xl shadow-sm border border-gray-100 dark:border-gray-700 overflow-hidden">
                <!-- Sync Trigger -->
                <div class="p-4 bg-gray-50 dark:bg-gray-800 border-b border-gray-100 dark:border-gray-700 flex justify-between items-center">
                    <p class="text-xs text-gray-500 dark:text-gray-400">
                        Configured Nodes: <strong><?php echo count($nodes); ?></strong> (Max 6 recom.)
                    </p>
                    <form method="POST">
                        <input type="hidden" name="action" value="trigger_sync">
                        <button class="px-3 py-1.5 bg-blue-600 hover:bg-blue-700 text-white rounded-lg text-xs font-bold transition flex items-center gap-1">
                            🚀 Trigger Sync Now
                        </button>
                    </form>
                </div>

                <!-- Node List -->
                <div class="divide-y divide-gray-100 dark:divide-gray-700">
                    <?php if (empty($nodes)): ?>
                        <div class="p-6 text-center text-sm text-gray-400 italic">No backup nodes configured. Add one below.</div>
                    <?php else: ?>
                        <?php foreach($nodes as $node): ?>
                        <div class="p-4 flex items-center justify-between hover:bg-gray-50 dark:hover:bg-gray-800/50 transition">
                            <div>
                                <h4 class="font-bold text-sm text-gray-900 dark:text-white"><?php echo htmlspecialchars($node['name']); ?></h4>
                                <div class="flex items-center gap-2 mt-1">
                                    <span class="text-[10px] text-gray-400 font-mono"><?php echo htmlspecialchars($node['url']); ?></span>
                                    <?php if($node['last_sync']): ?>
                                        <span class="text-[9px] px-1.5 py-0.5 rounded bg-green-50 text-green-600 border border-green-100">Last: <?php echo $node['last_sync']; ?></span>
                                    <?php else: ?>
                                        <span class="text-[9px] px-1.5 py-0.5 rounded bg-gray-100 text-gray-500">Never Synced</span>
                                    <?php endif; ?>
                                    <span class="text-[9px] px-1.5 py-0.5 rounded <?php echo $node['status'] === 'Online' ? 'bg-emerald-100 text-emerald-600' : 'bg-red-50 text-red-500'; ?>">
                                        <?php echo $node['status']; ?>
                                    </span>
                                </div>
                            </div>
                            <form method="POST" onsubmit="return confirm('Remove this node?');">
                                <input type="hidden" name="action" value="remove_node">
                                <input type="hidden" name="node_id" value="<?php echo $node['id']; ?>">
                                <button class="text-xs text-red-300 hover:text-red-600 p-2 font-bold opacity-0 group-hover:opacity-100 transition">REMOVE</button>
                            </form>
                        </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>

                <!-- Add Node Form -->
                <div class="p-4 bg-gray-50 dark:bg-gray-800 border-t border-gray-100 dark:border-gray-700">
                    <form method="POST" class="flex flex-col md:flex-row gap-2 items-center">
                        <input type="hidden" name="action" value="add_node">
                        <input type="text" name="node_name" placeholder="Name (e.g. Pi Backup)" required class="w-full md:w-auto flex-1 bg-white dark:bg-gray-900 border-none rounded-lg px-3 py-2 text-xs focus:ring-1 focus:ring-blue-500">
                        <input type="url" name="node_url" placeholder="URL (http://192.168.1.5/app)" required class="w-full md:w-auto flex-[2] bg-white dark:bg-gray-900 border-none rounded-lg px-3 py-2 text-xs focus:ring-1 focus:ring-blue-500">
                        <input type="password" name="node_secret" placeholder="Secret Key" required class="w-full md:w-auto flex-1 bg-white dark:bg-gray-900 border-none rounded-lg px-3 py-2 text-xs focus:ring-1 focus:ring-blue-500">
                        <button class="w-full md:w-auto px-6 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-lg text-xs font-bold shadow-lg shadow-blue-500/30 transition uppercase tracking-wider">Add Node</button>
                    </form>
                </div>
            </div>
            
            <p class="mt-4 text-[10px] text-gray-400">
                <strong>Setup:</strong> To configure <em>this machine</em> as a Backup Node, scroll down to the <strong>Receiver Node Configuration</strong> section below.
            </p>
            <div class="mt-8 pt-8 border-t border-gray-100 dark:border-gray-700">
                <h3 class="font-bold text-gray-900 dark:text-white mb-4 flex items-center gap-2">
                    <span class="bg-purple-100 text-purple-600 p-1 rounded text-sm">🛡️</span> Receiver Node Configuration
                </h3>
                <div class="bg-white dark:bg-gray-900 p-6 rounded-xl border border-gray-200 dark:border-gray-700">
                    <p class="text-sm text-gray-500 dark:text-gray-400 mb-4">
                        If this server is a <strong>Backup Node</strong>, set the Secret Key here to allow the Primary Server to push data.
                    </p>
                    
                    <?php 
                    $hasSecret = file_exists('../config/sync_secret.txt');
                    $currentSecret = $hasSecret ? trim(file_get_contents('../config/sync_secret.txt')) : '';
                    ?>
                    
                    <form method="POST" class="flex gap-3 items-end">
                        <input type="hidden" name="action" value="set_receiver_secret">
                        <div class="flex-grow">
                            <label class="block text-xs font-bold text-gray-500 uppercase mb-1">Receiver Secret Key</label>
                            <input type="text" name="receiver_secret" value="<?php echo htmlspecialchars($currentSecret); ?>" required 
                                   class="w-full px-4 py-2 bg-gray-50 dark:bg-gray-800 border border-gray-200 dark:border-gray-600 rounded-lg text-sm text-gray-900 dark:text-white focus:ring-2 focus:ring-purple-500 outline-none"
                                   placeholder="Enter a strong secret key">
                        </div>
                        <button type="submit" class="px-5 py-2 bg-purple-600 hover:bg-purple-700 text-white rounded-lg text-sm font-bold transition shadow-sm whitespace-nowrap">
                            Save Secret
                        </button>
                    </form>
                    <?php if($hasSecret): ?>
                        <div class="mt-3 flex items-center gap-2 text-xs text-green-600 dark:text-green-400 bg-green-50 dark:bg-green-900/20 px-3 py-2 rounded">
                            <span>✅ Active & Ready to Receive Data</span>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

        </div>

        <div class="mt-12 pt-8 border-t border-gray-100 italic text-[10px] text-gray-400 text-center">
            Finance Board v2.0.1 • Data is stored locally in SQLite and Uploads directory.
        </div>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>
