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
 * Get encryption key from session for file encryption
 * @return string Encryption key
 */
function getEncryptionKey() {
    if (isset($_SESSION['encryption_key'])) {
        return $_SESSION['encryption_key'];
    }
    $key = bin2hex(random_bytes(32));
    $_SESSION['encryption_key'] = $key;
    return $key;
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
