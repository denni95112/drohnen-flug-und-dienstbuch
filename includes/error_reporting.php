<?php
/**
 * Error reporting configuration
 * Include this file at the very top of all PHP pages
 */

// Load config to check debug mode (suppress errors if config doesn't exist yet)
$config = [];
$configFile = __DIR__ . '/../config/config.php';
if (file_exists($configFile)) {
    $config = @include $configFile;
    if (!is_array($config)) {
        $config = [];
    }
}

// Set error reporting based on debug mode
if (isset($config['debugMode']) && $config['debugMode'] === true) {
    // Debug mode ON - show all errors
    ini_set('display_errors', 1);
    ini_set('display_startup_errors', 1);
    error_reporting(E_ALL);
} else {
    // Debug mode OFF - hide errors from users
    ini_set('display_errors', 0);
    ini_set('display_startup_errors', 0);
    error_reporting(E_ALL & ~E_DEPRECATED & ~E_STRICT);
    // Still log errors
    ini_set('log_errors', 1);
    $logFile = __DIR__ . '/../logs/php_errors.log';
    $logDir = dirname($logFile);
    if (!is_dir($logDir)) {
        @mkdir($logDir, 0755, true);
    }
    ini_set('error_log', $logFile);
}

// Configure secure session settings
if (session_status() === PHP_SESSION_NONE) {
    // Session cookie security
    ini_set('session.cookie_httponly', 1);
    ini_set('session.use_strict_mode', 1);
    ini_set('session.cookie_samesite', 'Strict');
    
    // Set secure flag if HTTPS is being used
    if (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') {
        ini_set('session.cookie_secure', 1);
    }
}

