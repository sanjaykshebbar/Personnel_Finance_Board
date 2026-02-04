<?php
require_once '../includes/auth.php';
requireLogin();
$curPageName = 'settings.php'; // Keep active tab
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sync Cluster Setup | Finance Board</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;900&display=swap" rel="stylesheet">
    <style>body { font-family: 'Inter', sans-serif; }</style>
</head>
<body class="bg-gray-50 dark:bg-gray-900 text-gray-900 dark:text-gray-100 pb-24">

    <!-- Header -->
    <div class="bg-white dark:bg-gray-800 border-b border-gray-200 dark:border-gray-700 sticky top-0 z-30">
        <div class="max-w-4xl mx-auto px-4 h-16 flex items-center justify-between">
            <div class="flex items-center gap-3">
                <a href="settings.php" class="p-2 -ml-2 text-gray-400 hover:text-gray-600 dark:hover:text-gray-300">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
                </a>
                <h1 class="text-lg font-bold">High Availability Sync Guide</h1>
            </div>
        </div>
    </div>

    <!-- Content -->
    <div class="max-w-3xl mx-auto px-4 py-8 space-y-12">

        <!-- Intro -->
        <section>
            <div class="bg-blue-50 dark:bg-blue-900/20 border border-blue-100 dark:border-blue-900/50 rounded-2xl p-6">
                <h2 class="text-xl font-bold text-blue-900 dark:text-blue-100 mb-2">Cluster Architecture</h2>
                <p class="text-sm text-blue-700 dark:text-blue-300 leading-relaxed">
                    The High Availability (HA) Sync system allows you to replicate your entire financial database and document vault from this <strong>Primary Server</strong> to multiple <strong>Backup Nodes</strong> (e.g., Raspberry Pi, Secondary PC).
                </p>
                <div class="mt-4 flex flex-col md:flex-row items-center gap-4 text-xs font-mono text-blue-600 dark:text-blue-400 bg-white dark:bg-gray-900 p-4 rounded-xl border border-blue-100 dark:border-blue-900/30">
                    <div class="flex-1 text-center font-bold">[ Primary Server ] <br> (Source of Truth)</div>
                    <div class="text-lg">➔ PUSH ➔</div>
                    <div class="flex-1 text-center font-bold text-gray-400">[ Backup Node 1 ] <br> (Passive Replica)</div>
                </div>
            </div>
        </section>

        <!-- Prerequisites -->
        <section>
            <h3 class="text-lg font-bold mb-4 flex items-center gap-2">
                <span class="w-6 h-6 rounded-full bg-gray-200 dark:bg-gray-700 flex items-center justify-center text-xs">1</span>
                Prerequisites
            </h3>
            <ul class="space-y-3 text-sm text-gray-600 dark:text-gray-400">
                <li class="flex items-start gap-3">
                    <span class="text-green-500 font-bold">✓</span>
                    <div>
                        <strong>Identical Application Code:</strong> <br>
                        The Backup Node must have the same version of Finance Board installed.
                    </div>
                </li>
                <li class="flex items-start gap-3">
                    <span class="text-green-500 font-bold">✓</span>
                    <div>
                        <strong>Network Access:</strong> <br>
                        The Primary Server must be able to reach the Backup Node's IP address (e.g., <code class="bg-gray-100 dark:bg-gray-800 px-1 rounded">192.168.1.x</code>).
                    </div>
                </li>
                <li class="flex items-start gap-3">
                    <span class="text-green-500 font-bold">✓</span>
                    <div>
                        <strong>Write Permissions:</strong> <br>
                        The web server (www-data) on the Backup Node must have write access to <code class="bg-gray-100 dark:bg-gray-800 px-1 rounded">db/</code> and <code class="bg-gray-100 dark:bg-gray-800 px-1 rounded">uploads/</code>.
                    </div>
                </li>
            </ul>
        </section>

        <!-- Step 1: Backup Node Setup -->
        <section>
            <h3 class="text-lg font-bold mb-4 flex items-center gap-2">
                <span class="w-6 h-6 rounded-full bg-indigo-100 text-indigo-600 flex items-center justify-center text-xs">2</span>
                Setup Buffer Node (Receiver)
            </h3>
            <div class="pl-8 border-l-2 border-gray-100 dark:border-gray-700 space-y-6">
                <div>
                    <h4 class="font-bold text-sm mb-2">A. Create the Secret Key</h4>
                    <p class="text-sm text-gray-600 dark:text-gray-400 mb-3">
                        On your <strong>Backup Server</strong>, go to <strong class="text-purple-600">Settings &amp; Maintenance</strong>. <br>
                        Scroll down to the <strong>Receiver Node Configuration</strong> section and enter a strong Secret Key (e.g., "MySecurePassword123").
                    </p>
                    <div class="bg-purple-50 dark:bg-purple-900/10 border border-purple-100 dark:border-purple-900/30 p-4 rounded-xl text-xs text-purple-700 dark:text-purple-300">
                        <strong>Why?</strong> This key acts as the password. The Primary Server will use this key to authenticate itself before pushing data.
                    </div>
                </div>

                <div>
                    <h4 class="font-bold text-sm mb-2">B. Verify Receiver API</h4>
                    <p class="text-sm text-gray-600 dark:text-gray-400">
                        Ensure that you can access the receiver endpoint from your browser. Go to: <br>
                        <code class="text-indigo-600">http://[BACKUP_IP]/api/sync_receive.php</code>
                        <br><br>
                        <span class="text-xs bg-amber-50 text-amber-600 px-2 py-1 rounded">Expected Response:</span> 
                        <span class="text-xs font-mono">Method Not Allowed</span> (This confirms the file exists and PHP is running).
                    </p>
                </div>
            </div>
        </section>

        <!-- Step 2: Primary Configuration -->
        <section>
            <h3 class="text-lg font-bold mb-4 flex items-center gap-2">
                <span class="w-6 h-6 rounded-full bg-emerald-100 text-emerald-600 flex items-center justify-center text-xs">3</span>
                Configure Primary Server
            </h3>
            <div class="pl-8 border-l-2 border-gray-100 dark:border-gray-700 space-y-6">
                <p class="text-sm text-gray-600 dark:text-gray-400 mb-3">
                    Return to this <strong>Settings Page</strong> and locate "High Availability Sync Cluster".
                    You will use the <strong>Add Node</strong> form to register the Backup Node you just configured.
                </p>
                
                <div class="bg-white dark:bg-gray-800 p-4 rounded-xl border border-gray-200 dark:border-gray-700">
                    <h5 class="text-xs font-black uppercase tracking-widest text-gray-400 mb-4">Add Node Form Fields</h5>
                    <div class="space-y-4 text-sm">
                        <div>
                            <span class="font-bold block text-gray-700 dark:text-gray-300">1. Name</span>
                            <p class="text-xs text-gray-500">A label for your own identification (e.g., "Living Room Pi" or "Backup PC").</p>
                        </div>
                        <div>
                            <span class="font-bold block text-gray-700 dark:text-gray-300">2. URL</span>
                            <p class="text-xs text-gray-500 mb-1">
                                The base web address of the backup app. The system will auto-append <code>api/sync_receive.php</code> for you.
                            </p>
                            <div class="space-y-2 text-xs">
                                <div>
                                    <span class="text-[10px] uppercase tracking-widest text-gray-400">Examples</span>
                                </div>
                                <div class="space-y-1">
                                    <code class="bg-gray-100 dark:bg-gray-900 px-2 py-1 rounded">http://[IP_ADDRESS]:8000</code>
                                    <code class="bg-gray-100 dark:bg-gray-900 px-2 py-1 rounded">http://[IP_ADDRESS]/expense-tracker</code>
                                </div>
                                <div class="text-[10px] text-gray-500">
                                    Auto-generated endpoint: <code class="font-mono">/api/sync_receive.php</code>
                                </div>
                            </div>
                        </div>
                        <div>
                            <span class="font-bold block text-gray-700 dark:text-gray-300">3. Secret Key</span>
                            <p class="text-xs text-gray-500">The <strong>exact same password</strong> you entered in the "Receiver Node Configuration" on the backup device.</p>
                        </div>
                        <div>
                            <span class="font-bold block text-gray-700 dark:text-gray-300">4. Node Status</span>
                            <p class="text-xs text-gray-500">
                                After adding a node, click <strong>🔄 Refresh Status</strong> to send a heartbeat ping. You’ll see
                                <span class="text-emerald-600">Online</span> or <span class="text-red-500">Offline</span> badges.
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- Step 3: Trigger -->
        <section>
            <h3 class="text-lg font-bold mb-4 flex items-center gap-2">
                <span class="w-6 h-6 rounded-full bg-pink-100 text-pink-600 flex items-center justify-center text-xs">4</span>
                Trigger Sync
            </h3>
            <div class="pl-8 border-l-2 border-gray-100 dark:border-gray-700">
                <p class="text-sm text-gray-600 dark:text-gray-400">
                    Click the <strong class="text-blue-600">🚀 Trigger Sync Now</strong> button. <br>
                    The system will:
                    <ul class="list-disc ml-4 mt-2 space-y-1">
                        <li>Create a ZIP archive of your database and uploads.</li>
                        <li>Push the archive to all configured nodes in parallel.</li>
                        <li>The Backup Nodes will extract and overwrite their local data.</li>
                    </ul>
                </p>
            </div>
        </section>

        <!-- Step 4: Restore (Failover) -->
        <section>
            <h3 class="text-lg font-bold mb-4 flex items-center gap-2">
                <span class="w-6 h-6 rounded-full bg-amber-100 text-amber-600 flex items-center justify-center text-xs">5</span>
                Restore from a Backup Node (Failover)
            </h3>
            <div class="pl-8 border-l-2 border-gray-100 dark:border-gray-700">
                <p class="text-sm text-gray-600 dark:text-gray-400">
                    If the Primary server goes down, you can work directly on the Backup Node. When the Primary is back,
                    click <strong class="text-amber-600">↩️ RESTORE</strong> next to the node to pull the latest data back.
                    <br><br>
                    <span class="text-xs bg-amber-50 text-amber-600 px-2 py-1 rounded">Warning:</span>
                    This action overwrites the Primary server’s local database and uploads with the Backup’s data.
                </p>
            </div>
        </section>

    </div>

</body>
</html>
