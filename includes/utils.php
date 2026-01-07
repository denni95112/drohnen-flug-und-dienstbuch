<?php
/**
 * Utility functions for security and common operations
 */

/**
 * Generate and store CSRF token in session
 */
function generateCSRFToken(): string {
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
function verifyCSRFToken(string $token): bool {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

/**
 * Get CSRF token for forms
 */
function getCSRFToken(): string {
    return generateCSRFToken();
}

/**
 * Validate and sanitize integer input
 */
function validateInt($value, ?int $min = null, ?int $max = null) {
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
function validateFloat($value, ?float $min = null, ?float $max = null) {
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
function validateDateTime(string $datetime) {
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
 * @return float|false
 */
function validateLatitude($latitude) {
    $lat = validateFloat($latitude, -90, 90);
    return $lat !== false ? $lat : false;
}

/**
 * Validate longitude (-180 to 180)
 * @return float|false
 */
function validateLongitude($longitude) {
    $lng = validateFloat($longitude, -180, 180);
    return $lng !== false ? $lng : false;
}

/**
 * Get database path from config, with fallback to default location
 * Normalizes paths for Windows and Linux
 */
function getDatabasePath(): string {
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
function getDB(): SQLite3 {
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
function logError(string $message, array $context = []): void {
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
function checkGitHubVersion(string $currentVersion, string $owner, string $repo): ?array {
    $cacheFile = __DIR__ . '/../logs/github_version_cache.json';
    $cacheTime = 3600;
    
    if (file_exists($cacheFile)) {
        $cache = json_decode(file_get_contents($cacheFile), true);
        if ($cache && isset($cache['timestamp']) && (time() - $cache['timestamp']) < $cacheTime) {
            return $cache['data'];
        }
    }
    
    // Fetch all releases to find the latest non-draft, non-prerelease release
    // This is more reliable than /releases/latest which can be cached
    $url = "https://api.github.com/repos/{$owner}/{$repo}/releases";
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
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
        
        // Try to use system CA bundle if available
        $caBundlePaths = [
            __DIR__ . '/../cacert.pem',
            ini_get('curl.cainfo'),
            getenv('SSL_CERT_FILE')
        ];
        foreach ($caBundlePaths as $caPath) {
            if ($caPath && file_exists($caPath)) {
                curl_setopt($ch, CURLOPT_CAINFO, $caPath);
                break;
            }
        }
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);
        
        // If SSL verification failed, try again without verification (less secure but works)
        if ($httpCode === 0 && strpos($curlError, 'SSL') !== false) {
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'User-Agent: Drohnen-Flug-und-Dienstbuch',
                'Accept: application/vnd.github.v3+json'
            ]);
            curl_setopt($ch, CURLOPT_TIMEOUT, 5);
            curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 3);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
            
            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
        }
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
    
    $releases = json_decode($response, true);
    if (!is_array($releases) || empty($releases)) {
        return null;
    }
    
    // Find the latest non-draft, non-prerelease release
    $latestRelease = null;
    foreach ($releases as $release) {
        if (isset($release['draft']) && $release['draft'] === true) {
            continue;
        }
        if (isset($release['prerelease']) && $release['prerelease'] === true) {
            continue;
        }
        if (!isset($release['tag_name'])) {
            continue;
        }
        $latestRelease = $release;
        break; // Releases are already sorted by date, newest first
    }
    
    if (!$latestRelease || !isset($latestRelease['tag_name'])) {
        return null;
    }
    
    $latestVersion = ltrim($latestRelease['tag_name'], 'v');
    $available = version_compare($latestVersion, $currentVersion, '>');
    
    $result = [
        'available' => $available,
        'version' => $latestVersion,
        'url' => $latestRelease['html_url'] ?? "https://github.com/{$owner}/{$repo}/releases/latest"
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
function toUTC(string $datetime): string {
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
 * @param string|null $utcTime UTC datetime string (format: 'Y-m-d H:i:s')
 * @param string $format Output format (default: 'Y-m-d H:i:s')
 * @return string Local datetime string (empty string if input is null/empty)
 */
function toLocalTime(?string $utcTime, string $format = 'Y-m-d H:i:s'): string {
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
    if ($utcTime === null || $utcTime === '') {
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
 * @param string|null $utcTime UTC datetime string (format: 'Y-m-d H:i:s')
 * @return string Local datetime string in 'Y-m-d\TH:i' format (empty string if input is null/empty)
 */
function toLocalTimeForInput(?string $utcTime): string {
    return toLocalTime($utcTime, 'Y-m-d\TH:i');
}

/**
 * Get current datetime in UTC
 * @return string UTC datetime string in 'Y-m-d H:i:s' format
 */
function getCurrentUTC(): string {
    $date = new DateTime('now', new DateTimeZone('UTC'));
    return $date->format('Y-m-d H:i:s');
}

/**
 * Verify password against stored hash (supports both bcrypt and legacy SHA256)
 * @param string $password Plain text password to verify
 * @param string $storedHash Stored password hash
 * @return array Returns ['valid' => bool, 'needs_rehash' => bool, 'new_hash' => string|null]
 */
function verifyPassword(string $password, string $storedHash): array {
    $result = [
        'valid' => false,
        'needs_rehash' => false,
        'new_hash' => null
    ];
    
    // Try bcrypt/argon2 first (modern method)
    if (password_verify($password, $storedHash)) {
        $result['valid'] = true;
        // Check if rehashing is needed (e.g., cost factor changed)
        if (password_needs_rehash($storedHash, PASSWORD_DEFAULT)) {
            $result['needs_rehash'] = true;
            $result['new_hash'] = password_hash($password, PASSWORD_DEFAULT);
        }
    }
    // Fallback to legacy SHA256 (for backward compatibility)
    elseif (hash('sha256', $password) === $storedHash) {
        $result['valid'] = true;
        $result['needs_rehash'] = true;
        $result['new_hash'] = password_hash($password, PASSWORD_DEFAULT);
    }
    
    return $result;
}

/**
 * Update config.php with a new key-value pair
 * @param string $key Config key to add/update
 * @param mixed $value Config value
 * @return bool True on success, false on failure
 */
function updateConfig(string $key, $value): bool {
    $configFile = __DIR__ . '/../config/config.php';
    if (!file_exists($configFile)) {
        return false;
    }
    
    // Read current config
    $config = include $configFile;
    if (!is_array($config)) {
        return false;
    }
    
    // Update the value
    $config[$key] = $value;
    
    // Write back to file, preserving structure
    $content = "<?php\nreturn [\n";
    
    foreach ($config as $k => $v) {
        if (is_array($v)) {
            $content .= "    '{$k}' => [\n";
            foreach ($v as $subKey => $subValue) {
                $subValueEscaped = addslashes($subValue);
                $content .= "        '{$subKey}' => '{$subValueEscaped}',\n";
            }
            $content .= "    ],\n";
        } elseif (is_bool($v)) {
            $content .= "    '{$k}' => " . ($v ? 'true' : 'false') . ",\n";
        } elseif (is_numeric($v) && !is_string($v)) {
            $content .= "    '{$k}' => {$v},\n";
        } else {
            // Handle special cases for existing config keys
            $vEscaped = addslashes($v);
            $comment = '';
            if ($k === 'password_hash') {
                $comment = ' // password_hash (bcrypt/argon2)';
            }
            $content .= "    '{$k}' => '{$vEscaped}',{$comment}\n";
        }
    }
    
    $content .= "];\n";
    
    // Create backup before writing
    $backupFile = $configFile . '.backup.' . time();
    @copy($configFile, $backupFile);
    
    $result = file_put_contents($configFile, $content) !== false;
    
    // Remove backup if write was successful
    if ($result && file_exists($backupFile)) {
        @unlink($backupFile);
    }
    
    return $result;
}

