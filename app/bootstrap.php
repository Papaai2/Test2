<?php
// in file: app/bootstrap.php

// Define the application path
define('APP_PATH', __DIR__);

// Include Composer's autoloader to make vendor libraries available
require_once APP_PATH . '/../vendor/autoload.php';

// Error reporting (for development)
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Set default timezone based on your location
date_default_timezone_set('Africa/Cairo');

// Require core application files
require_once APP_PATH . '/core/config.php';
require_once APP_PATH . '/core/database.php';
require_once APP_PATH . '/core/auth.php';
require_once APP_PATH . '/core/helpers.php';
require_once APP_PATH . '/core/error_handler.php';

// Require Application Services
// This line was missing and is now added to make the service class available globally.
require_once APP_PATH . '/core/services/AttendanceService.php';

// Start session if not already started
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}