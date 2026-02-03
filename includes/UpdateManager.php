<?php
/**
 * Universal Update Manager
 * Cross-platform update system for Docker, Linux, Windows, Raspberry Pi
 */

class UpdateManager {
    private $appDir;
    private $dbPath;
    private $backupDir;
    private $logFile;
    
    public function __construct() {
        $this->appDir = dirname(__DIR__);
        $this->dbPath = $this->appDir . '/db/finance.db';
        $this->backupDir = $this->appDir . '/db/backups';
        $this->logFile = $this->appDir . '/update.log';
        
        // Create backup directory if it doesn't exist
        if (!is_dir($this->backupDir)) {
            mkdir($this->backupDir, 0755, true);
        }
    }
    
    /**
     * Log message to file and return for display
     */
    private function log($message, $type = 'INFO') {
        $timestamp = date('Y-m-d H:i:s');
        $logMessage = "[$timestamp] [$type] $message\n";
        file_put_contents($this->logFile, $logMessage, FILE_APPEND);
        return $logMessage;
    }
    
    /**
     * Detect operating system
     */
    private function getOS() {
        if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
            return 'windows';
        } elseif (PHP_OS === 'Linux') {
            return 'linux';
        } elseif (PHP_OS === 'Darwin') {
            return 'mac';
        }
        return 'unknown';
    }
    
    /**
     * Check if running in Docker
     */
    private function isDocker() {
        return file_exists('/.dockerenv') || file_exists('/proc/1/cgroup') && strpos(file_get_contents('/proc/1/cgroup'), 'docker') !== false;
    }
    
    /**
     * Execute command with cross-platform compatibility
     */
    private function execCommand($command, &$output = null, &$returnVar = null) {
        $os = $this->getOS();
        
        // For Windows, we need to handle commands differently
        if ($os === 'windows') {
            // Split compound commands (with &&) and execute separately
            if (strpos($command, ' && ') !== false) {
                $commands = explode(' && ', $command);
                $output = [];
                $returnVar = 0;
                
                foreach ($commands as $cmd) {
                    $cmdOutput = [];
                    $cmdReturn = 0;
                    
                    // Execute each command separately
                    exec($cmd . ' 2>&1', $cmdOutput, $cmdReturn);
                    
                    $output = array_merge($output, $cmdOutput);
                    
                    // If any command fails, stop and return error
                    if ($cmdReturn !== 0) {
                        $returnVar = $cmdReturn;
                        return false;
                    }
                }
                
                return true;
            } else {
                // Single command, execute normally
                exec($command . ' 2>&1', $output, $returnVar);
                return $returnVar === 0;
            }
        } else {
            // Linux/Mac - can use && directly
            exec($command . ' 2>&1', $output, $returnVar);
            return $returnVar === 0;
        }
    }
    
    /**
     * Check if Git is available
     */
    public function isGitAvailable() {
        $output = [];
        $returnVar = 0;
        
        if ($this->getOS() === 'windows') {
            exec('git --version 2>&1', $output, $returnVar);
        } else {
            exec('which git 2>&1', $output, $returnVar);
        }
        
        return $returnVar === 0;
    }
    
    /**
     * Check if this is a Git repository
     */
    public function isGitRepo() {
        return is_dir($this->appDir . '/.git');
    }
    
    /**
     * Get current Git commit hash
     */
    public function getCurrentCommit() {
        if (!$this->isGitRepo()) {
            return 'not-a-git-repo';
        }
        
        $output = [];
        $returnVar = 0;
        
        $os = $this->getOS();
        if ($os === 'windows') {
            $cmd = 'cd /d "' . $this->appDir . '" && git rev-parse --short HEAD';
            exec($cmd . ' 2>&1', $output, $returnVar);
        } else {
            $this->execCommand('cd "' . $this->appDir . '" && git rev-parse --short HEAD', $output, $returnVar);
        }
        
        if ($returnVar === 0 && !empty($output)) {
            return trim($output[0]);
        }
        
        return 'unknown';
    }
    
    /**
     * Get current branch name
     */
    public function getCurrentBranch() {
        if (!$this->isGitRepo()) {
            return 'N/A';
        }
        
        $output = [];
        $returnVar = 0;
        
        $os = $this->getOS();
        if ($os === 'windows') {
            $cmd = 'cd /d "' . $this->appDir . '" && git rev-parse --abbrev-ref HEAD';
            exec($cmd . ' 2>&1', $output, $returnVar);
        } else {
            $this->execCommand('cd "' . $this->appDir . '" && git rev-parse --abbrev-ref HEAD', $output, $returnVar);
        }
        
        if ($returnVar === 0 && !empty($output)) {
            return trim($output[0]);
        }
        
        return 'unknown';
    }
    
    /**
     * Check for available updates
     */
    public function checkForUpdates() {
        if (!$this->isGitAvailable()) {
            return ['error' => 'Git is not installed or not available in PATH'];
        }
        
        if (!$this->isGitRepo()) {
            return ['error' => 'This directory is not a Git repository'];
        }
        
        $this->log('Checking for updates...');
        
        // Fetch from remote
        $output = [];
        $returnVar = 0;
        
        $os = $this->getOS();
        if ($os === 'windows') {
            $cmd = 'cd /d "' . $this->appDir . '" && git fetch origin';
            exec($cmd . ' 2>&1', $output, $returnVar);
        } else {
            $this->execCommand('cd "' . $this->appDir . '" && git fetch origin', $output, $returnVar);
        }
        
        if ($returnVar !== 0) {
            return ['error' => 'Failed to fetch from remote: ' . implode("\n", $output)];
        }
        
        // Get current branch
        $branch = $this->getCurrentBranch();
        
        // Count commits behind
        $output = [];
        if ($os === 'windows') {
            $cmd = 'cd /d "' . $this->appDir . '" && git rev-list HEAD...origin/' . $branch . ' --count';
            exec($cmd . ' 2>&1', $output, $returnVar);
        } else {
            $this->execCommand('cd "' . $this->appDir . '" && git rev-list HEAD...origin/' . $branch . ' --count', $output, $returnVar);
        }
        
        $count = isset($output[0]) ? intval(trim($output[0])) : 0;
        
        return [
            'available' => $count > 0,
            'count' => $count,
            'current_commit' => $this->getCurrentCommit(),
            'branch' => $branch
        ];
    }
    
    /**
     * Create a full backup (Database + Uploads)
     */
    public function createBackup() {
        $timestamp = date('Ymd_His');
        $commit = $this->getCurrentCommit();
        $backupFile = $this->backupDir . "/finance_full_{$timestamp}_{$commit}.zip";
        
        $this->log("Creating full backup: $backupFile");
        
        $zip = new ZipArchive();
        if ($zip->open($backupFile, ZipArchive::CREATE) !== TRUE) {
            $this->log('Failed to create ZIP backup', 'ERROR');
            return ['success' => false, 'error' => 'Could not create ZIP file'];
        }

        // Add Database
        if (file_exists($this->dbPath)) {
            $zip->addFile($this->dbPath, 'finance.db');
            $this->log('Database added to backup');
        }

        // Add Uploads directory recursively
        $uploadsDir = $this->appDir . '/uploads';
        if (is_dir($uploadsDir)) {
            $files = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($uploadsDir),
                RecursiveIteratorIterator::LEAVES_ONLY
            );

            foreach ($files as $name => $file) {
                if (!$file->isDir()) {
                    $filePath = $file->getRealPath();
                    $relativePath = 'uploads/' . substr($filePath, strlen(realpath($uploadsDir)) + 1);
                    $zip->addFile($filePath, $relativePath);
                }
            }
            $this->log('Uploads directory added to backup');
        }

        $zip->close();
        
        $this->log('Full system backup completed successfully');
        return ['success' => true, 'file' => basename($backupFile)];
    }

    /**
     * @deprecated Use createBackup instead
     */
    public function backupDatabase() {
        return $this->createBackup();
    }
    
    /**
     * Perform update
     */
    public function performUpdate() {
        $this->log('========================================');
        $this->log('Starting OTA Update Process');
        $this->log('========================================');
        $this->log('OS: ' . $this->getOS());
        $this->log('Docker: ' . ($this->isDocker() ? 'Yes' : 'No'));
        
        // Step 1: Check prerequisites
        if (!$this->isGitAvailable()) {
            return ['success' => false, 'error' => 'Git is not installed'];
        }
        
        if (!$this->isGitRepo()) {
            return ['success' => false, 'error' => 'Not a Git repository'];
        }
        
        // Step 2: Get current state
        $currentCommit = $this->getCurrentCommit();
        $branch = $this->getCurrentBranch();
        if ($branch === 'unknown' || $branch === 'N/A') $branch = 'master';
        
        $this->log("Current commit: $currentCommit");
        $this->log("Current branch: $branch");
        
        // Step 3: Backup system
        $backup = $this->createBackup();
        if (!$backup['success']) {
            return $backup;
        }
        
        // Step 4: Stash local changes
        $this->log('Stashing local changes...');
        $output = [];
        $returnVar = 0;
        
        // Change to app directory and stash
        $os = $this->getOS();
        if ($os === 'windows') {
            // Windows: use cd /d to change drive and directory
            $stashCmd = 'cd /d "' . $this->appDir . '" && git stash push -m "Auto-stash before update"';
            exec($stashCmd . ' 2>&1', $output, $returnVar);
        } else {
            $this->execCommand('cd "' . $this->appDir . '" && git stash push -m "Auto-stash before update"', $output, $returnVar);
        }
        
        // Step 5: Update to latest commits from remote
        $this->log('Fetching latest changes and resetting to origin/' . $branch . '...');
        $output = [];
        
        if ($os === 'windows') {
            // Windows: fetch and reset hard to ensure local matches remote tip
            $updateCmd = 'cd /d "' . $this->appDir . '" && git fetch origin && git reset --hard origin/' . $branch;
            exec($updateCmd . ' 2>&1', $output, $returnVar);
            $success = ($returnVar === 0);
        } else {
            // Linux: fetch and reset hard to ensure local matches remote tip
            $this->execCommand('cd "' . $this->appDir . '" && git fetch origin && git reset --hard origin/' . $branch, $output, $returnVar);
            $success = ($returnVar === 0);
        }
        
        if (!$success) {
            $this->log('Failed to update code: ' . implode("\n", $output), 'ERROR');
            $this->log('Attempting to restore original state...', 'WARNING');
            // Rollback only code to previous commit if update failed
            if ($os === 'windows') {
                exec('cd /d "' . $this->appDir . '" && git reset --hard ' . $currentCommit . ' 2>&1');
            } else {
                $this->execCommand('cd "' . $this->appDir . '" && git reset --hard ' . $currentCommit, $output, $returnVar);
            }
            return ['success' => false, 'error' => 'Failed to update code', 'output' => implode("\n", $output)];
        }
        
        $newCommit = $this->getCurrentCommit();
        $this->log("Successfully updated to latest commit: $newCommit");
        
        // Step 6: Clean up old backups (keep last 10)
        $this->cleanupOldBackups();
        
        $this->log('========================================');
        $this->log('Update completed successfully!');
        $this->log("Previous commit: $currentCommit");
        $this->log("New commit: $newCommit");
        $this->log('========================================');
        
        return [
            'success' => true,
            'previous_commit' => $currentCommit,
            'new_commit' => $newCommit,
            'log' => file_get_contents($this->logFile)
        ];
    }
    
    /**
     * Rollback to a previous backup
     */
    public function rollback($backupIndex = 0, $restoreData = true) {
        $this->log('========================================');
        $this->log('Starting Rollback Process');
        $this->log('========================================');
        
        $backups = $this->getBackupList();
        
        if (empty($backups)) {
            return ['success' => false, 'error' => 'No backups available'];
        }
        
        if ($backupIndex >= count($backups)) {
            return ['success' => false, 'error' => 'Invalid backup index'];
        }
        
        $backup = $backups[$backupIndex];
        $this->log("Selected backup: " . $backup['file']);
        
        // 1. Data Restore (Optional)
        if ($restoreData) {
            $this->log("Mode: Full Restore (Data + App)");
            
            // Restore system from ZIP
            $backupPath = $this->backupDir . '/' . $backup['file'];
            $zip = new ZipArchive();
            if ($zip->open($backupPath) === TRUE) {
                $tempExtract = $this->backupDir . '/temp_restore_' . uniqid();
                mkdir($tempExtract, 0777, true);
                $zip->extractTo($tempExtract);
                $zip->close();

                // 1. Restore Database
                if (file_exists($tempExtract . '/finance.db')) {
                    copy($tempExtract . '/finance.db', $this->dbPath);
                    $this->log('Database restored successfully');
                }

                // 2. Restore Uploads
                if (is_dir($tempExtract . '/uploads')) {
                    $src = $tempExtract . '/uploads';
                    $dst = $this->appDir . '/uploads';
                    
                    // Use a helper method for recursive copy to ensure cross-platform compatibility
                    $this->recursiveCopy($src, $dst);
                    $this->log('Uploads restored successfully');
                }

                // Cleanup temp
                $this->recursiveRemove($tempExtract);
            } else {
                 return ['success' => false, 'error' => 'Failed to open backup ZIP'];
            }
        } else {
            $this->log("Mode: Application Downgrade Only (Data Preserved)");
        }

        // 2. Git Rollback (Common)
        // Rollback Git if commit is available
        if (!empty($backup['commit']) && $this->isGitRepo()) {
            $this->log("Rolling back Git to commit: " . $backup['commit']);
            $output = [];
            $returnVar = 0;
            
            $os = $this->getOS();
            if ($os === 'windows') {
                $resetCmd = 'cd /d "' . $this->appDir . '" && git reset --hard ' . $backup['commit'];
                exec($resetCmd . ' 2>&1', $output, $returnVar);
            } else {
                $this->execCommand('cd "' . $this->appDir . '" && git reset --hard ' . $backup['commit'], $output, $returnVar);
            }
            
            if ($returnVar === 0) {
                $this->log('Git rollback completed');
            } else {
                $this->log('Git rollback failed: ' . implode("\n", $output), 'WARNING');
            }
        }
        
        $this->log('========================================');
        $this->log('Rollback completed successfully!');
        $this->log('========================================');
        
        return [
            'success' => true,
            'backup' => $backup,
            'log' => file_get_contents($this->logFile)
        ];
    }
    
    /**
     * Get list of available backups
     */
    public function getBackupList() {
        if (!is_dir($this->backupDir)) {
            return [];
        }
        
        $files = glob($this->backupDir . '/finance_full_*.zip');
        // Fallback for old .db backups
        $oldFiles = glob($this->backupDir . '/finance_*.db');
        $files = array_merge($files, $oldFiles);
        rsort($files); // Most recent first
        
        $backups = [];
        foreach ($files as $file) {
            $filename = basename($file);
            if (preg_match('/finance_(?:full_)?(\d{8}_\d{6})_([a-f0-9]+)\.(db|zip)/', $filename, $matches)) {
                $backups[] = [
                    'file' => $filename,
                    'timestamp' => DateTime::createFromFormat('Ymd_His', $matches[1])->format('Y-m-d H:i:s'),
                    'commit' => $matches[2],
                    'size' => filesize($file),
                    'type' => $matches[3]
                ];
            }
        }
        
        return $backups;
    }
    
    /**
     * Clean up old backups (keep last 10)
     */
    private function cleanupOldBackups() {
        $backups = $this->getBackupList();
        
        if (count($backups) > 10) {
            $this->log('Cleaning up old backups...');
            $toDelete = array_slice($backups, 10);
            
            foreach ($toDelete as $backup) {
                $file = $this->backupDir . '/' . $backup['file'];
                if (unlink($file)) {
                    $this->log("Deleted old backup: " . $backup['file']);
                }
            }
        }
    }
    
    /**
     * Helper for recursive copy
     */
    private function recursiveCopy($src, $dst) {
        if (!is_dir($dst)) @mkdir($dst, 0777, true);
        $dir = opendir($src);
        if ($dir) {
            while(false !== ( $file = readdir($dir)) ) {
                if (( $file != '.' ) && ( $file != '..' )) {
                    if ( is_dir($src . '/' . $file) ) {
                        $this->recursiveCopy($src . '/' . $file,$dst . '/' . $file);
                    } else {
                        copy($src . '/' . $file,$dst . '/' . $file);
                    }
                }
            }
            closedir($dir);
        }
    }

    /**
     * Helper for recursive remove
     */
    private function recursiveRemove($dir) {
        if (!is_dir($dir)) return;
        $files = array_diff(scandir($dir), array('.','..'));
        foreach ($files as $file) {
            (is_dir("$dir/$file")) ? $this->recursiveRemove("$dir/$file") : unlink("$dir/$file");
        }
        return rmdir($dir);
    }

    /**
     * Get system information
     */
    public function getSystemInfo() {
        return [
            'os' => $this->getOS(),
            'php_version' => PHP_VERSION,
            'is_docker' => $this->isDocker(),
            'git_available' => $this->isGitAvailable(),
            'is_git_repo' => $this->isGitRepo(),
            'current_commit' => $this->getCurrentCommit(),
            'current_branch' => $this->getCurrentBranch(),
            'app_dir' => $this->appDir
        ];
    }
}
?>
