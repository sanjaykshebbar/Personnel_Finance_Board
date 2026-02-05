<?php
require_once '../config/database.php';
require_once '../includes/auth.php';
requireLogin();

$userId = getCurrentUserId();
$message = '';
$error = '';
$previewData = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action']) && $_POST['action'] === 'parse_csv' && isset($_FILES['csv_file'])) {
        $file = $_FILES['csv_file']['tmp_name'];
        if (($handle = fopen($file, "r")) !== FALSE) {
            $header = fgetcsv($handle, 1000, ","); // Skip header
            while (($data = fgetcsv($handle, 1000, ",")) !== FALSE) {
                if (count($data) >= 4) {
                    $previewData[] = [
                        'date' => $data[0],
                        'category' => $data[1],
                        'description' => $data[2],
                        'amount' => $data[3],
                        'payment_method' => $data[4] ?? 'Cash'
                    ];
                }
            }
            fclose($handle);
            $_SESSION['import_preview'] = $previewData;
        } else {
            $error = "File could not be opened.";
        }
    } elseif (isset($_POST['action']) && $_POST['action'] === 'confirm_import') {
        $dataToImport = $_SESSION['import_preview'] ?? [];
        if (!empty($dataToImport)) {
            try {
                $pdo->beginTransaction();
                $stmt = $pdo->prepare("INSERT INTO expenses (user_id, date, category, description, amount, payment_method) VALUES (?, ?, ?, ?, ?, ?)");
                foreach ($dataToImport as $row) {
                    $stmt->execute([
                        $userId,
                        $row['date'],
                        $row['category'],
                        $row['description'],
                        $row['amount'],
                        $row['payment_method']
                    ]);
                }
                $pdo->commit();
                unset($_SESSION['import_preview']);
                $_SESSION['flash_message'] = count($dataToImport) . " expenses imported successfully!";
                header("Location: expenses.php");
                exit;
            } catch (Exception $e) {
                $pdo->rollBack();
                $error = "Import failed: " . $e->getMessage();
            }
        }
    }
}

$pageTitle = 'Bulk Import';
require_once '../includes/header.php';
?>

<div class="max-w-4xl mx-auto space-y-6">
    <div class="bg-white dark:bg-gray-800 p-8 rounded-xl shadow-sm border border-gray-100 dark:border-gray-700">
        <h1 class="text-2xl font-bold text-gray-900 dark:text-white mb-2">Bulk Data Import</h1>
        <p class="text-sm text-gray-500 dark:text-gray-400 mb-8">Upload a CSV file to bulk add expenses. Download the <a href="?template=1" class="text-brand-500 font-bold hover:underline">template here</a>.</p>

        <?php if ($error): ?>
            <div class="bg-red-50 border border-red-100 text-red-700 p-4 rounded-lg mb-6 text-sm flex items-center">
                <span class="mr-2">⚠️</span> <?php echo $error; ?>
            </div>
        <?php endif; ?>

        <?php if (empty($_SESSION['import_preview'])): ?>
            <form method="POST" enctype="multipart/form-data" class="space-y-4">
                <input type="hidden" name="action" value="parse_csv">
                <div class="border-2 border-dashed border-gray-200 dark:border-gray-700 rounded-xl p-12 text-center">
                    <input type="file" name="csv_file" accept=".csv" required id="csv_file" class="hidden">
                    <label for="csv_file" class="cursor-pointer group">
                        <div class="h-16 w-16 bg-blue-50 dark:bg-blue-900/30 text-blue-600 dark:text-blue-400 rounded-full flex items-center justify-center mx-auto mb-4 group-hover:scale-110 transition-transform text-2xl">
                            📄
                        </div>
                        <p class="text-gray-900 dark:text-white font-bold mb-1">Click to upload CSV</p>
                        <p class="text-xs text-gray-500">Max size 5MB (Date, Category, Description, Amount, Method)</p>
                    </label>
                </div>
                <button type="submit" class="w-full bg-brand-500 hover:bg-brand-600 text-white font-bold py-3 rounded-xl transition shadow-lg shadow-brand-500/20">
                    Parse CSV & Preview
                </button>
            </form>
        <?php else: ?>
            <div class="overflow-x-auto rounded-xl border border-gray-100 dark:border-gray-700 mb-6">
                <table class="w-full text-left text-sm">
                    <thead class="bg-gray-50 dark:bg-gray-900/50 text-gray-500 font-bold">
                        <tr>
                            <th class="px-4 py-3">Date</th>
                            <th class="px-4 py-3">Category</th>
                            <th class="px-4 py-3">Description</th>
                            <th class="px-4 py-3">Amount</th>
                            <th class="px-4 py-3">Method</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                        <?php foreach ($_SESSION['import_preview'] as $row): ?>
                            <tr class="dark:text-gray-300">
                                <td class="px-4 py-3"><?php echo htmlspecialchars($row['date']); ?></td>
                                <td class="px-4 py-3"><?php echo htmlspecialchars($row['category']); ?></td>
                                <td class="px-4 py-3"><?php echo htmlspecialchars($row['description']); ?></td>
                                <td class="px-4 py-3 font-mono">₹<?php echo number_format($row['amount'], 2); ?></td>
                                <td class="px-4 py-3"><?php echo htmlspecialchars($row['payment_method']); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <div class="flex gap-4">
                <form method="POST" class="flex-1">
                    <input type="hidden" name="action" value="confirm_import">
                    <button type="submit" class="w-full bg-emerald-600 hover:bg-emerald-700 text-white font-bold py-3 rounded-xl transition shadow-lg shadow-emerald-500/20">
                        Confirm & Import <?php echo count($_SESSION['import_preview']); ?> Entries
                    </button>
                </form>
                <a href="import.php" class="px-6 py-3 bg-gray-100 dark:bg-gray-700 text-gray-600 dark:text-gray-300 font-bold rounded-xl hover:bg-gray-200 transition">
                    Clear / Cancel
                </a>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php 
if (isset($_GET['template'])) {
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="expense_template.csv"');
    $output = fopen('php://output', 'w');
    fputcsv($output, ['Date (YYYY-MM-DD)', 'Category', 'Description', 'Amount', 'Payment Method']);
    fputcsv($output, ['2026-01-01', 'Food', 'Dinner at Restaurant', '500', 'Cash']);
    fputcsv($output, ['2026-01-02', 'Shopping', 'Amazon Purchase', '1200', 'HDFC Credit Card']);
    fclose($output);
    exit;
}
require_once '../includes/footer.php'; 
?>
