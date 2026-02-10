<?php
// api/trigger_sync.php
require_once '../config/database.php';
require_once '../includes/auth.php';

// Ensure only logged-in users can trigger sync
requireLogin();

header('Content-Type: application/json');

$action = $_GET['step'] ?? '';
$rootDir = realpath(__DIR__ . '/../');

try {
    if ($action === 'create_backup') {
        // 1. Create ZIP of DB and Uploads
        $zipName = 'sync_packet_' . date('Ymd_His') . '_' . uniqid() . '.zip';
        $zipPath = sys_get_temp_dir() . '/' . $zipName;

        $zip = new ZipArchive();
        if ($zip->open($zipPath, ZipArchive::CREATE) !== TRUE) {
            throw new Exception("Could not create ZIP file at $zipPath");
        }

        // Add Database
        $dbFile = $rootDir . '/db/finance.db';
        if (file_exists($dbFile)) {
            $zip->addFile($dbFile, 'finance.db');
        } else {
            throw new Exception("Database file not found!");
        }

        // Add Uploads
        $uploadDir = $rootDir . '/uploads/';
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
        
        echo json_encode(['status' => 'success', 'zip_path' => $zipPath, 'message' => 'Backup created successfully']);

    } elseif ($action === 'push_node') {
        // 2. Push to specific node
        $nodeIndex = $_POST['node_index'] ?? null;
        $zipPath = $_POST['zip_path'] ?? '';

        if ($nodeIndex === null || !$zipPath || !file_exists($zipPath)) {
            throw new Exception("Invalid parameters or missing backup file.");
        }

        $nodesFile = $rootDir . '/config/sync_nodes.json';
        if (!file_exists($nodesFile)) {
            throw new Exception("No nodes configured.");
        }
        
        $nodes = json_decode(file_get_contents($nodesFile), true) ?? [];
        if (!isset($nodes[$nodeIndex])) {
            throw new Exception("Node not found.");
        }

        $node = $nodes[$nodeIndex];
        $targetUrl = rtrim($node['url'], '/') . '/api/sync_receive.php';
        $secret = $node['key'];

        // CURL Push
        $ch = curl_init();
        $cfile = new CURLFile($zipPath, 'application/zip', 'backup_file');
        
        $data = [
            'secret' => $secret,
            'backup_file' => $cfile
        ];

        curl_setopt($ch, CURLOPT_URL, $targetUrl);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 300); // 5 min timeout for large files

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($httpCode === 200 && trim($response) === 'OK') {
            echo json_encode(['status' => 'success', 'message' => "Synced to {$node['name']}"]);
        } else {
            // Try to extract error message from response if possible, or use curl error
            $errMsg = $error ?: "HTTP $httpCode: " . substr($response, 0, 100);
            echo json_encode(['status' => 'error', 'message' => $errMsg]);
        }

    } elseif ($action === 'cleanup') {
        // 3. Delete Temp Zip
        $zipPath = $_POST['zip_path'] ?? '';
        if ($zipPath && file_exists($zipPath)) {
            unlink($zipPath);
        }
        echo json_encode(['status' => 'success']);

    } else {
        throw new Exception("Invalid action step.");
    }

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
?>
