<?php
// includes/SyncManager.php

class SyncManager {
    private $configFile;
    private $nodes = [];

    public function __construct() {
        $this->configFile = __DIR__ . '/../config/sync_nodes.json';
        if (file_exists($this->configFile)) {
            $this->nodes = json_decode(file_get_contents($this->configFile), true) ?? [];
        }
    }

    public function getNodes() {
        return $this->nodes;
    }

    public function addNode($name, $url, $secret) {
        // Ensure URL has sync endpoint
        $url = rtrim($url, '/');
        if (!str_ends_with($url, 'api/sync_receive.php')) {
            $url .= '/api/sync_receive.php';
        }

        $this->nodes[] = [
            'id' => uniqid(),
            'name' => $name,
            'url' => $url,
            'secret' => $secret,
            'last_sync' => null,
            'status' => 'Pending'
        ];
        $this->save();
    }

    public function removeNode($id) {
        $this->nodes = array_filter($this->nodes, fn($n) => $n['id'] !== $id);
        $this->save();
    }

    private function save() {
        if (!is_dir(dirname($this->configFile))) mkdir(dirname($this->configFile), 0777, true);
        file_put_contents($this->configFile, json_encode(array_values($this->nodes), JSON_PRETTY_PRINT));
    }

    public function triggerSync() {
        if (empty($this->nodes)) return ["status" => "error", "message" => "No backup nodes configured."];

        // 1. Create Archive of DB + Uploads
        $zipPath = $this->createBackupZip();
        if (!$zipPath) return ["status" => "error", "message" => "Failed to create backup archive."];

        $results = [];
        $mh = curl_multi_init();
        $handles = [];

        // 2. Prepare Parallel Uploads
        foreach ($this->nodes as $index => $node) {
            $ch = curl_init();
            $postFields = [
                'secret' => $node['secret'],
                'backup_file' => new CURLFile($zipPath)
            ];

            curl_setopt($ch, CURLOPT_URL, $node['url']);
            curl_setopt($ch, CURLOPT_POST, 1);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $postFields);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 30); // 30s timeout per node
            
