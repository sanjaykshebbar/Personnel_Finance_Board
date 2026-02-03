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

    private function createBackupZip() {
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
}
