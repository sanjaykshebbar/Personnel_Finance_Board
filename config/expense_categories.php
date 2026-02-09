<?php
/**
 * Expense Categories Configuration & Management
 * Provides centralized category management for the expense tracker
 */

require_once __DIR__ . '/database.php';

// Default categories that come with the system
const DEFAULT_CATEGORIES = [
    'Food & Dining',
    'Transportation',
    'Shopping',
    'Entertainment',
    'Bills & Utilities',
    'Healthcare',
    'Education',
    'Investment',
    'Credit Card Bill',
    'Groceries',
    'Personal Care',
    'Travel',
    'Gifts & Donations',
    'Home & Garden',
    'Insurance',
    'Other'
];

/**
 * Get all categories for a user (default + custom)
 */
function getExpenseCategories($userId) {
    global $pdo;
    
    // Get custom categories from database
    $stmt = $pdo->prepare("
        SELECT DISTINCT category 
        FROM expenses 
        WHERE user_id = ? AND category NOT IN ('" . implode("','", DEFAULT_CATEGORIES) . "')
        ORDER BY category ASC
    ");
    $stmt->execute([$userId]);
    $customCategories = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    // Merge default and custom categories
    $allCategories = array_merge(DEFAULT_CATEGORIES, $customCategories);
    sort($allCategories);
    
    return $allCategories;
}

/**
 * Get category usage statistics
 */
function getCategoryUsage($userId) {
    global $pdo;
    
    $stmt = $pdo->prepare("
        SELECT category, COUNT(*) as count, SUM(amount) as total
        FROM expenses 
        WHERE user_id = ?
        GROUP BY category
        ORDER BY total DESC
    ");
    $stmt->execute([$userId]);
    
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Check if a category is in use
 */
function isCategoryInUse($userId, $category) {
    global $pdo;
    
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM expenses WHERE user_id = ? AND category = ?");
    $stmt->execute([$userId, $category]);
    
    return $stmt->fetchColumn() > 0;
}

/**
 * Delete expenses with a specific category (use with caution!)
 */
function deleteCategory($userId, $category) {
    global $pdo;
    
    // Prevent deletion of default categories
    if (in_array($category, DEFAULT_CATEGORIES)) {
        return ['success' => false, 'message' => 'Cannot delete default categories.'];
    }
    
    // Check if in use
    if (isCategoryInUse($userId, $category)) {
        return ['success' => false, 'message' => 'Category is currently in use. Delete or reassign expenses first.'];
    }
    
    return ['success' => true, 'message' => 'Category can be safely removed (no expenses using it).'];
}

/**
 * Rename/Reassign category
 */
function reassignCategory($userId, $oldCategory, $newCategory) {
    global $pdo;
    
    try {
        $stmt = $pdo->prepare("UPDATE expenses SET category = ? WHERE user_id = ? AND category = ?");
        $stmt->execute([$newCategory, $userId, $oldCategory]);
        
        return ['success' => true, 'message' => "Updated {$stmt->rowCount()} expenses to new category."];
    } catch (Exception $e) {
        return ['success' => false, 'message' => 'Error: ' . $e->getMessage()];
    }
}
