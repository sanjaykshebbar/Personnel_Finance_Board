<?php
// api/sync_receive.php

// Disable timeout for large syncs
set_time_limit(300);

require_once '../config/database.php'; // For any DB utils, mostly we need paths
// Since this is a standalone entry point, define paths relative to this file
$rootDir = realpath(__DIR__ . '/../');

// 0. Heartbeat (Ping)
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['ping'])) {
    header('Content-Type: text/plain');
    echo "PONG";
    exit;
}

// 1. Validation
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    die("Method Not Allowed");
}

$secret = $_POST['secret'] ?? '';
// In a real scenario, this secret should be checked against a local config or env var.
// For this flexible setup, we can store the "Expected Secret" in config/sync_secret.txt
// If the file doesn't exist, we might reject or allow initial setup. 
// Let's enforce: User must create 'config/sync_secret.txt' on Backup Server to authorize.

$secretFile = $rootDir . '/config/sync_secret.txt';
if (!file_exists($secretFile)) {
    http_response_code(403);
    die("Setup Error: config/sync_secret.txt missing on backup server.");
}

$expectedSecret = trim(file_get_contents($secretFile));
if ($secret !== $expectedSecret) {
    http_response_code(401);
    die("Unauthorized: Invalid Secret");
}

// 2. Receive File
if (!isset($_FILES['backup_file']) || $_FILES['backup_file']['error'] !== UPLOAD_ERR_OK) {
    http_response_code(400);
    die("Upload Failed");
}

$tempZip = $_FILES['backup_file']['tmp_name'];
$zip = new ZipArchive;
if ($zip->open($tempZip) === TRUE) {
    
    // 3. Maintenance Mode (Optional: Create lock file)
    $lockFile = $rootDir . '/maintenance.lock';
    touch($lockFile);

    try {
        // 4. Extract
        // We extract to a temp folder first to verify, then swap?
        // For simplicity: Direct overwrite is requested "complete data in sync"
        
        $zip->extractTo($rootDir . '/temp_sync_extract/');
        $zip->close();

        // 5. Swap DB
        $newDb = $rootDir . '/temp_sync_extract/finance.db';
        if (file_exists($newDb)) {
            // Backup existing just in case? No, "Sync" implies strict mirror.
            copy($newDb, $rootDir . '/db/finance.db');
        }

        // 6. Merge Uploads
        $newUploads = $rootDir . '/temp_sync_extract/uploads/';
        if (is_dir($newUploads)) {
            // Recursive copy/move
            copydir($newUploads, $rootDir . '/uploads/');
        }

        // Cleanup
        delTree($rootDir . '/temp_sync_extract/');
        unlink($lockFile);
        
        echo "OK";

    } catch (Exception $e) {
        unlink($lockFile);
        http_response_code(500);
        die("Extraction Error: " . $e->getMessage());
    }

} else {
    http_response_code(500);
    die("Invalid Zip File");
}

// Helpers
function copydir($source, $dest) {
    if (!is_dir($dest)) mkdir($dest, 0777, true);
    $dir = opendir($source);
    while (false !== ($file = readdir($dir))) {
        if ($file == '.' || $file == '..') continue;
        if (is_dir($source . '/' . $file)) {
            copydir($source . '/' . $file, $dest . '/' . $file);
        } else {
            copy($source . '/' . $file, $dest . '/' . $file);
        }
    }
    closedir($dir);
}

function delTree($dir) { 
   $files = array_diff(scandir($dir), array('.','..')); 
    foreach ($files as $file) { 
      (is_dir("$dir/$file")) ? delTree("$dir/$file") : unlink("$dir/$file"); 
    } 
    return rmdir($dir); 
} 
?>
