<?php
require_once '../includes/auth.php';
requireLogin();
$pageTitle = 'Change Log';
require_once '../includes/header.php';
?>

<div class="max-w-4xl mx-auto space-y-8 pb-12">
    <!-- Hero Section -->
    <div class="relative overflow-hidden bg-brand-600 rounded-[2.5rem] p-8 md:p-12 text-white shadow-2xl shadow-brand-500/20">
        <div class="relative z-10">
            <div class="flex items-center gap-3 mb-4">
                <span class="px-3 py-1 bg-white/20 backdrop-blur-md rounded-full text-xs font-black uppercase tracking-widest">Version 2.1.1</span>
                <span class="text-white/60 text-xs font-medium">Released Feb 5, 2026</span>
            </div>
            <h1 class="text-3xl md:text-5xl font-black tracking-tight mb-4">Product Evolution</h1>
            <p class="text-brand-100 text-lg max-w-xl leading-relaxed">
                Experience a smarter way to manage your finances with our latest updates focused on performance and bulk management.
            </p>
        </div>
        <!-- Abstract Decoration -->
        <div class="absolute -right-20 -top-20 w-80 h-80 bg-white/10 rounded-full blur-3xl"></div>
        <div class="absolute -left-20 -bottom-20 w-60 h-60 bg-brand-400/20 rounded-full blur-2xl"></div>
    </div>

    <!-- Timeline -->
    <div class="space-y-12">
        <!-- V2.1.1 -->
        <div class="relative pl-8 md:pl-0">
            <!-- Connector Line -->
            <div class="hidden md:block absolute left-1/2 -ml-px h-full w-0.5 bg-gray-200 dark:bg-gray-800"></div>
            
            <div class="md:grid md:grid-cols-2 md:gap-16 items-start">
                <div class="md:text-right">
                    <div class="sticky top-24">
                        <span class="inline-block px-4 py-2 bg-brand-500 text-white text-sm font-black rounded-2xl mb-4 shadow-lg shadow-brand-500/20">v2.1.1</span>
                        <h2 class="text-2xl font-bold text-gray-900 dark:text-white mb-2">Bulk & Stability Update</h2>
                        <p class="text-sm text-gray-500 dark:text-gray-400 mb-6 md:mb-0">Major focus on data ingestion and financial logic precision.</p>
                    </div>
                </div>
                
                <div class="space-y-6">
                    <div class="bg-white dark:bg-gray-800 p-6 rounded-3xl border border-gray-100 dark:border-gray-700 shadow-sm transition-all hover:shadow-md">
                        <div class="flex items-center gap-3 mb-4">
                            <div class="h-10 w-10 bg-emerald-100 dark:bg-emerald-900/50 text-emerald-600 dark:text-emerald-400 rounded-xl flex items-center justify-center text-xl">📊</div>
                            <h3 class="font-bold text-gray-900 dark:text-white">Bulk Data Importer</h3>
                        </div>
                        <ul class="space-y-3 text-sm text-gray-600 dark:text-gray-400">
                            <li class="flex items-start gap-2">
                                <span class="text-emerald-500">✓</span>
                                <span>CSV-based bulk import for expenses, supporting large historical datasets.</span>
                            </li>
                            <li class="flex items-start gap-2">
                                <span class="text-emerald-500">✓</span>
                                <span>Real-time data preview with validation before committing to the database.</span>
                            </li>
                            <li class="flex items-start gap-2">
                                <span class="text-emerald-500">✓</span>
                                <span>Downloadable standard CSV templates for error-free data mapping.</span>
                            </li>
                        </ul>
                    </div>

                    <div class="bg-white dark:bg-gray-800 p-6 rounded-3xl border border-gray-100 dark:border-gray-700 shadow-sm transition-all hover:shadow-md">
                        <div class="flex items-center gap-3 mb-4">
                            <div class="h-10 w-10 bg-amber-100 dark:bg-amber-900/50 text-amber-600 dark:text-amber-400 rounded-xl flex items-center justify-center text-xl">🎯</div>
                            <h3 class="font-bold text-gray-900 dark:text-white">Precision & Core Fixes</h3>
                        </div>
                        <ul class="space-y-3 text-sm text-gray-600 dark:text-gray-400">
                            <li class="flex items-start gap-2">
                                <span class="text-amber-500">✓</span>
                                <span>Fixed "Headers already sent" redirect error on Credit Card page.</span>
                            </li>
                            <li class="flex items-start gap-2">
                                <span class="text-amber-500">✓</span>
                                <span>Optimized financial cycle logic for more accurate balance carry-forwards.</span>
                            </li>
                            <li class="flex items-start gap-2">
                                <span class="text-amber-500">✓</span>
                                <span>Cleaned up legacy database references in maintenance scripts.</span>
                            </li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>

        <!-- V2.1.0 -->
        <div class="relative pl-8 md:pl-0 opacity-60 grayscale hover:grayscale-0 hover:opacity-100 transition-all duration-500">
            <div class="md:grid md:grid-cols-2 md:gap-16 items-start">
                <div class="md:text-right">
                    <span class="inline-block px-4 py-2 bg-gray-200 dark:bg-gray-700 text-gray-600 dark:text-gray-300 text-sm font-black rounded-2xl mb-4">v2.1.0</span>
                    <h2 class="text-2xl font-bold text-gray-900 dark:text-white mb-2">The Hybrid Release</h2>
                    <p class="text-sm text-gray-500 dark:text-gray-400">Introduction of HA Sync and Mobile Navigation.</p>
                </div>
                
                <div class="space-y-4 text-sm text-gray-600 dark:text-gray-400">
                    <p>• Launched **High Availability Sync** for multi-node server replication.</p>
                    <p>• Implemented **Mobile Bottom-Sheet** for native-app feel on smartphones.</p>
                    <p>• Added **Document Vault** for payslips and financial record keeping.</p>
                </div>
            </div>
        </div>
    </div>

    <!-- CTA -->
    <div class="bg-gray-50 dark:bg-gray-900/50 rounded-[2.5rem] p-8 border border-gray-100 dark:border-gray-800 text-center">
        <h3 class="text-lg font-bold text-gray-900 dark:text-white mb-2">Want to see more?</h3>
        <p class="text-sm text-gray-500 dark:text-gray-400 mb-6">Check the System Update page for real-time status and OTA updates.</p>
        <a href="update.php" class="inline-flex items-center gap-2 px-6 py-3 bg-brand-500 text-white font-bold rounded-2xl hover:bg-brand-600 transition shadow-lg shadow-brand-500/20">
            <span>🔄</span> Go to Update Center
        </a>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>
