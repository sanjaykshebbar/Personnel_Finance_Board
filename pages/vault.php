<?php
require_once '../config/database.php';
require_once '../includes/auth.php';
requireLogin();

$userId = getCurrentUserId();
$uploadsDir = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'uploads';
$vaultDir = $uploadsDir . DIRECTORY_SEPARATOR . 'vault';
$baseVaultDir = $vaultDir . DIRECTORY_SEPARATOR . 'user_' . $userId;

// Ensure base vault directory exists (Universally)
if (!is_dir($baseVaultDir)) {
    mkdir($baseVaultDir, 0777, true);
}

// Get current directory from URL, sanitize it
$currentRelDir = $_GET['dir'] ?? '';
$currentRelDir = str_replace(['..', '\\', '//'], ['', '/', '/'], $currentRelDir); // Robust sanitation
$currentRelDir = trim($currentRelDir, '/');

// Build the full path
$fullCurrentDir = $baseVaultDir;
if ($currentRelDir) {
    $fullCurrentDir .= DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $currentRelDir);
}

// Security check: ensure the resulting path is still within the user's base vault
// We use realpath on the base to get a canonical parent for comparison
$canonicalBase = realpath($baseVaultDir);
// Ensure the full current dir exists before getting realpath to avoid false
if (!is_dir($fullCurrentDir)) {
    mkdir($fullCurrentDir, 0777, true);
}
$canonicalCurrent = realpath($fullCurrentDir);

if ($canonicalCurrent === false || strpos($canonicalCurrent, $canonicalBase) !== 0) {
    header("Location: vault.php");
    exit;
}

// Handle Actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['create_folder'])) {
        $folderName = preg_replace('/[^a-zA-Z0-9_\- ]/', '', $_POST['folder_name']);
        if ($folderName) {
            $newFolderPath = $fullCurrentDir . DIRECTORY_SEPARATOR . $folderName;
            if (!is_dir($newFolderPath)) {
                mkdir($newFolderPath, 0777);
                $_SESSION['flash_message'] = "Folder created: $folderName";
            } else {
                $_SESSION['flash_message'] = "Folder already exists.";
            }
        }
    } elseif (isset($_POST['upload_file'])) {
        $file = $_FILES['vault_file'];
        if ($file && $file['error'] === UPLOAD_ERR_OK) {
            $fileName = preg_replace('/[^a-zA-Z0-9_\-\. ]/', '', basename($file['name']));
            $targetPath = $fullCurrentDir . DIRECTORY_SEPARATOR . $fileName;
            if (move_uploaded_file($file['tmp_name'], $targetPath)) {
                $_SESSION['flash_message'] = "File uploaded: $fileName";
            } else {
                $_SESSION['flash_message'] = "Failed to upload file.";
            }
        }
    } elseif (isset($_POST['delete_item'])) {
        $itemName = basename($_POST['item_name']);
        $itemPath = $fullCurrentDir . DIRECTORY_SEPARATOR . $itemName;
        if (is_file($itemPath)) {
            unlink($itemPath);
            $_SESSION['flash_message'] = "File deleted.";
        } elseif (is_dir($itemPath)) {
            // Check if directory is empty
            $files = array_diff(scandir($itemPath), array('.', '..'));
            if (empty($files)) {
                rmdir($itemPath);
                $_SESSION['flash_message'] = "Folder deleted.";
            } else {
                $_SESSION['flash_message'] = "Error: Folder is not empty.";
            }
        }
    }
    header("Location: vault.php?dir=" . urlencode($currentRelDir));
    exit;
}

$pageTitle = 'Document Vault';
require_once '../includes/header.php';

// List contents
$items = [];
if (is_dir($fullCurrentDir)) {
    $dirContents = array_diff(scandir($fullCurrentDir), array('.', '..'));
    foreach ($dirContents as $item) {
        $itemPath = $fullCurrentDir . DIRECTORY_SEPARATOR . $item;
        $items[] = [
            'name' => $item,
            'is_dir' => is_dir($itemPath),
            'size' => is_file($itemPath) ? filesize($itemPath) : 0,
            'mtime' => filemtime($itemPath)
        ];
    }
}

