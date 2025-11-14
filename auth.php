<?php
require_once __DIR__ . '/includes/error_reporting.php';
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Helper function to ensure auth_tokens table exists
function ensureAuthTokensTable($db) {
    $db->exec('CREATE TABLE IF NOT EXISTS auth_tokens (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        token TEXT NOT NULL UNIQUE,
        user_id INTEGER DEFAULT 1,
        expires_at DATETIME NOT NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    )');
}

// Function to check if the user is authenticated based on session
function isAuthenticated() {
    // Check if session is set for logged in
    if (isset($_SESSION['loggedin']) && $_SESSION['loggedin'] === true) {
        // Check session timeout (30 days)
        if (isset($_SESSION['login_time']) && (time() - $_SESSION['login_time']) > (30 * 24 * 60 * 60)) {
            // Session expired
            logout();
            return false;
        }
        return true;
    }

    // Check if the login cookie exists (for "remember me" functionality)
    if (isset($_COOKIE['dg_dashboard_token'])) {
        $token = $_COOKIE['dg_dashboard_token'];
        
        // Verify token from database
        try {
            require_once __DIR__ . '/includes/utils.php';
            $dbPath = getDatabasePath();
            $db = new SQLite3($dbPath);
            // Ensure table exists (for existing installations)
            ensureAuthTokensTable($db);
            
            $stmt = $db->prepare('SELECT user_id, expires_at FROM auth_tokens WHERE token = :token AND expires_at > datetime("now")');
            $stmt->bindValue(':token', hash('sha256', $token), SQLITE3_TEXT);
            $result = $stmt->execute()->fetchArray(SQLITE3_ASSOC);
            
            if ($result) {
                // Valid token, restore session
                $_SESSION['loggedin'] = true;
                $_SESSION['login_time'] = time();
                return true;
            } else {
                // Invalid or expired token
                setcookie('dg_dashboard_token', '', time() - 3600, "/", "", true, true);
            }
        } catch (Exception $e) {
            // Database error, fail securely
            return false;
        }
    }

    return false;
}

// Function to require authentication (redirect if not authenticated)
function requireAuth() {
    if (!isAuthenticated()) {
        header('Location: index.php');
        exit();
    }
}

// Function to set secure login cookie with token
function setLoginCookie() {
    // Generate secure random token
    $token = bin2hex(random_bytes(32));
    $tokenHash = hash('sha256', $token);
    $expires = time() + (30 * 24 * 60 * 60); // 30 days
    
    // Store token in database
    try {
        require_once __DIR__ . '/includes/utils.php';
        $dbPath = getDatabasePath();
        $db = new SQLite3($dbPath);
        
        // Ensure auth_tokens table exists
        ensureAuthTokensTable($db);
        
        // Clean up expired tokens
        $db->exec('DELETE FROM auth_tokens WHERE expires_at < datetime("now")');
        
        // Insert new token
        $stmt = $db->prepare('INSERT INTO auth_tokens (token, expires_at) VALUES (:token, datetime(:expires, "unixepoch"))');
        $stmt->bindValue(':token', $tokenHash, SQLITE3_TEXT);
        $stmt->bindValue(':expires', $expires, SQLITE3_INTEGER);
        $stmt->execute();
        
        // Set secure cookie
        $secure = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on';
        setcookie('dg_dashboard_token', $token, $expires, "/", "", $secure, true); // HttpOnly, Secure
    } catch (Exception $e) {
        // If database fails, just use session (no "remember me")
        error_log("Failed to create auth token: " . $e->getMessage());
    }
}

// Function to get encryption key from session (for file encryption)
function getEncryptionKey() {
    if (isset($_SESSION['encryption_key'])) {
        return $_SESSION['encryption_key'];
    }
    // Generate and store key for this session
    $key = bin2hex(random_bytes(32));
    $_SESSION['encryption_key'] = $key;
    return $key;
}

// Function to log the user out and delete the cookie
function logout() {
    // Delete token from database if cookie exists
    if (isset($_COOKIE['dg_dashboard_token'])) {
        $token = $_COOKIE['dg_dashboard_token'];
        try {
            require_once __DIR__ . '/includes/utils.php';
            $dbPath = getDatabasePath();
            $db = new SQLite3($dbPath);
            // Ensure table exists before trying to delete
            ensureAuthTokensTable($db);
            $stmt = $db->prepare('DELETE FROM auth_tokens WHERE token = :token');
            $stmt->bindValue(':token', hash('sha256', $token), SQLITE3_TEXT);
            $stmt->execute();
        } catch (Exception $e) {
            // Ignore errors
        }
    }
    
    // Delete cookie
    setcookie('dg_dashboard_token', '', time() - 3600, "/", "", true, true);
    
    // Destroy session
    $_SESSION = [];
    if (isset($_COOKIE[session_name()])) {
        setcookie(session_name(), '', time() - 3600, "/");
    }
    session_destroy();
}

// Check if user is admin (stored in session, not cookie)
function isAdmin() {
    return isset($_SESSION['is_admin']) && $_SESSION['is_admin'] === true;
}

// Set admin status in session
function setAdminStatus($isAdmin) {
    $_SESSION['is_admin'] = $isAdmin === true;
}
?>
