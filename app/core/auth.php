<?php
// in file: app/core/auth.php

// Start the session on any page that includes this file
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

/**
 * Checks if a user is currently logged in.
 * Redirects to the login page if not.
 */
function require_login() {
    if (!isset($_SESSION['user_id'])) {
        // Ensure BASE_URL is defined before using it (though it should be via bootstrap.php)
        if (!defined('BASE_URL')) {
            require_once __DIR__ . '/config.php'; 
        }
        header("Location: " . BASE_URL . "/login.php");
        exit();
    }
}

/**
 * Checks if the logged-in user has a specific role or one of several roles.
 * @param mixed $roles A string for a single role or an array for multiple roles.
 */
function require_role($roles) {
    require_login(); // A user must be logged in to have a role

    // Ensure $roles is an array for easy checking
    if (!is_array($roles)) {
        $roles = [$roles];
    }

    if (!isset($_SESSION['role']) || !in_array($_SESSION['role'], $roles)) {
        // User does not have the required role.
        // For security, just show a "Not Found" error instead of "Access Denied".
        http_response_code(404);
        echo "<h1>404 Not Found</h1>";
        echo "The page you requested could not be found.";
        exit();
    }
}

/**
 * Gets the current user's ID.
 * @return int|null The user ID or null if not logged in.
 */
function get_current_user_id() {
    return $_SESSION['user_id'] ?? null;
}

/**
 * Gets the current user's role.
 * @return string|null The user role or null if not logged in.
 */
function get_current_user_role() {
    return $_SESSION['role'] ?? null;
}

/**
 * Checks if a user is currently logged in.
 * @return bool True if logged in, false otherwise.
 */
function is_logged_in() {
    return isset($_SESSION['user_id']);
}

/**
 * Checks if the current user is an admin.
 * @return bool True if user is an admin, false otherwise.
 */
function is_admin() {
    return isset($_SESSION['role']) && $_SESSION['role'] === 'admin';
}

/**
 * Checks if the current user is an HR Manager.
 * @return bool True if user is an HR Manager, false otherwise.
 */
function is_hr_manager() {
    return isset($_SESSION['role']) && $_SESSION['role'] === 'hr_manager';
}

/**
 * Checks if the current user is HR.
 * @return bool True if user is HR, false otherwise.
 */
function is_hr() {
    return isset($_SESSION['role']) && $_SESSION['role'] === 'hr';
}