// Sort items: folders first, then alphabetical
usort($items, function($a, $b) {
    if ($a['is_dir'] && !$b['is_dir']) return -1;
    if (!$a['is_dir'] && $b['is_dir']) return 1;
    return strcasecmp($a['name'], $b['name']);
});

// Breadcrumbs
$breadcrumbs = [['name' => 'Root', 'path' => '']];
if ($currentRelDir) {
    $parts = explode('/', $currentRelDir);
    $pathBuilder = '';
    foreach ($parts as $part) {
        $pathBuilder .= ($pathBuilder ? '/' : '') . $part;
        $breadcrumbs[] = ['name' => $part, 'path' => $pathBuilder];
    }
}
?>

<div class="space-y-6">
    <!-- Action Bar -->
    <div class="flex flex-col md:flex-row justify-between items-start md:items-center bg-white dark:bg-gray-800 p-4 rounded-xl shadow-sm border border-gray-100 dark:border-gray-700 gap-4">
        <!-- Breadcrumbs -->
        <nav class="flex items-center space-x-2 text-sm text-gray-500 dark:text-gray-400 overflow-x-auto whitespace-nowrap pb-2 md:pb-0">
            <?php foreach ($breadcrumbs as $i => $bc): ?>
                <?php if ($i > 0): ?><span>/</span><?php endif; ?>
                <a href="?dir=<?php echo urlencode($bc['path']); ?>" class="hover:text-brand-600 font-medium <?php echo ($i === count($breadcrumbs) - 1) ? 'text-gray-900 dark:text-white font-bold' : ''; ?>">
                    <?php echo htmlspecialchars($bc['name']); ?>
                </a>
            <?php endforeach; ?>
        </nav>

        <div class="flex items-center space-x-3 w-full md:w-auto">
            <button onclick="document.getElementById('newFolderModal').classList.remove('hidden')" class="flex-1 md:flex-none px-4 py-2 bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-200 rounded-lg text-xs font-bold hover:bg-gray-200 transition">
                + New Folder
            </button>
            <button onclick="document.getElementById('uploadFileModal').classList.remove('hidden')" class="flex-1 md:flex-none px-4 py-2 bg-brand-600 text-white rounded-lg text-xs font-bold hover:bg-brand-700 shadow-md transition">
                ↑ Upload File
            </button>
        </div>
    </div>

    <!-- Items Grid -->
    <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-6 gap-6">
        <?php foreach ($items as $item): ?>
            <div class="group relative bg-white dark:bg-gray-800 p-4 rounded-2xl border border-gray-100 dark:border-gray-700 text-center hover:shadow-lg hover:border-brand-500/50 transition-all">
                <div class="mb-3">
                    <?php if ($item['is_dir']): ?>
                        <a href="?dir=<?php echo urlencode(($currentRelDir ? $currentRelDir . '/' : '') . $item['name']); ?>" class="block">
                            <span class="text-5xl block transition-transform group-hover:scale-110">📂</span>
                        </a>
                    <?php else: ?>
                        <?php 
                        $ext = strtolower(pathinfo($item['name'], PATHINFO_EXTENSION));
                        $isImg = in_array($ext, ['jpg', 'jpeg', 'png', 'webp', 'gif']);
                        ?>
                        <div class="relative h-16 flex items-center justify-center">
                            <?php if ($isImg): ?>
                                <img src="../uploads/vault/user_<?php echo $userId; ?>/<?php echo ($currentRelDir ? $currentRelDir . '/' : '') . $item['name']; ?>" 
                                     class="max-h-full max-w-full rounded-lg shadow-sm" alt="">
                            <?php else: ?>
                                <span class="text-5xl block">📄</span>
                            <?php endif; ?>
                            
                            <div class="absolute inset-0 bg-black/40 opacity-0 group-hover:opacity-100 flex items-center justify-center rounded-lg transition-opacity">
                                <a href="../uploads/vault/user_<?php echo $userId; ?>/<?php echo ($currentRelDir ? $currentRelDir . '/' : '') . $item['name']; ?>" 
                                   download class="p-2 bg-white text-gray-900 rounded-full shadow-lg hover:scale-110 transition">📥</a>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>

                <div class="text-xs font-bold text-gray-900 dark:text-white truncate px-1" title="<?php echo htmlspecialchars($item['name']); ?>">
                    <?php echo htmlspecialchars($item['name']); ?>
                </div>
                <div class="text-[10px] text-gray-400 mt-1 uppercase font-bold tracking-tighter">
                    <?php echo $item['is_dir'] ? 'Folder' : ($isImg ? 'Image' : 'Document'); ?>
                </div>

                <!-- Delete Action -->
                <form method="POST" onsubmit="return confirm('Delete this <?php echo $item['is_dir'] ? 'folder' : 'file'; ?>?');" class="absolute top-2 right-2 opacity-0 group-hover:opacity-100 transition-opacity">
                    <input type="hidden" name="delete_item" value="1">
                    <input type="hidden" name="item_name" value="<?php echo htmlspecialchars($item['name']); ?>">
                    <button type="submit" class="p-1.5 bg-red-50 text-red-500 rounded-lg hover:bg-red-500 hover:text-white transition">🗑️</button>
                </form>
            </div>
        <?php endforeach; ?>

        <?php if (empty($items)): ?>
            <div class="col-span-full py-20 text-center">
                <div class="text-6xl mb-6 opacity-20">📁</div>
                <h4 class="text-lg font-bold text-gray-400 uppercase tracking-widest mb-1">This folder is empty</h4>
                <p class="text-xs text-gray-400 italic">Upload a document or create a subfolder.</p>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- New Folder Modal -->
