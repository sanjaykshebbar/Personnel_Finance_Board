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
    ['name' => 'System Update', 'url' => $prefix . 'pages/update.php', 'icon' => 'arrow-path'],
    ['name' => 'Maintenance', 'url' => $prefix . 'pages/settings.php', 'icon' => 'cog'],
    ['name' => 'Change Log', 'url' => $prefix . 'pages/changelog.php', 'icon' => 'list-bullet'],
];

if (!function_exists('isDataActive')) {
    function isDataActive($url, $curPageName) {
        if (basename($url) === $curPageName) return 'bg-gray-800 text-white';
        return 'text-gray-400 hover:bg-gray-800 hover:text-white';
    }
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
                            case 'arrow-path': echo '🔄'; break;
                            case 'cog': echo '⚙️'; break;
                            case 'list-bullet': echo '📜'; break;
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

<!-- Mobile Bottom Sheet (Replaces Sidebar) -->
<div id="bottom-sheet-overlay" class="fixed inset-0 bg-black/40 z-50 hidden md:hidden transition-opacity duration-300 backdrop-blur-sm" onclick="toggleBottomSheet()"></div>

<div id="bottom-sheet" 
     class="fixed bottom-0 left-0 right-0 bg-white dark:bg-gray-900 z-[60] transform translate-y-full transition-transform duration-300 ease-out md:hidden flex flex-col rounded-t-[2.5rem] shadow-2xl safe-bottom overflow-hidden border-t border-gray-100 dark:border-gray-800"
     style="height: 80vh; max-height: 80vh;">
    
    <!-- Drag Handle Area -->
    <div class="flex flex-col items-center py-4 cursor-grab active:cursor-grabbing shrink-0" id="sheet-handle">
        <div class="w-12 h-1.5 bg-gray-300 dark:bg-gray-700 rounded-full mb-2"></div>
        <p class="text-[10px] font-black text-gray-400 uppercase tracking-[0.2em]">Navigation</p>
    </div>

    <!-- User Header in Sheet -->
    <div class="px-8 py-2 mb-4 flex items-center justify-between shrink-0">
        <div class="flex items-center">
            <div class="h-12 w-12 rounded-2xl bg-gradient-to-br from-brand-500 to-brand-600 flex items-center justify-center text-white text-xl font-bold shadow-lg shadow-brand-500/20">
                <?php echo strtoupper(substr($_SESSION['user_name'] ?? 'U', 0, 1)); ?>
            </div>
            <div class="ml-4">
                <p class="text-base font-bold text-gray-900 dark:text-white"><?php echo htmlspecialchars($_SESSION['user_name'] ?? 'User'); ?></p>
                <div class="flex items-center space-x-2">
                    <span class="w-2 h-2 rounded-full bg-green-500 animate-pulse"></span>
                    <p class="text-xs text-gray-500 font-medium">Personal Finance Board</p>
                </div>
            </div>
        </div>
        <a href="<?php echo $prefix; ?>logout.php" class="p-3 bg-gray-100 dark:bg-gray-800 rounded-2xl text-gray-500 hover:text-red-500 transition-all active:scale-90">
            <span class="text-xl">🚪</span>
        </a>
    </div>

    <!-- Navigation Scrollable Area -->
    <div class="flex-1 overflow-y-auto px-6 pb-24">
        <div class="grid grid-cols-2 gap-4">
            <?php foreach ($navItems as $item): ?>
                <?php 
                $isActive = (basename($item['url']) === $curPageName);
                ?>
                <a href="<?php echo $item['url']; ?>" 
                   onclick="toggleBottomSheet()"
                   class="<?php echo $isActive ? 'bg-brand-500 text-white shadow-xl shadow-brand-500/30 ring-2 ring-brand-400' : 'bg-gray-50 dark:bg-gray-800/80 text-gray-800 dark:text-gray-200 hover:bg-gray-100 dark:hover:bg-gray-700'; ?> group flex flex-col items-center justify-center p-6 rounded-[2.5rem] transition-all duration-300 active:scale-95 border border-transparent">
                    <span class="text-4xl mb-3 transform transition-transform group-hover:scale-110">
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
                            case 'arrow-path': echo '🔄'; break;
                            case 'cog': echo '⚙️'; break;
                            case 'list-bullet': echo '📜'; break;
                            default: echo '•';
                        }
                        ?>
                    </span>
                    <span class="text-[12px] font-black uppercase tracking-wider text-center leading-tight">
                        <?php echo $item['name']; ?>
                    </span>
                </a>
            <?php endforeach; ?>
        </div>
        
        <!-- Footer Info in Sheet -->
        <div class="mt-8 mb-4 text-center">
            <p class="text-[10px] text-gray-400 font-bold uppercase tracking-widest">&copy; <?php echo date('Y'); ?> Finance Board Pro</p>
        </div>
    </div>
</div>

<script>
let startY = 0;
let currentY = 0;
const sheet = document.getElementById('bottom-sheet');
const overlay = document.getElementById('bottom-sheet-overlay');

function toggleBottomSheet() {
    const isHidden = sheet.classList.contains('translate-y-full');
    
    if (isHidden) {
        // Open
        sheet.classList.remove('translate-y-full');
        sheet.style.transform = ''; // Clear inline transform if set by swipe
        overlay.classList.remove('hidden');
        setTimeout(() => overlay.classList.add('opacity-100'), 10);
        document.body.style.overflow = 'hidden';
    } else {
        // Close
        sheet.classList.add('translate-y-full');
        overlay.classList.remove('opacity-100');
        setTimeout(() => {
            overlay.classList.add('hidden');
            document.body.style.overflow = '';
        }, 300);
    }
}

// Support for old toggleSidebar calls
function toggleSidebar() { toggleBottomSheet(); }

// Gesture Support for swipe to close
const handle = document.getElementById('sheet-handle');
handle.addEventListener('touchstart', (e) => {
    startY = e.touches[0].clientY;
    sheet.style.transition = 'none';
}, {passive: true});

handle.addEventListener('touchmove', (e) => {
    currentY = e.touches[0].clientY;
    const diff = currentY - startY;
    if (diff > 0) {
        sheet.style.transform = `translateY(${diff}px)`;
    }
}, {passive: true});

handle.addEventListener('touchend', (e) => {
    const diff = currentY - startY;
    sheet.style.transition = 'transform 0.4s cubic-bezier(0.16, 1, 0.3, 1)';
    if (diff > 120) {
        toggleBottomSheet();
        sheet.style.transform = ''; // Reset for next open
    } else {
        sheet.style.transform = 'translateY(0)';
    }
});
</script>

