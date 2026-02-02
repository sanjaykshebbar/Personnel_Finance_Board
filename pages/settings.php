<?php
require_once '../config/database.php';
require_once '../includes/auth.php';
requireLogin();

$userId = getCurrentUserId();
$message = '';
$error = '';

// Handle Actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
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

        <div class="mt-12 pt-8 border-t border-gray-100 italic text-[10px] text-gray-400 text-center">
            Finance Board v2.0 • Data is stored locally in SQLite and Uploads directory.
        </div>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>