<div id="newFolderModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 hidden flex items-center justify-center p-4 z-50">
    <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-xl w-full max-w-sm p-8">
        <h2 class="text-xl font-bold text-gray-900 dark:text-white mb-2">Create New Folder</h2>
        <form method="POST" class="space-y-4">
            <input type="hidden" name="create_folder" value="1">
            <div>
                <label class="block text-xs font-bold text-gray-400 uppercase mb-2">Folder Name</label>
                <input type="text" name="folder_name" required placeholder="e.g. Rent Receipts 2026" 
                       class="w-full text-sm rounded-xl border border-gray-200 dark:border-gray-700 dark:bg-gray-900 dark:text-white p-3 outline-none focus:ring-2 focus:ring-brand-500 transition-all font-bold">
            </div>
            <div class="flex gap-4 pt-4">
                <button type="button" onclick="document.getElementById('newFolderModal').classList.add('hidden')" 
                        class="flex-1 px-4 py-3 bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-200 rounded-xl text-sm font-bold">Cancel</button>
                <button type="submit" class="flex-1 px-4 py-3 bg-brand-600 text-white rounded-xl text-sm font-bold shadow-lg shadow-brand-600/20">Create</button>
            </div>
        </form>
    </div>
</div>

<!-- Upload File Modal -->
<div id="uploadFileModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 hidden flex items-center justify-center p-4 z-50">
    <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-xl w-full max-w-sm p-8">
        <h2 class="text-xl font-bold text-gray-900 dark:text-white mb-2">Upload Document</h2>
        <form method="POST" enctype="multipart/form-data" class="space-y-4">
            <input type="hidden" name="upload_file" value="1">
            <div class="group relative bg-gray-50 dark:bg-gray-900 border-2 border-dashed border-gray-200 dark:border-gray-800 p-8 rounded-2xl transition-all hover:border-brand-500/50 hover:bg-brand-50/10">
                <input type="file" name="vault_file" required 
                       class="absolute inset-0 w-full h-full opacity-0 cursor-pointer z-10">
                <div class="text-center space-y-2">
                    <span class="text-3xl block transition-transform group-hover:scale-110">📤</span>
                    <span class="text-xs font-bold text-gray-500 block uppercase tracking-tight">Select File</span>
                </div>
            </div>
            <div class="flex gap-4 pt-4">
                <button type="button" onclick="document.getElementById('uploadFileModal').classList.add('hidden')" 
                        class="flex-1 px-4 py-3 bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-200 rounded-xl text-sm font-bold">Cancel</button>
                <button type="submit" class="flex-1 px-4 py-3 bg-brand-600 text-white rounded-xl text-sm font-bold shadow-lg shadow-brand-600/20">Upload</button>
            </div>
        </form>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>
