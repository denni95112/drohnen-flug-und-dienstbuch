<?php
/**
 * Error reporting configuration
 * Include this file at the very top of all PHP pages
 */

$config = [];
$configFile = __DIR__ . '/../config/config.php';
if (file_exists($configFile)) {
    $config = @include $configFile;
    if (!is_array($config)) {
        $config = [];
    }
}

if (isset($config['debugMode']) && $config['debugMode'] === true) {
    ini_set('display_errors', 1);
    ini_set('display_startup_errors', 1);
    error_reporting(E_ALL);
} else {
    ini_set('display_errors', 0);
    ini_set('display_startup_errors', 0);
    error_reporting(E_ALL & ~E_DEPRECATED & ~E_STRICT);
    ini_set('log_errors', 1);
    $logFile = __DIR__ . '/../logs/php_errors.log';
    $logDir = dirname($logFile);
    if (!is_dir($logDir)) {
        @mkdir($logDir, 0755, true);
    }
    ini_set('error_log', $logFile);
}

if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.cookie_httponly', 1);
    ini_set('session.use_strict_mode', 1);
    ini_set('session.cookie_samesite', 'Strict');
    
    if (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') {
        ini_set('session.cookie_secure', 1);
    }
}

