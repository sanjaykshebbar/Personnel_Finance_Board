<?php
require_once '../config/database.php';
require_once '../config/expense_categories.php';
require_once '../includes/auth.php';
requireLogin();

$userId = getCurrentUserId();

// Handle POST requests
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['reassign_category'])) {
        $oldCategory = $_POST['old_category'];
        $newCategory = trim($_POST['new_category']);
        
        if (empty($newCategory)) {
            $_SESSION['flash_message'] = "New category name cannot be empty.";
        } else {
            $result = reassignCategory($userId, $oldCategory, $newCategory);
            $_SESSION['flash_message'] = $result['message'];
        }
    }
    
    header("Location: categories.php");
    exit;
}

$categoryUsage = getCategoryUsage($userId);
$allCategories = getExpenseCategories($userId);

$pageTitle = 'Expense Categories';
require_once '../includes/header.php';
?>

<div class="space-y-6">
    <!-- Header -->
    <div class="bg-gradient-to-br from-brand-500 to-brand-700 p-8 rounded-3xl shadow-2xl text-white">
        <div class="flex items-center gap-4 mb-4">
            <div class="p-4 bg-white/20 backdrop-blur-sm rounded-2xl">
                <span class="text-4xl">🏷️</span>
            </div>
            <div>
                <h1 class="text-3xl font-black tracking-tight">Expense Categories</h1>
                <p class="text-brand-100 text-sm font-medium mt-1">Manage and organize your spending categories</p>
            </div>
        </div>
    </div>

    <!-- Info Cards -->
    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
        <div class="bg-white dark:bg-gray-800 p-6 rounded-2xl shadow-sm border border-gray-100 dark:border-gray-700">
            <div class="flex items-center gap-3 mb-2">
                <div class="p-2 bg-blue-50 dark:bg-blue-900/30 text-blue-600 dark:text-blue-400 rounded-xl">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z"></path></svg>
                </div>
                <h3 class="text-sm font-bold text-gray-500 dark:text-gray-400 uppercase">Total Categories</h3>
            </div>
            <p class="text-3xl font-black text-gray-900 dark:text-white"><?php echo count($allCategories); ?></p>
        </div>

        <div class="bg-white dark:bg-gray-800 p-6 rounded-2xl shadow-sm border border-gray-100 dark:border-gray-700">
            <div class="flex items-center gap-3 mb-2">
                <div class="p-2 bg-emerald-50 dark:bg-emerald-900/30 text-emerald-600 dark:text-emerald-400 rounded-xl">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                </div>
                <h3 class="text-sm font-bold text-gray-500 dark:text-gray-400 uppercase">Default Categories</h3>
            </div>
            <p class="text-3xl font-black text-gray-900 dark:text-white"><?php echo count(DEFAULT_CATEGORIES); ?></p>
        </div>

        <div class="bg-white dark:bg-gray-800 p-6 rounded-2xl shadow-sm border border-gray-100 dark:border-gray-700">
            <div class="flex items-center gap-3 mb-2">
                <div class="p-2 bg-purple-50 dark:bg-purple-900/30 text-purple-600 dark:text-purple-400 rounded-xl">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path></svg>
                </div>
                <h3 class="text-sm font-bold text-gray-500 dark:text-gray-400 uppercase">Custom Categories</h3>
            </div>
            <p class="text-3xl font-black text-gray-900 dark:text-white"><?php echo count($allCategories) - count(DEFAULT_CATEGORIES); ?></p>
        </div>
    </div>

    <!-- Category Usage Table -->
    <div class="bg-white dark:bg-gray-800 rounded-3xl shadow-sm border border-gray-100 dark:border-gray-700 overflow-hidden">
        <div class="p-6 border-b border-gray-100 dark:border-gray-700">
            <h2 class="text-xl font-black text-gray-900 dark:text-white">Category Usage & Management</h2>
            <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">View spending by category and manage your categories</p>
        </div>

        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                <thead class="bg-gray-50 dark:bg-gray-900">
                    <tr>
                        <th class="px-6 py-4 text-left text-xs font-bold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Category</th>
                        <th class="px-6 py-4 text-right text-xs font-bold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Transactions</th>
                        <th class="px-6 py-4 text-right text-xs font-bold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Total Spent</th>
                        <th class="px-6 py-4 text-center text-xs font-bold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Type</th>
                        <th class="px-6 py-4 text-center text-xs font-bold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Actions</th>
                    </tr>
                </thead>
                <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                    <?php 
                    $usedCategories = array_column($categoryUsage, 'category');
                    
                    // First show used categories
                    foreach($categoryUsage as $cat): 
                        $isDefault = in_array($cat['category'], DEFAULT_CATEGORIES);
                    ?>
                    <tr class="hover:bg-gray-50 dark:hover:bg-gray-700/50 transition-colors">
                        <td class="px-6 py-4 whitespace-nowrap">
                            <div class="flex items-center">
                                <div class="flex-shrink-0 h-10 w-10 bg-gradient-to-br from-brand-500 to-brand-600 rounded-xl flex items-center justify-center text-white font-bold">
                                    <?php echo strtoupper(substr($cat['category'], 0, 2)); ?>
                                </div>
                                <div class="ml-4">
                                    <div class="text-sm font-bold text-gray-900 dark:text-white"><?php echo htmlspecialchars($cat['category']); ?></div>
                                </div>
                            </div>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-right">
                            <span class="text-sm font-bold text-gray-900 dark:text-white"><?php echo number_format($cat['count']); ?></span>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-right">
                            <span class="text-sm font-black text-brand-600 dark:text-brand-400">₹<?php echo number_format($cat['total'], 2); ?></span>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-center">
                            <?php if($isDefault): ?>
                                <span class="px-3 py-1 text-xs font-bold rounded-full bg-emerald-100 dark:bg-emerald-900/30 text-emerald-600 dark:text-emerald-400">Default</span>
                            <?php else: ?>
                                <span class="px-3 py-1 text-xs font-bold rounded-full bg-purple-100 dark:bg-purple-900/30 text-purple-600 dark:text-purple-400">Custom</span>
                            <?php endif; ?>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-center">
                            <button onclick="openReassignModal('<?php echo htmlspecialchars($cat['category'], ENT_QUOTES); ?>')" 
                                    class="px-4 py-2 bg-blue-50 dark:bg-blue-900/30 text-blue-600 dark:text-blue-400 rounded-lg text-xs font-bold hover:bg-blue-100 dark:hover:bg-blue-900/50 transition">
                                Rename/Merge
                            </button>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    
                    <!-- Show unused categories -->
                    <?php foreach($allCategories as $cat): 
                        if(in_array($cat, $usedCategories)) continue;
                        $isDefault = in_array($cat, DEFAULT_CATEGORIES);
                    ?>
                    <tr class="hover:bg-gray-50 dark:hover:bg-gray-700/50 transition-colors opacity-50">
                        <td class="px-6 py-4 whitespace-nowrap">
                            <div class="flex items-center">
                                <div class="flex-shrink-0 h-10 w-10 bg-gray-200 dark:bg-gray-700 rounded-xl flex items-center justify-center text-gray-600 dark:text-gray-400 font-bold">
                                    <?php echo strtoupper(substr($cat, 0, 2)); ?>
                                </div>
                                <div class="ml-4">
                                    <div class="text-sm font-bold text-gray-500 dark:text-gray-500"><?php echo htmlspecialchars($cat); ?></div>
                                </div>
                            </div>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-right">
                            <span class="text-sm text-gray-400">0</span>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-right">
                            <span class="text-sm text-gray-400">₹0.00</span>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-center">
                            <?php if($isDefault): ?>
                                <span class="px-3 py-1 text-xs font-bold rounded-full bg-gray-100 dark:bg-gray-700 text-gray-500">Default</span>
                            <?php else: ?>
                                <span class="px-3 py-1 text-xs font-bold rounded-full bg-gray-100 dark:bg-gray-700 text-gray-500">Custom</span>
                            <?php endif; ?>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-center">
                            <span class="text-xs text-gray-400 italic">Unused</span>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Info Box -->
    <div class="bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-800 rounded-2xl p-6">
        <div class="flex items-start gap-4">
            <div class="flex-shrink-0">
                <svg class="w-6 h-6 text-blue-600 dark:text-blue-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
            </div>
            <div class="flex-1">
                <h3 class="text-sm font-bold text-blue-900 dark:text-blue-300 mb-2">About Categories</h3>
                <ul class="text-sm text-blue-700 dark:text-blue-400 space-y-1">
                    <li>• <strong>Default categories</strong> are built-in and available to all users</li>
                    <li>• <strong>Custom categories</strong> are created automatically when you enter a new category name in expense forms</li>
                    <li>• Use <strong>Rename/Merge</strong> to consolidate similar categories or fix typos</li>
                    <li>• Categories cannot be deleted if they have associated expenses</li>
                </ul>
            </div>
        </div>
    </div>
