<?php
// includes/auth.php
session_start();

/**
 * Require Login Middleware
 * Redirects to login page if user is not authenticated
 */
function requireLogin() {
    if (!isset($_SESSION['user_id'])) {
        header('Location: ../login.php');
        exit;
    }
}

/**
 * Get Current User ID
 */
function getCurrentUserId() {
    return $_SESSION['user_id'] ?? null;
}

/**
 * Get Current User Name
 */
function getCurrentUserName() {
    return $_SESSION['user_name'] ?? 'User';
}
?>
