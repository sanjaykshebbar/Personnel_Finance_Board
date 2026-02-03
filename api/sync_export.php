<?php
// api/sync_export.php

set_time_limit(300);

require_once '../config/database.php';
require_once '../includes/SyncManager.php';

$rootDir = realpath(__DIR__ . '/../');

// 1. Validation
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    die("Method Not Allowed");
}

$secret = $_POST['secret'] ?? '';
$secretFile = $rootDir . '/config/sync_secret.txt';

if (!file_exists($secretFile)) {
    http_response_code(403);
    die("Setup Error: config/sync_secret.txt missing on this server.");
}

$expectedSecret = trim(file_get_contents($secretFile));
if ($secret !== $expectedSecret) {
    http_response_code(401);
    die("Unauthorized: Invalid Secret");
}

// 2. Create Backup
try {
    $sm = new SyncManager();
    // Using the public createBackupZip we just exposed
    $zipPath = $sm->createBackupZip();

    if ($zipPath && file_exists($zipPath)) {
        // 3. Serve File
        header('Content-Type: application/zip');
        header('Content-Disposition: attachment; filename="backup_export.zip"');
        header('Content-Length: ' . filesize($zipPath));
        
        readfile($zipPath);
        
        // Cleanup
        @unlink($zipPath);
        exit;
    } else {
        throw new Exception("Failed to create backup archive.");
    }
} catch (Exception $e) {
    http_response_code(500);
    die("Export Error: " . $e->getMessage());
}