</div>

<!-- Reassign/Rename Modal -->
<div id="reassignModal" class="fixed inset-0 bg-black/50 backdrop-blur-sm z-50 hidden flex items-center justify-center p-4">
    <div class="bg-white dark:bg-gray-800 rounded-3xl w-full max-w-lg overflow-hidden shadow-2xl">
        <div class="p-6 border-b border-gray-100 dark:border-gray-700">
            <h3 class="text-xl font-black text-gray-900 dark:text-white">Rename / Merge Category</h3>
            <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">All expenses will be updated to the new category name</p>
        </div>
        <form method="POST" class="p-6">
            <input type="hidden" name="reassign_category" value="1">
            <input type="hidden" name="old_category" id="modalOldCategory">
            
            <div class="space-y-6">
                <div>
                    <label class="block text-sm font-bold text-gray-700 dark:text-gray-300 mb-2">Current Category</label>
                    <div class="p-4 bg-gray-50 dark:bg-gray-900 rounded-xl">
                        <p class="text-lg font-black text-gray-900 dark:text-white" id="modalOldCategoryDisplay"></p>
                    </div>
                </div>

                <div>
                    <label class="block text-sm font-bold text-gray-700 dark:text-gray-300 mb-2">New Category Name</label>
                    <input type="text" name="new_category" required
                           class="w-full px-4 py-3 border border-gray-300 dark:border-gray-600 dark:bg-gray-900 dark:text-white rounded-xl focus:ring-2 focus:ring-brand-500 focus:border-transparent outline-none transition">
                    <p class="mt-2 text-xs text-gray-500 dark:text-gray-400">💡 Tip: You can merge categories by renaming to an existing category name</p>
                </div>

                <div class="flex gap-3">
                    <button type="submit" class="flex-1 px-6 py-3 bg-brand-600 text-white font-bold rounded-xl hover:bg-brand-700 transition shadow-lg">
                        Update Category
                    </button>
                    <button type="button" onclick="closeReassignModal()" class="px-6 py-3 bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-300 font-bold rounded-xl hover:bg-gray-200 dark:hover:bg-gray-600 transition">
                        Cancel
                    </button>
                </div>
            </div>
        </form>
    </div>
</div>

<script>
function openReassignModal(category) {
    document.getElementById('modalOldCategory').value = category;
    document.getElementById('modalOldCategoryDisplay').textContent = category;
    document.getElementById('reassignModal').classList.remove('hidden');
}

function closeReassignModal() {
    document.getElementById('reassignModal').classList.add('hidden');
}
</script>

<?php require_once '../includes/footer.php'; ?>
