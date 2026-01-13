<?php
require_once __DIR__ . '/error_reporting.php';
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/**
 * Ensure auth_tokens table exists in database
 * @param SQLite3 $db Database connection
 */
function ensureAuthTokensTable($db) {
    $db->exec('CREATE TABLE IF NOT EXISTS auth_tokens (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        token TEXT NOT NULL UNIQUE,
        user_id INTEGER DEFAULT 1,
        expires_at DATETIME NOT NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    )');
}

/**
 * Check if the user is authenticated based on session or cookie
 * @return bool True if authenticated, false otherwise
 */
function isAuthenticated() {
    if (isset($_SESSION['loggedin']) && $_SESSION['loggedin'] === true) {
        if (isset($_SESSION['login_time']) && (time() - $_SESSION['login_time']) > (30 * 24 * 60 * 60)) {
            logout();
            return false;
        }
        return true;
    }

    if (isset($_COOKIE['dg_dashboard_token'])) {
        $token = $_COOKIE['dg_dashboard_token'];
        
        try {
            require_once __DIR__ . '/utils.php';
            $dbPath = getDatabasePath();
            $db = new SQLite3($dbPath);
            $db->enableExceptions(true);
            ensureAuthTokensTable($db);
            
            $stmt = $db->prepare('SELECT user_id, expires_at FROM auth_tokens WHERE token = :token AND expires_at > datetime("now")');
            $stmt->bindValue(':token', hash('sha256', $token), SQLITE3_TEXT);
            $result = $stmt->execute()->fetchArray(SQLITE3_ASSOC);
            
            if ($result) {
                $_SESSION['loggedin'] = true;
                $_SESSION['login_time'] = time();
                return true;
            } else {
                setcookie('dg_dashboard_token', '', time() - 3600, "/", "", true, true);
            }
        } catch (Exception $e) {
            return false;
        }
    }

    return false;
}

/**
 * Require authentication, redirect to login if not authenticated
 */
function requireAuth() {
    if (!isAuthenticated()) {
        header('Location: ../index.php');
        exit();
    }
}

/**
 * Set secure login cookie with token for "remember me" functionality
 */
function setLoginCookie() {
    $token = bin2hex(random_bytes(32));
    $tokenHash = hash('sha256', $token);
    $expires = time() + (30 * 24 * 60 * 60);
    
    try {
        require_once __DIR__ . '/utils.php';
        $dbPath = getDatabasePath();
        $db = new SQLite3($dbPath);
        $db->enableExceptions(true);
        
        ensureAuthTokensTable($db);
        $db->exec('DELETE FROM auth_tokens WHERE expires_at < datetime("now")');
        
        $stmt = $db->prepare('INSERT INTO auth_tokens (token, expires_at) VALUES (:token, datetime(:expires, "unixepoch"))');
        $stmt->bindValue(':token', $tokenHash, SQLITE3_TEXT);
        $stmt->bindValue(':expires', $expires, SQLITE3_INTEGER);
        $stmt->execute();
        
        $secure = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on';
        setcookie('dg_dashboard_token', $token, $expires, "/", "", $secure, true);
    } catch (Exception $e) {
        error_log("Failed to create auth token: " . $e->getMessage());
    }
}

/**
 * Get encryption key from config file for file encryption
 * Generates and stores key in config if it doesn't exist
 * Returns the key as binary (for OpenSSL functions)
 * @return string Encryption key (binary format)
 */
function getEncryptionKey() {
    $configFile = __DIR__ . '/../config/config.php';
    if (!file_exists($configFile)) {
        throw new Exception('Configuration file not found');
    }
    
    $config = include $configFile;
    if (!is_array($config)) {
        throw new Exception('Invalid configuration file');
    }
    
    // Check if encryption key exists in config
    if (isset($config['encryption']['key']) && !empty($config['encryption']['key'])) {
        $keyHex = $config['encryption']['key'];
        // Convert hex to binary for OpenSSL functions
        // If it's already binary (length 32), return as-is
        // If it's hex (length 64), convert to binary
        if (strlen($keyHex) === 64 && ctype_xdigit($keyHex)) {
            return hex2bin($keyHex);
        } elseif (strlen($keyHex) === 32) {
            // Already binary, return as-is
            return $keyHex;
        } else {
            // Invalid key format, generate new one
            $key = bin2hex(random_bytes(32));
            require_once __DIR__ . '/utils.php';
            if (!isset($config['encryption'])) {
                $config['encryption'] = [];
            }
            $config['encryption']['key'] = $key;
            if (!isset($config['encryption']['method'])) {
                $config['encryption']['method'] = 'aes-256-cbc';
            }
            updateConfig('encryption', $config['encryption']);
            return hex2bin($key);
        }
    }
    
    // Generate new key if it doesn't exist
    $keyHex = bin2hex(random_bytes(32)); // 64 hex characters = 32 bytes for AES-256
    
    // Update config with new key
    require_once __DIR__ . '/utils.php';
    if (!isset($config['encryption'])) {
        $config['encryption'] = [];
    }
    
    // Preserve existing encryption settings (method, iv) if they exist
    $encryptionConfig = $config['encryption'];
    $encryptionConfig['key'] = $keyHex;
    
    // Ensure encryption method is set
    if (!isset($encryptionConfig['method'])) {
        $encryptionConfig['method'] = 'aes-256-cbc';
    }
    
    // Save updated config (preserves existing 'iv' and other settings)
    updateConfig('encryption', $encryptionConfig);
    
    // Return binary key for OpenSSL functions
    return hex2bin($keyHex);
}

/**
 * Log the user out and delete the cookie
 */
function logout() {
    if (isset($_COOKIE['dg_dashboard_token'])) {
        $token = $_COOKIE['dg_dashboard_token'];
        try {
            require_once __DIR__ . '/utils.php';
            $dbPath = getDatabasePath();
            $db = new SQLite3($dbPath);
            $db->enableExceptions(true);
            ensureAuthTokensTable($db);
            $stmt = $db->prepare('DELETE FROM auth_tokens WHERE token = :token');
            $stmt->bindValue(':token', hash('sha256', $token), SQLITE3_TEXT);
            $stmt->execute();
        } catch (Exception $e) {
        }
    }
    
    setcookie('dg_dashboard_token', '', time() - 3600, "/", "", true, true);
    
    $_SESSION = [];
    if (isset($_COOKIE[session_name()])) {
        setcookie(session_name(), '', time() - 3600, "/");
    }
    session_destroy();
}

/**
 * Check if user is admin
 * @return bool True if user is admin, false otherwise
 */
function isAdmin() {
    return isset($_SESSION['is_admin']) && $_SESSION['is_admin'] === true;
}

/**
 * Set admin status in session
 * @param bool $isAdmin Admin status
 */
function setAdminStatus($isAdmin) {
    $_SESSION['is_admin'] = $isAdmin === true;
}
?>
