<?php
/**
 * Utility functions for security and common operations
 */

/**
 * Generate and store CSRF token in session
 */
function generateCSRFToken() {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    if (!isset($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * Verify CSRF token
 */
function verifyCSRFToken($token) {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

/**
 * Get CSRF token for forms
 */
function getCSRFToken() {
    return generateCSRFToken();
}

/**
 * Validate and sanitize integer input
 */
function validateInt($value, $min = null, $max = null) {
    $int = filter_var($value, FILTER_VALIDATE_INT);
    if ($int === false) {
        return false;
    }
    if ($min !== null && $int < $min) {
        return false;
    }
    if ($max !== null && $int > $max) {
        return false;
    }
    return $int;
}

/**
 * Validate and sanitize float input
 */
function validateFloat($value, $min = null, $max = null) {
    $float = filter_var($value, FILTER_VALIDATE_FLOAT);
    if ($float === false) {
        return false;
    }
    if ($min !== null && $float < $min) {
        return false;
    }
    if ($max !== null && $float > $max) {
        return false;
    }
    return $float;
}

/**
 * Validate datetime string
 */
function validateDateTime($datetime) {
    $d = DateTime::createFromFormat('Y-m-d H:i:s', $datetime);
    if ($d && $d->format('Y-m-d H:i:s') === $datetime) {
        return $datetime;
    }
    $d = DateTime::createFromFormat('Y-m-d\TH:i', $datetime);
    if ($d) {
        return $d->format('Y-m-d H:i:s');
    }
    return false;
}

/**
 * Validate latitude (-90 to 90)
 */
function validateLatitude($latitude) {
    $lat = validateFloat($latitude, -90, 90);
    return $lat !== false ? $lat : false;
}

/**
 * Validate longitude (-180 to 180)
 */
function validateLongitude($longitude) {
    $lng = validateFloat($longitude, -180, 180);
    return $lng !== false ? $lng : false;
}

/**
 * Get database path from config, with fallback to default location
 * Normalizes paths for Windows and Linux
 */
function getDatabasePath() {
    $config = [];
    $configFile = __DIR__ . '/../config/config.php';
    if (file_exists($configFile)) {
        $config = @include $configFile;
        if (!is_array($config)) {
            $config = [];
        }
    }
    
    $dbPath = $config['database_path'] ?? null;
    
    if ($dbPath) {
        $dbPath = str_replace('\\', '/', $dbPath);
        
        $isAbsolute = false;
        if (DIRECTORY_SEPARATOR === '\\') {
            $isAbsolute = preg_match('/^[A-Za-z]:\/|^\/\/|^\\\\/', $dbPath);
        } else {
            $isAbsolute = strpos($dbPath, '/') === 0;
        }
        
        if (!$isAbsolute) {
            $projectRoot = realpath(__DIR__ . '/..');
            if ($projectRoot === false) {
                $projectRoot = __DIR__ . '/..';
            }
            $dbPath = $projectRoot . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $dbPath);
        } else {
            $dbPath = str_replace('/', DIRECTORY_SEPARATOR, $dbPath);
        }
        
        return $dbPath;
    }
    
    return __DIR__ . '/../db/drone-dashboard-database.sqlite';
}

/**
 * Get database connection with error handling
 */
function getDB() {
    $dbPath = getDatabasePath();
    if (!file_exists($dbPath)) {
        throw new Exception('Database file not found: ' . $dbPath);
    }
    $db = new SQLite3($dbPath);
    $db->enableExceptions(true);
    $db->exec('PRAGMA foreign_keys = ON');
    return $db;
}

/**
 * Log error securely (don't expose to user)
 */
function logError($message, $context = []) {
    $logFile = __DIR__ . '/../logs/error.log';
    $logDir = dirname($logFile);
    if (!is_dir($logDir)) {
        mkdir($logDir, 0755, true);
    }
    $timestamp = date('Y-m-d H:i:s');
    $contextStr = !empty($context) ? ' ' . json_encode($context) : '';
    error_log("[$timestamp] $message$contextStr\n", 3, $logFile);
}

