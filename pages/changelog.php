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
                <span class="px-3 py-1 bg-white/20 backdrop-blur-md rounded-full text-xs font-black uppercase tracking-widest">Version 2.4.1</span>
                <span class="text-white/60 text-xs font-medium">Released <?php echo date('M j, Y'); ?></span>
            </div>
            <h1 class="text-3xl md:text-5xl font-black tracking-tight mb-4">Product Evolution</h1>
            <p class="text-brand-100 text-lg max-w-xl leading-relaxed">
                Reimagined High Availability Sync, dedicated System Dashboard, and enhanced data security controls.
            </p>
        </div>
        <!-- Abstract Decoration -->
        <div class="absolute -right-20 -top-20 w-80 h-80 bg-white/10 rounded-full blur-3xl"></div>
        <div class="absolute -left-20 -bottom-20 w-60 h-60 bg-brand-400/20 rounded-full blur-2xl"></div>
    </div>

    <!-- Timeline -->
    <div class="space-y-12">
        <!-- V2.4.1 -->
        <div class="relative pl-8 md:pl-0">
            <!-- Connector Line -->
            <div class="hidden md:block absolute left-1/2 -ml-px h-full w-0.5 bg-gray-200 dark:bg-gray-800"></div>
            
            <div class="md:grid md:grid-cols-2 md:gap-16 items-start">
                <div class="md:text-right">
                    <div class="sticky top-24">
                        <span class="inline-block px-4 py-2 bg-brand-500 text-white text-sm font-black rounded-2xl mb-4 shadow-lg shadow-brand-500/20">v2.4.1</span>
                        <h2 class="text-2xl font-bold text-gray-900 dark:text-white mb-2">Live Sync & System Dashboard</h2>
                        <p class="text-sm text-gray-500 dark:text-gray-400 mb-6 md:mb-0">Complete overhaul of the Maintenance page with real-time sync visibility and server role indicators.</p>
                    </div>
                </div>
                
                <div class="space-y-6">
                    <div class="bg-white dark:bg-gray-800 p-6 rounded-3xl border border-gray-100 dark:border-gray-700 shadow-sm transition-all hover:shadow-md">
                        <div class="flex items-center gap-3 mb-4">
                            <div class="h-10 w-10 bg-emerald-100 dark:bg-emerald-900/50 text-emerald-600 dark:text-emerald-400 rounded-xl flex items-center justify-center text-xl">🔄</div>
                            <h3 class="font-bold text-gray-900 dark:text-white">Live Sync Status</h3>
                        </div>
                        <ul class="space-y-3 text-sm text-gray-600 dark:text-gray-400">
                            <li class="flex items-start gap-2">
                                <span class="text-emerald-500">✓</span>
                                <span><b>Real-time Feedback:</b> created a new visual console that shows step-by-step progress when backing up and syncing to nodes.</span>
                            </li>
                            <li class="flex items-start gap-2">
                                <span class="text-emerald-500">✓</span>
                                <span><b>Server Role Indicator:</b> New badge in Settings identifying if the server is Primary, Backup, or Standalone.</span>
                            </li>
                        </ul>
                    </div>

                    <div class="bg-white dark:bg-gray-800 p-6 rounded-3xl border border-gray-100 dark:border-gray-700 shadow-sm transition-all hover:shadow-md">
                        <div class="flex items-center gap-3 mb-4">
                            <div class="h-10 w-10 bg-blue-100 dark:bg-blue-900/50 text-blue-600 dark:text-blue-400 rounded-xl flex items-center justify-center text-xl">🛡️</div>
                            <h3 class="font-bold text-gray-900 dark:text-white">Security & UI Polish</h3>
                        </div>
                        <ul class="space-y-3 text-sm text-gray-600 dark:text-gray-400">
                            <li class="flex items-start gap-2">
                                <span class="text-blue-500">✓</span>
                                <span><b>Secure Configuration:</b> Receiver Node secret keys are now masked by default with a toggle to reveal.</span>
                            </li>
                            <li class="flex items-start gap-2">
                                <span class="text-blue-500">✓</span>
                                <span><b>Form Resubmission Fix:</b> Implemented PRG pattern to prevent "Confirm Form Resubmission" popups on refresh.</span>
                            </li>
                            <li class="flex items-start gap-2">
                                <span class="text-blue-500">✓</span>
                                <span><b>Dashboard UI:</b> Professional card-based layout for the Settings & Maintenance page.</span>
                            </li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>

        <!-- V2.4.0 -->
        <div class="relative pl-8 md:pl-0">
            <!-- Connector Line -->
            <div class="hidden md:block absolute left-1/2 -ml-px h-full w-0.5 bg-gray-200 dark:bg-gray-800"></div>
            
            <div class="md:grid md:grid-cols-2 md:gap-16 items-start">
                <div class="md:text-right">
                    <div class="sticky top-24">
                        <span class="inline-block px-4 py-2 bg-gray-100 dark:bg-gray-700 text-gray-500 dark:text-gray-400 text-sm font-black rounded-2xl mb-4">v2.4.0</span>
                        <h2 class="text-2xl font-bold text-gray-900 dark:text-white mb-2">Expense Management & UX Polish</h2>
                        <p class="text-sm text-gray-500 dark:text-gray-400 mb-6 md:mb-0">Introduced dedicated expense management page, enhanced navigation, and streamlined credit card editing.</p>
                    </div>
                </div>
                
                <div class="space-y-6">
                    <div class="bg-white dark:bg-gray-800 p-6 rounded-3xl border border-gray-100 dark:border-gray-700 shadow-sm transition-all hover:shadow-md">
                        <div class="flex items-center gap-3 mb-4">
                            <div class="h-10 w-10 bg-blue-100 dark:bg-blue-900/50 text-blue-600 dark:text-blue-400 rounded-xl flex items-center justify-center text-xl">📝</div>
                            <h3 class="font-bold text-gray-900 dark:text-white">New Expenses Page</h3>
                        </div>
                        <ul class="space-y-3 text-sm text-gray-600 dark:text-gray-400">
                            <li class="flex items-start gap-2">
                                <span class="text-blue-500">✓</span>
                                <span><b>Dedicated Management:</b> Full CRUD operations (Add, Edit, Delete, List) for expenses on a standalone page.</span>
                            </li>
                            <li class="flex items-start gap-2">
                                <span class="text-blue-500">✓</span>
                                <span><b>Advanced Filtering:</b> Filter expenses by category and search by description or method.</span>
                            </li>
                            <li class="flex items-start gap-2">
                                <span class="text-blue-500">✓</span>
                                <span><b>Dashboard Integration:</b> Restored direct access via "View All" link on the Dashboard.</span>
                            </li>
                        </ul>
                    </div>

                    <div class="bg-white dark:bg-gray-800 p-6 rounded-3xl border border-gray-100 dark:border-gray-700 shadow-sm transition-all hover:shadow-md">
                        <div class="flex items-center gap-3 mb-4">
                            <div class="h-10 w-10 bg-emerald-100 dark:bg-emerald-900/50 text-emerald-600 dark:text-emerald-400 rounded-xl flex items-center justify-center text-xl">✏️</div>
                            <h3 class="font-bold text-gray-900 dark:text-white">Credit Card Improvements</h3>
                        </div>
                        <ul class="space-y-3 text-sm text-gray-600 dark:text-gray-400">
                            <li class="flex items-start gap-2">
                                <span class="text-emerald-500">✓</span>
                                <span><b>Edit Modal:</b> Intuitive modal for updating credit limits, card names, and opening balances without page reloads.</span>
                            </li>
                            <li class="flex items-start gap-2">
                                <span class="text-emerald-500">✓</span>
                                <span><b>Seamless UX:</b> Removed confusing top-of-page forms in favor of contextual editing.</span>
                            </li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
        <!-- V2.3.0 -->
        <div class="relative pl-8 md:pl-0">
            <!-- Connector Line -->
            <div class="hidden md:block absolute left-1/2 -ml-px h-full w-0.5 bg-gray-200 dark:bg-gray-800"></div>
            
            <div class="md:grid md:grid-cols-2 md:gap-16 items-start">
                <div class="md:text-right">
                    <div class="sticky top-24">
                        <span class="inline-block px-4 py-2 bg-brand-500 text-white text-sm font-black rounded-2xl mb-4 shadow-lg shadow-brand-500/20">v2.3.0</span>
                        <h2 class="text-2xl font-bold text-gray-900 dark:text-white mb-2">Insights & Category Control</h2>
                        <p class="text-sm text-gray-500 dark:text-gray-400 mb-6 md:mb-0">Advanced category management, enhanced reporting, and refined credit monitoring.</p>
                    </div>
                </div>
                
                <div class="space-y-6">
                    <div class="bg-white dark:bg-gray-800 p-6 rounded-3xl border border-gray-100 dark:border-gray-700 shadow-sm transition-all hover:shadow-md">
                        <div class="flex items-center gap-3 mb-4">
                            <div class="h-10 w-10 bg-purple-100 dark:bg-purple-900/50 text-purple-600 dark:text-purple-400 rounded-xl flex items-center justify-center text-xl">🏷️</div>
                            <h3 class="font-bold text-gray-900 dark:text-white">Category Management System</h3>
                        </div>
                        <ul class="space-y-3 text-sm text-gray-600 dark:text-gray-400">
                            <li class="flex items-start gap-2">
                                <span class="text-purple-500">✓</span>
                                <span><b>Centralized Categories:</b> View all default and custom expense categories in one place with usage statistics.</span>
                            </li>
                            <li class="flex items-start gap-2">
                                <span class="text-purple-500">✓</span>
                                <span><b>Rename & Merge:</b> Consolidate similar categories or fix typos by renaming - automatically updates all expenses.</span>
                            </li>
                            <li class="flex items-start gap-2">
                                <span class="text-purple-500">✓</span>
                                <span><b>Usage Analytics:</b> See transaction counts and total spending per category at a glance.</span>
                            </li>
                        </ul>
                    </div>

                    <div class="bg-white dark:bg-gray-800 p-6 rounded-3xl border border-gray-100 dark:border-gray-700 shadow-sm transition-all hover:shadow-md">
                        <div class="flex items-center gap-3 mb-4">
                            <div class="h-10 w-10 bg-indigo-100 dark:bg-indigo-900/50 text-indigo-600 dark:text-indigo-400 rounded-xl flex items-center justify-center text-xl">📊</div>
                            <h3 class="font-bold text-gray-900 dark:text-white">Category-Wise Spending Reports</h3>
                        </div>
                        <ul class="space-y-3 text-sm text-gray-600 dark:text-gray-400">
                            <li class="flex items-start gap-2">
                                <span class="text-indigo-500">✓</span>
                                <span><b>Visual Breakdown:</b> Interactive donut chart showing spending distribution by category.</span>
                            </li>
                            <li class="flex items-start gap-2">
                                <span class="text-indigo-500">✓</span>
                                <span><b>Detailed Analytics:</b> Percentage breakdowns and amount details with color-coded progress bars.</span>
                            </li>
                            <li class="flex items-start gap-2">
                                <span class="text-indigo-500">✓</span>
                                <span><b>Month Selector:</b> Analyze category spending patterns across different months.</span>
                            </li>
                        </ul>
                    </div>

                    <div class="bg-white dark:bg-gray-800 p-6 rounded-3xl border border-gray-100 dark:border-gray-700 shadow-sm transition-all hover:shadow-md">
                        <div class="flex items-center gap-3 mb-4">
                            <div class="h-10 w-10 bg-emerald-100 dark:bg-emerald-900/50 text-emerald-600 dark:text-emerald-400 rounded-xl flex items-center justify-center text-xl">💳</div>
                            <h3 class="font-bold text-gray-900 dark:text-white">Credit Card UI Enhancements</h3>
                        </div>
                        <ul class="space-y-3 text-sm text-gray-600 dark:text-gray-400">
                            <li class="flex items-start gap-2">
                                <span class="text-emerald-500">✓</span>
                                <span><b>Smart Color Coding:</b> Utilization now shows green (<30%), orange (30-60%), and red (≥60%) for better credit health tracking.</span>
                            </li>
                            <li class="flex items-start gap-2">
                                <span class="text-emerald-500">✓</span>
                                <span><b>Enhanced Balance Display:</b> Card balances highlighted in green for quick visual identification.</span>
                            </li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>

        <!-- V2.2.0 -->
        <div class="relative pl-8 md:pl-0">
            <!-- Connector Line -->
            <div class="hidden md:block absolute left-1/2 -ml-px h-full w-0.5 bg-gray-200 dark:bg-gray-800"></div>
            
            <div class="md:grid md:grid-cols-2 md:gap-16 items-start">
                <div class="md:text-right">
                    <div class="sticky top-24">
                        <span class="inline-block px-4 py-2 bg-brand-500 text-white text-sm font-black rounded-2xl mb-4 shadow-lg shadow-brand-500/20">v2.2.0</span>
                        <h2 class="text-2xl font-bold text-gray-900 dark:text-white mb-2">Offline-First & Smart Sync</h2>
                        <p class="text-sm text-gray-500 dark:text-gray-400 mb-6 md:mb-0">Introducing "Quick Update" to log spends on the go and sync them intelligently.</p>
                    </div>
                </div>
                
                <div class="space-y-6">
                    <div class="bg-white dark:bg-gray-800 p-6 rounded-3xl border border-gray-100 dark:border-gray-700 shadow-sm transition-all hover:shadow-md">
                        <div class="flex items-center gap-3 mb-4">
                            <div class="h-10 w-10 bg-amber-100 dark:bg-amber-900/50 text-amber-600 dark:text-amber-400 rounded-xl flex items-center justify-center text-xl">⚡</div>
                            <h3 class="font-bold text-gray-900 dark:text-white">Quick Update Feature</h3>
                        </div>
                        <ul class="space-y-3 text-sm text-gray-600 dark:text-gray-400">
                            <li class="flex items-start gap-2">
                                <span class="text-amber-500">✓</span>
                                <span><b>Offline-ready Drafts:</b> Log transactions instantly in a "Pending" list without affecting balances immediately.</span>
                            </li>
                            <li class="flex items-start gap-2">
                                <span class="text-amber-500">✓</span>
                                <span><b>Smart Sync:</b> "Sync All" moves drafts to the main ledger and auto-updates Bank Balance (for UPI) or Credit Utilization (for Cards).</span>
                            </li>
                            <li class="flex items-start gap-2">
                                <span class="text-amber-500">✓</span>
                                <span><b>Streamlined UI:</b> Minimalist form accessible via navbar for rapid entry.</span>
                            </li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>

        <!-- V2.1.2 -->
        <div class="relative pl-8 md:pl-0">
            <!-- Connector Line -->
            <div class="hidden md:block absolute left-1/2 -ml-px h-full w-0.5 bg-gray-200 dark:bg-gray-800"></div>
            
            <div class="md:grid md:grid-cols-2 md:gap-16 items-start">
                <div class="md:text-right">
                    <div class="sticky top-24">
                        <span class="inline-block px-4 py-2 bg-brand-500 text-white text-sm font-black rounded-2xl mb-4 shadow-lg shadow-brand-500/20">v2.1.2</span>
                        <h2 class="text-2xl font-bold text-gray-900 dark:text-white mb-2">Data Portability & UI Polish</h2>
                        <p class="text-sm text-gray-500 dark:text-gray-400 mb-6 md:mb-0">Empowering users with more control over their data and a cleaner update history.</p>
                    </div>
                </div>
                
                <div class="space-y-6">
                    <div class="bg-white dark:bg-gray-800 p-6 rounded-3xl border border-gray-100 dark:border-gray-700 shadow-sm transition-all hover:shadow-md">
                        <div class="flex items-center gap-3 mb-4">
                            <div class="h-10 w-10 bg-emerald-100 dark:bg-emerald-900/50 text-emerald-600 dark:text-emerald-400 rounded-xl flex items-center justify-center text-xl">📥</div>
                            <h3 class="font-bold text-gray-900 dark:text-white">Expense Export Tool</h3>
                        </div>
                        <ul class="space-y-3 text-sm text-gray-600 dark:text-gray-400">
                            <li class="flex items-start gap-2">
                                <span class="text-emerald-500">✓</span>
                                <span>One-click CSV export for expenses directly from the history table.</span>
                            </li>
                            <li class="flex items-start gap-2">
                                <span class="text-emerald-500">✓</span>
                                <span>Context-aware export: applies your active filters (month, category, method) to the CSV.</span>
                            </li>
                            <li class="flex items-start gap-2">
                                <span class="text-emerald-500">✓</span>
                                <span>Exported data includes all critical fields for manual tallying and offline reconciliation.</span>
                            </li>
                        </ul>
                    </div>

                    <div class="bg-white dark:bg-gray-800 p-6 rounded-3xl border border-gray-100 dark:border-gray-700 shadow-sm transition-all hover:shadow-md">
                        <div class="flex items-center gap-3 mb-4">
                            <div class="h-10 w-10 bg-blue-100 dark:bg-blue-900/50 text-blue-600 dark:text-blue-400 rounded-xl flex items-center justify-center text-xl">🏠</div>
                            <h3 class="font-bold text-gray-900 dark:text-white">UX Improvements</h3>
                        </div>
                        <ul class="space-y-3 text-sm text-gray-600 dark:text-gray-400">
                            <li class="flex items-start gap-2">
                                <span class="text-blue-500">✓</span>
                                <span>Streamlined Change Log UI with collapsible history for better readability.</span>
                            </li>
                            <li class="flex items-start gap-2">
                                <span class="text-blue-500">✓</span>
                                <span>Project version bumped to 2.1.2 across all system configurations.</span>
                            </li>
                            <li class="flex items-start gap-2">
                                <span class="text-blue-500">✓</span>
                                <span>High-precision interest rates: input fields now supports up to 3 decimal points.</span>
                            </li>
                            <li class="flex items-start gap-2">
                                <span class="text-blue-500">✓</span>
                                <span>Manual EMI Adjustment: You can now override calculated EMI amounts to match bank statements exactly.</span>
                            </li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>

        <!-- Collapsible Older Versions -->
        <div class="pt-8">
            <details class="group bg-white dark:bg-gray-800/50 rounded-3xl border border-gray-100 dark:border-gray-800 overflow-hidden shadow-sm transition-all">
                <summary class="flex items-center justify-between p-6 cursor-pointer list-none">
                    <span class="text-lg font-bold text-gray-900 dark:text-white">View Legacy Change Logs</span>
                    <span class="text-brand-500 transition-transform group-open:rotate-180">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path></svg>
                    </span>
                </summary>
                
                <div class="p-6 pt-0 space-y-12">
                    <!-- V2.1.1 -->
                    <div class="border-t border-gray-100 dark:border-gray-700 pt-8">
                        <div class="md:grid md:grid-cols-2 md:gap-16 items-start">
                            <div class="md:text-right">
                                <span class="inline-block px-4 py-2 bg-gray-100 dark:bg-gray-700 text-gray-600 dark:text-gray-300 text-sm font-black rounded-2xl mb-4">v2.1.1</span>
                                <h2 class="text-2xl font-bold text-gray-900 dark:text-white mb-2">Bulk & Stability Update</h2>
                            </div>
                            <div class="space-y-4 text-sm text-gray-600 dark:text-gray-400">
                                <p>• Launched **Bulk Data Importer** for large historical CSV datasets.</p>
                                <p>• Fixed "Headers already sent" redirect error on Credit Card page.</p>
                                <p>• Optimized financial cycle logic for balance carry-forwards.</p>
                            </div>
                        </div>
                    </div>

                    <!-- V2.1.0 -->
                    <div class="border-t border-gray-100 dark:border-gray-700 pt-8 opacity-60">
                        <div class="md:grid md:grid-cols-2 md:gap-16 items-start">
                            <div class="md:text-right">
                                <span class="inline-block px-4 py-2 bg-gray-100 dark:bg-gray-700 text-gray-600 dark:text-gray-300 text-sm font-black rounded-2xl mb-4">v2.1.0</span>
                                <h2 class="text-2xl font-bold text-gray-900 dark:text-white mb-2">The Hybrid Release</h2>
                            </div>
                            <div class="space-y-4 text-sm text-gray-600 dark:text-gray-400">
                                <p>• Launched **High Availability Sync** for multi-node server replication.</p>
                                <p>• Implemented **Mobile Bottom-Sheet** for native-app feel on smartphones.</p>
                                <p>• Added **Document Vault** for payslips and records.</p>
                            </div>
                        </div>
                    </div>
                </div>
            </details>
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
