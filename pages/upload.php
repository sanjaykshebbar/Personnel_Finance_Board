<?php
require_once '../config/database.php';
require_once '../includes/auth.php';
requireLogin();

$pageTitle = 'Import Statements';
require_once '../includes/header.php';

$userId = getCurrentUserId();

$output = '';

// Load Payment Methods dynamically from credit_accounts + default options
$paymentMethods = ['Bank Account', 'Cash']; // Default options
$creditStmt = $pdo->prepare("SELECT provider_name FROM credit_accounts WHERE user_id = ? ORDER BY provider_name");
$creditStmt->execute([$userId]);
while ($row = $creditStmt->fetch()) {
    $paymentMethods[] = $row['provider_name'];
}


if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['file_upload'])) {
    $targetDir = __DIR__ . '/../uploads/';
    if (!is_dir($targetDir)) mkdir($targetDir);
    
    $fileName = basename($_FILES['file_upload']['name']);
    $targetFilePath = $targetDir . time() . '_' . $fileName;
    $fileType = strtolower(pathinfo($targetFilePath, PATHINFO_EXTENSION));
    
    $allowed = ['pdf', 'csv', 'xls', 'xlsx'];
    
    if (in_array($fileType, $allowed)) {
        if (move_uploaded_file($_FILES['file_upload']['tmp_name'], $targetFilePath)) {
            $method = escapeshellarg($_POST['payment_method']);
            $dbPath = escapeshellarg(__DIR__ . '/../db/finance.db');
            $scriptPath = escapeshellarg(__DIR__ . '/../scripts/import_data.py'); // Updated script name
            $filePath = escapeshellarg($targetFilePath);
            $uId = escapeshellarg($userId);
            $ocrFlag = isset($_POST['use_ocr']) ? " --ocr" : "";
            
            // Execute Python Script
            $cmd = "python $scriptPath $filePath $dbPath $method $uId$ocrFlag 2>&1";
            $output = shell_exec($cmd);
            
            if (strpos($output, 'Successfully imported') !== false || strpos($output, 'Successfully recorded') !== false) {
                $_SESSION['flash_message'] = "Import Success!";
            } else {
                $_SESSION['flash_message'] = "Import Finished.";
            }
            $_SESSION['import_output'] = $output;
        } else {
            $_SESSION['flash_message'] = "Error uploading file.";
        }
    } else {
        $_SESSION['flash_message'] = "Only PDF, CSV, and Excel files are allowed.";
    }
    header("Location: upload.php");
    exit;
}

$output = $_SESSION['import_output'] ?? '';
unset($_SESSION['import_output']);
?>

<div class="max-w-2xl mx-auto space-y-6">
    <div class="bg-white p-6 rounded-lg shadow">
        <h2 class="text-xl font-bold mb-4">Import Statement</h2>
        <p class="text-sm text-gray-500 mb-6">
            Upload your Bank or Credit Card statement. Supported formats:
            <br>
            <span class="inline-flex items-center space-x-2 mt-2">
                <span class="px-2 py-1 bg-red-100 text-red-800 rounded text-xs font-bold">PDF</span>
                <span class="px-2 py-1 bg-green-100 text-green-800 rounded text-xs font-bold">Excel (XLSX/XLS)</span>
                <span class="px-2 py-1 bg-blue-100 text-blue-800 rounded text-xs font-bold">CSV</span>
            </span>
        </p>

        <?php if ($output): ?>
            <div class="bg-brand-50 border border-brand-200 p-4 rounded mb-4">
                <p class="text-xs font-bold text-brand-700 mb-2 uppercase tracking-wider">Console Output</p>
                <pre class="mt-2 text-[10px] bg-gray-900 text-emerald-400 p-4 rounded-xl shadow-inner overflow-x-auto leading-relaxed"><?php echo htmlspecialchars($output); ?></pre>
            </div>
        <?php endif; ?>

        <form method="POST" enctype="multipart/form-data" class="space-y-4">
            <div>
                <label class="block text-sm font-bold text-gray-700 mb-2">Select Payment Method (Target)</label>
                <select name="payment_method" class="w-full border p-2 rounded">
                    <?php foreach($paymentMethods as $m) echo "<option>$m</option>"; ?>
                </select>
            </div>
            
            <div>
                <label class="block text-sm font-bold text-gray-700 mb-2">Statement File</label>
                <input type="file" name="file_upload" accept=".pdf,.csv,.xls,.xlsx" required class="w-full border p-2 rounded bg-gray-50 mb-3">
                <div class="flex items-center space-x-2">
                    <input type="checkbox" name="use_ocr" id="use_ocr" class="w-4 h-4 text-brand-600 border-gray-300 rounded focus:ring-brand-500">
                    <label for="use_ocr" class="text-xs text-gray-600 font-medium">Enable OCR Mode (For scanned/image-based PDFs)</label>
                </div>
                <p class="text-[10px] text-gray-400 mt-1 italic">Note: OCR Mode requires Tesseract OCR and is slower but supports scanned images.</p>
            </div>

            <button type="submit" class="w-full bg-brand-600 hover:bg-brand-700 text-white font-bold py-3 rounded transition">
                Upload & Process
            </button>
        </form>
    </div>
</div>
<?php require_once '../includes/footer.php'; ?>