            curl_multi_add_handle($mh, $ch);
            $handles[$index] = $ch;
        }

        // 3. Execute
        $running = null;
        do {
            curl_multi_exec($mh, $running);
        } while ($running);

        // 4. Collect Results
        foreach ($handles as $index => $ch) {
            $response = curl_multi_getcontent($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $error = curl_error($ch);
            
            $node = &$this->nodes[$index];
            if ($httpCode === 200 && trim($response) === 'OK') {
                $node['last_sync'] = date('Y-m-d H:i:s');
                $node['status'] = 'Online';
                $results[] = "✅ {$node['name']}: Synced";
            } else {
                $node['status'] = 'Error';
                $results[] = "❌ {$node['name']}: " . ($error ?: "HTTP $httpCode - $response");
            }
            curl_multi_remove_handle($mh, $ch);
            curl_close($ch);
        }
        curl_multi_close($mh);
        
        $this->save(); // Update status
        @unlink($zipPath); // Cleanup

        return ["status" => "success", "results" => $results];
    }

    public function createBackupZip() {
        $zipFile = __DIR__ . '/../temp_sync.zip';
        $rootPath = realpath(__DIR__ . '/../');
        
        $zip = new ZipArchive();
        if ($zip->open($zipFile, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== TRUE) {
            return false;
        }

        // Add Database
        $dbPath = $rootPath . '/db/finance.db';
        if (file_exists($dbPath)) {
            $zip->addFile($dbPath, 'finance.db');
        }

        // Add Uploads (Recursive)
        $uploadDir = $rootPath . '/uploads';
        if (is_dir($uploadDir)) {
            $files = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($uploadDir),
                RecursiveIteratorIterator::LEAVES_ONLY
            );

            foreach ($files as $name => $file) {
                if (!$file->isDir()) {
                    $filePath = $file->getRealPath();
                    $relativePath = 'uploads/' . substr($filePath, strlen($uploadDir) + 1);
                    $zip->addFile($filePath, $relativePath);
                }
            }
        }

        $zip->close();
        return $zipFile;
    }

    public function restoreBackup($zipPath) {
        $zip = new ZipArchive();
        if ($zip->open($zipPath) === TRUE) {
            $extractPath = sys_get_temp_dir() . '/finance_restore_' . uniqid();
            if (!mkdir($extractPath, 0777, true) && !is_dir($extractPath)) {
                return ["status" => "error", "message" => "Failed to create temp directory."];
            }
            
            $zip->extractTo($extractPath);
            $zip->close();

            // 1. Restore Database
            $dbSource = $extractPath . '/finance.db';
            if (file_exists($dbSource)) {
                $dbDest = __DIR__ . '/../db/finance.db';
                // Ensure db dir exists
                if (!is_dir(dirname($dbDest))) mkdir(dirname($dbDest), 0777, true);
                if (!copy($dbSource, $dbDest)) {
                     $this->cleanupDir($extractPath);
                     return ["status" => "error", "message" => "Failed to copy database file."];
                }
            }

            // 2. Restore Uploads
            if (is_dir($extractPath . '/uploads')) {
                $src = $extractPath . '/uploads';
                $dst = __DIR__ . '/../uploads';
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
            }
            
            $this->cleanupDir($extractPath);
            return ["status" => "success", "message" => "System restored successfully."];
        } else {
             return ["status" => "error", "message" => "Invalid ZIP file."];
        }
    }

    private function cleanupDir($dir) {
        if (!is_dir($dir)) return;
        $files = array_diff(scandir($dir), array('.','..'));
        foreach ($files as $file) {
            (is_dir("$dir/$file")) ? $this->cleanupDir("$dir/$file") : unlink("$dir/$file");
        }
        rmdir($dir);
    }

    public function checkNodeHealth($nodeId) {
        foreach ($this->nodes as &$node) {
            if ($node['id'] === $nodeId) {
                // Ping
                $ch = curl_init();
                // Ensure URL ends correctly for api/sync_receive.php
                // Actually the stored URL includes 'api/sync_receive.php'. 
                // We want to append ?ping=1
                $url = $node['url'] . '?ping=1';
                
                curl_setopt($ch, CURLOPT_URL, $url);
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($ch, CURLOPT_TIMEOUT, 5);
                
                $response = curl_exec($ch);
                $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                curl_close($ch);

                if ($httpCode === 200 && trim($response) === 'PONG') {
                    $node['status'] = 'Online';
                    $node['last_seen'] = time(); // Unix timestamp
                    $this->save();
                    return ["status" => "success", "message" => "Node is Online"];
                } else {
                    $node['status'] = 'Offline';
                    $this->save();
                    return ["status" => "error", "message" => "Node Unreachable (HTTP $httpCode)"];
                }
            }
        }
        return ["status" => "error", "message" => "Node not found"];
    }

    public function pullFromNode($nodeId) {
        foreach ($this->nodes as $node) {
            if ($node['id'] === $nodeId) {
                // Determine Export URL
                // Replace 'sync_receive.php' with 'sync_export.php'
                $exportUrl = str_replace('sync_receive.php', 'sync_export.php', $node['url']);
                
                $ch = curl_init();
                curl_setopt($ch, CURLOPT_URL, $exportUrl);
                curl_setopt($ch, CURLOPT_POST, 1);
                curl_setopt($ch, CURLOPT_POSTFIELDS, ['secret' => $node['secret']]);
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($ch, CURLOPT_TIMEOUT, 120); // Allow time for backup creation
                
                $response = curl_exec($ch);
                $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                $contentType = curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
                curl_close($ch);

                if ($httpCode === 200 && (strpos($contentType, 'zip') !== false || strpos($response, 'PK') === 0)) {
                    // Save response to temp file
                    $tempZip = sys_get_temp_dir() . '/restore_from_node_' . uniqid() . '.zip';
                    file_put_contents($tempZip, $response);
                    
                    // Restore
                    $res = $this->restoreBackup($tempZip);
                    @unlink($tempZip);
                    return $res;
                } else {
                    return ["status" => "error", "message" => "Failed to pull data. Node might not support export or check logs. (HTTP $httpCode)"];
                }
            }
        }
        return ["status" => "error", "message" => "Node not found"];
    }
}
