<?php
// config/version.php
// Version tracking for OTA updates

define('APP_VERSION', '2.2.0');
define('APP_NAME', 'Finance Board');
define('LAST_UPDATE_COMMIT', 'unknown');
define('LAST_UPDATE_DATE', 'unknown');

/**
 * Get current Git commit hash
 */
function getCurrentCommit() {
    $gitDir = __DIR__ . '/../.git';
    if (!file_exists($gitDir)) {
        return 'not-a-git-repo';
    }
    
    $headFile = $gitDir . '/HEAD';
    if (!file_exists($headFile)) {
        return 'unknown';
    }
    
    $head = trim(file_get_contents($headFile));
    
    if (strpos($head, 'ref:') === 0) {
        $ref = substr($head, 5);
        $refFile = $gitDir . '/' . trim($ref);
        if (file_exists($refFile)) {
            return substr(trim(file_get_contents($refFile)), 0, 7);
        }
    }
    
    return substr($head, 0, 7);
}

/**
 * Check if updates are available
 */
function checkForUpdates() {
    $output = [];
    $returnVar = 0;
    
    exec('cd ' . escapeshellarg(__DIR__ . '/..') . ' && git fetch origin 2>&1', $output, $returnVar);
    
    if ($returnVar !== 0) {
        return ['error' => 'Failed to fetch updates', 'output' => implode("\n", $output)];
    }
    
    exec('cd ' . escapeshellarg(__DIR__ . '/..') . ' && git rev-list HEAD...origin/$(git rev-parse --abbrev-ref HEAD) --count', $output, $returnVar);
    
    $count = intval($output[0] ?? 0);
    
    return [
        'available' => $count > 0,
        'count' => $count,
        'current_commit' => getCurrentCommit()
    ];
}

/**
 * Get update history from backups
 */
function getUpdateHistory() {
    $backupDir = __DIR__ . '/../db/backups';
    if (!is_dir($backupDir)) {
        return [];
    }
    
    $backups = glob($backupDir . '/finance_*.db');
    rsort($backups);
    
    $history = [];
    foreach (array_slice($backups, 0, 10) as $backup) {
        $filename = basename($backup);
        if (preg_match('/finance_(\d{8}_\d{6})_([a-f0-9]+)\.db/', $filename, $matches)) {
            $history[] = [
                'timestamp' => DateTime::createFromFormat('Ymd_His', $matches[1])->format('Y-m-d H:i:s'),
                'commit' => $matches[2],
                'file' => $filename,
                'size' => filesize($backup)
            ];
        }
    }
    
    return $history;
}
?>
