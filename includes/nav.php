<?php
$curPageName = basename($_SERVER['PHP_SELF']);
$prefix = (basename(dirname($_SERVER['PHP_SELF'])) == 'expense-tracker') ? './' : '../';

$navItems = [
    ['name' => 'Dashboard', 'url' => $prefix . 'index.php', 'icon' => 'home'],
    ['name' => 'Income', 'url' => $prefix . 'pages/income.php', 'icon' => 'banknotes'],
    ['name' => 'Document Vault', 'url' => $prefix . 'pages/vault.php', 'icon' => 'folder'],
    ['name' => 'Expenses', 'url' => $prefix . 'pages/expenses.php', 'icon' => 'credit-card'],
    ['name' => 'Investments', 'url' => $prefix . 'pages/investments.php', 'icon' => 'trending-up'],
    ['name' => 'Loans', 'url' => $prefix . 'pages/loans.php', 'icon' => 'users'],
    ['name' => 'EMI Tracker', 'url' => $prefix . 'pages/emis.php', 'icon' => 'calendar'],
    ['name' => 'Reports', 'url' => $prefix . 'pages/reports.php', 'icon' => 'chart-pie'],
    ['name' => 'Credit', 'url' => $prefix . 'pages/credit.php', 'icon' => 'scale'],
    ['name' => 'Import', 'url' => $prefix . 'pages/upload.php', 'icon' => 'document-arrow-up'],
    ['name' => 'Maintenance', 'url' => $prefix . 'pages/settings.php', 'icon' => 'cog'],
];

function isDataActive($url, $curPageName) {
    if (basename($url) === $curPageName) return 'bg-gray-800 text-white';
    return 'text-gray-400 hover:bg-gray-800 hover:text-white';
}
?>

<!-- Desktop Sidebar (Hidden on Mobile) -->
<aside class="hidden md:flex flex-col w-64 bg-gray-900 border-r border-gray-800">
    <div class="flex items-center justify-center h-16 border-b border-gray-800 bg-gray-900">
        <span class="text-white text-xl font-bold">Finance<span class="text-brand-500">Board</span></span>
    </div>

    <!-- User Profile Snippet -->
    <div class="px-4 py-4 border-b border-gray-800 bg-gray-800">
        <div class="flex items-center">
            <div class="h-8 w-8 rounded-full bg-brand-500 flex items-center justify-center text-white font-bold">
                <?php echo strtoupper(substr($_SESSION['user_name'] ?? 'U', 0, 1)); ?>
            </div>
            <div class="ml-3">
                <p class="text-sm font-medium text-white"><?php echo htmlspecialchars($_SESSION['user_name'] ?? 'User'); ?></p>
                <a href="<?php echo $prefix; ?>logout.php" class="text-xs text-gray-400 hover:text-white">Sign out</a>
            </div>
        </div>
    </div>

    <div class="flex flex-col flex-1 overflow-y-auto">
        <nav class="flex-1 px-2 py-4 space-y-2">
            <?php foreach ($navItems as $item): ?>
                <a href="<?php echo $item['url']; ?>" 
                   class="<?php echo isDataActive($item['url'], $curPageName); ?> group flex items-center px-4 py-3 text-sm font-medium rounded-md transition-colors">
                    <span class="mr-3 h-6 w-6 text-gray-400 text-lg">
                        <?php 
                        switch($item['icon']) {
                            case 'home': echo '🏠'; break;
                            case 'banknotes': echo '💰'; break;
                            case 'credit-card': echo '💸'; break;
                            case 'trending-up': echo '📈'; break;
                            case 'calendar': echo '📅'; break;
                            case 'chart-pie': echo '📊'; break;
                            case 'users': echo '🤝'; break;
                            case 'scale': echo '⚖️'; break;
                            case 'document-arrow-up': echo '📂'; break;
                            case 'folder': echo '📁'; break;
                            case 'cog': echo '⚙️'; break;
                            default: echo '•';
                        }
                        ?>
                    </span>
                    <?php echo $item['name']; ?>
                </a>
            <?php endforeach; ?>
        </nav>
    </div>
</aside>

<!-- Mobile Bottom Navigation -->
<div class="md:hidden fixed bottom-0 left-0 right-0 bg-white dark:bg-gray-800 border-t border-gray-100 dark:border-gray-700 z-50 pb-safe shadow-lg rounded-t-2xl transition-colors">
    <div class="flex justify-around items-center h-16 px-2">
        <a href="<?php echo $prefix; ?>index.php" class="flex flex-col items-center justify-center w-full h-full <?php echo basename('index.php')===$curPageName?'text-brand-600 font-bold':'text-gray-400'; ?>">
            <span class="text-xl <?php echo basename('index.php')===$curPageName?'scale-110 transition-transform':''; ?>">🏠</span>
            <span class="text-[10px] mt-0.5">Home</span>
        </a>
        <a href="<?php echo $prefix; ?>pages/expenses.php" class="flex flex-col items-center justify-center w-full h-full <?php echo basename('expenses.php')===$curPageName?'text-brand-600 font-bold':'text-gray-400'; ?>">
            <span class="text-xl <?php echo basename('expenses.php')===$curPageName?'scale-110 transition-transform':''; ?>">💸</span>
            <span class="text-[10px] mt-0.5">Exp</span>
        </a>
        <a href="<?php echo $prefix; ?>pages/income.php" class="flex flex-col items-center justify-center w-full h-full <?php echo basename('income.php')===$curPageName?'text-brand-600 font-bold':'text-gray-400'; ?>">
            <span class="text-xl <?php echo basename('income.php')===$curPageName?'scale-110 transition-transform':''; ?>">💰</span>
            <span class="text-[10px] mt-0.5">Inc</span>
        </a>
        <a href="<?php echo $prefix; ?>pages/loans.php" class="flex flex-col items-center justify-center w-full h-full <?php echo basename('loans.php')===$curPageName?'text-brand-600 font-bold':'text-gray-400'; ?>">
            <span class="text-xl <?php echo basename('loans.php')===$curPageName?'scale-110 transition-transform':''; ?>">🤝</span>
            <span class="text-[10px] mt-0.5">Loans</span>
        </a>
        <a href="<?php echo $prefix; ?>pages/emis.php" class="flex flex-col items-center justify-center w-full h-full <?php echo basename('emis.php')===$curPageName?'text-brand-600 font-bold':'text-gray-400'; ?>">
            <span class="text-xl <?php echo basename('emis.php')===$curPageName?'scale-110 transition-transform':''; ?>">📅</span>
            <span class="text-[10px] mt-0.5">EMI</span>
        </a>
    </div>
</div>
<div class="md:hidden h-20 w-full"></div> 
