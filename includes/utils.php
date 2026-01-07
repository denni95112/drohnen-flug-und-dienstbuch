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

/**
 * Check if a newer version is available on GitHub
 * @param string $currentVersion Current application version
 * @param string $owner GitHub repository owner
 * @param string $repo GitHub repository name
 * @return array|null Returns array with 'available' (bool), 'version' (string), 'url' (string) or null on error
 */
function checkGitHubVersion($currentVersion, $owner, $repo) {
    $cacheFile = __DIR__ . '/../logs/github_version_cache.json';
    $cacheTime = 3600;
    
    if (file_exists($cacheFile)) {
        $cache = json_decode(file_get_contents($cacheFile), true);
        if ($cache && isset($cache['timestamp']) && (time() - $cache['timestamp']) < $cacheTime) {
            return $cache['data'];
        }
    }
    
    $url = "https://api.github.com/repos/{$owner}/{$repo}/releases/latest";
    $response = null;
    $httpCode = 0;
    
    if (function_exists('curl_init')) {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'User-Agent: Drohnen-Flug-und-Dienstbuch',
            'Accept: application/vnd.github.v3+json'
        ]);
        curl_setopt($ch, CURLOPT_TIMEOUT, 5);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 3);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
    } elseif (ini_get('allow_url_fopen')) {
        $context = stream_context_create([
            'http' => [
                'method' => 'GET',
                'header' => [
                    'User-Agent: Drohnen-Flug-und-Dienstbuch',
                    'Accept: application/vnd.github.v3+json'
                ],
                'timeout' => 5
            ]
        ]);
        
        $response = @file_get_contents($url, false, $context);
        if ($response !== false) {
            $httpCode = 200;
        }
    }
    
    if ($httpCode !== 200 || !$response) {
        if (file_exists($cacheFile)) {
            $cache = json_decode(file_get_contents($cacheFile), true);
            if ($cache && isset($cache['data'])) {
                return $cache['data'];
            }
        }
        return null;
    }
    
    $data = json_decode($response, true);
    if (!$data || !isset($data['tag_name'])) {
        return null;
    }
    
    $latestVersion = ltrim($data['tag_name'], 'v');
    $available = version_compare($latestVersion, $currentVersion, '>');
    
    $result = [
        'available' => $available,
        'version' => $latestVersion,
        'url' => $data['html_url'] ?? "https://github.com/{$owner}/{$repo}/releases/latest"
    ];
    
    $cacheDir = dirname($cacheFile);
    if (!is_dir($cacheDir)) {
        @mkdir($cacheDir, 0755, true);
    }
    
    @file_put_contents($cacheFile, json_encode([
        'timestamp' => time(),
        'data' => $result
    ]));
    
    return $result;
}

/**
 * Convert datetime from local timezone to UTC for storage
 * @param string $datetime Local datetime string (format: 'Y-m-d H:i:s' or 'Y-m-d\TH:i')
 * @return string UTC datetime string in 'Y-m-d H:i:s' format
 */
function toUTC($datetime) {
    $config = [];
    $configFile = __DIR__ . '/../config/config.php';
    if (file_exists($configFile)) {
        $config = @include $configFile;
        if (!is_array($config)) {
            $config = [];
        }
    }
    
    $timezone = $config['timezone'] ?? 'Europe/Berlin';
    
    // Handle both 'Y-m-d H:i:s' and 'Y-m-d\TH:i' formats
    $date = null;
    if (strpos($datetime, 'T') !== false) {
        // Format: 'Y-m-d\TH:i' (from datetime-local input)
        $date = DateTime::createFromFormat('Y-m-d\TH:i', $datetime, new DateTimeZone($timezone));
    } else {
        // Format: 'Y-m-d H:i:s' or 'Y-m-d'
        $date = DateTime::createFromFormat('Y-m-d H:i:s', $datetime, new DateTimeZone($timezone));
        if (!$date) {
            $date = DateTime::createFromFormat('Y-m-d', $datetime, new DateTimeZone($timezone));
        }
    }
    
    if (!$date) {
        // Fallback: try to parse with strtotime
        $timestamp = strtotime($datetime);
        if ($timestamp === false) {
            throw new InvalidArgumentException("Invalid datetime format: $datetime");
        }
        $date = new DateTime('@' . $timestamp);
        $date->setTimezone(new DateTimeZone($timezone));
    }
    
    $date->setTimezone(new DateTimeZone('UTC'));
    return $date->format('Y-m-d H:i:s');
}

/**
 * Convert datetime from UTC to local timezone for display
 * @param string $utcTime UTC datetime string (format: 'Y-m-d H:i:s')
 * @param string $format Output format (default: 'Y-m-d H:i:s')
 * @return string Local datetime string
 */
function toLocalTime($utcTime, $format = 'Y-m-d H:i:s') {
    $config = [];
    $configFile = __DIR__ . '/../config/config.php';
    if (file_exists($configFile)) {
        $config = @include $configFile;
        if (!is_array($config)) {
            $config = [];
        }
    }
    
    $timezone = $config['timezone'] ?? 'Europe/Berlin';
    
    // Handle null or empty strings
    if (empty($utcTime)) {
        return '';
    }
    
    // Parse UTC datetime
    $date = DateTime::createFromFormat('Y-m-d H:i:s', $utcTime, new DateTimeZone('UTC'));
    if (!$date) {
        // Try parsing as-is (might already be in a different format)
        $date = new DateTime($utcTime, new DateTimeZone('UTC'));
    }
    
    $date->setTimezone(new DateTimeZone($timezone));
    return $date->format($format);
}

/**
 * Convert datetime from UTC to local timezone for datetime-local input
 * @param string $utcTime UTC datetime string (format: 'Y-m-d H:i:s')
 * @return string Local datetime string in 'Y-m-d\TH:i' format
 */
function toLocalTimeForInput($utcTime) {
    return toLocalTime($utcTime, 'Y-m-d\TH:i');
}

/**
 * Get current datetime in UTC
 * @return string UTC datetime string in 'Y-m-d H:i:s' format
 */
function getCurrentUTC() {
    $date = new DateTime('now', new DateTimeZone('UTC'));
    return $date->format('Y-m-d H:i:s');
}

