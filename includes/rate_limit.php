<?php
/**
 * Rate limiting for brute force protection
 * Tracks failed login attempts per IP address
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/**
 * Check if IP has exceeded rate limit
 * @param string $action Action being rate limited (e.g., 'login', 'admin_login')
 * @param int $maxAttempts Maximum attempts allowed
 * @param int $timeWindow Time window in seconds
 * @return bool True if rate limit exceeded, false otherwise
 */
function checkRateLimit($action = 'login', $maxAttempts = 5, $timeWindow = 900) {
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    $key = "rate_limit_{$action}_{$ip}";
    
    try {
        require_once __DIR__ . '/utils.php';
        $dbPath = getDatabasePath();
        $db = new SQLite3($dbPath);
        $db->enableExceptions(true);
        
        // Ensure rate_limits table exists
        $db->exec('CREATE TABLE IF NOT EXISTS rate_limits (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            ip_address TEXT NOT NULL,
            action TEXT NOT NULL,
            attempts INTEGER DEFAULT 1,
            first_attempt DATETIME DEFAULT CURRENT_TIMESTAMP,
            last_attempt DATETIME DEFAULT CURRENT_TIMESTAMP,
            blocked_until DATETIME,
            UNIQUE(ip_address, action)
        )');
        
        $db->exec('DELETE FROM rate_limits WHERE last_attempt < datetime("now", "-' . ($timeWindow * 2) . ' seconds")');
        
        $stmt = $db->prepare('SELECT attempts, first_attempt, blocked_until FROM rate_limits WHERE ip_address = :ip AND action = :action');
        $stmt->bindValue(':ip', $ip, SQLITE3_TEXT);
        $stmt->bindValue(':action', $action, SQLITE3_TEXT);
        $result = $stmt->execute()->fetchArray(SQLITE3_ASSOC);
        
        if ($result) {
            if ($result['blocked_until'] && strtotime($result['blocked_until']) > time()) {
                return true;
            }
            
            $firstAttempt = strtotime($result['first_attempt']);
            if (time() - $firstAttempt > $timeWindow) {
                $stmt = $db->prepare('UPDATE rate_limits SET attempts = 1, first_attempt = datetime("now"), last_attempt = datetime("now"), blocked_until = NULL WHERE ip_address = :ip AND action = :action');
                $stmt->bindValue(':ip', $ip, SQLITE3_TEXT);
                $stmt->bindValue(':action', $action, SQLITE3_TEXT);
                $stmt->execute();
                return false;
            }
            
            if ($result['attempts'] >= $maxAttempts) {
                $blockDuration = min(15 * 60 * $result['attempts'], 2 * 60 * 60);
                $blockedUntil = date('Y-m-d H:i:s', time() + $blockDuration);
                
                $stmt = $db->prepare('UPDATE rate_limits SET blocked_until = :blocked_until WHERE ip_address = :ip AND action = :action');
                $stmt->bindValue(':blocked_until', $blockedUntil, SQLITE3_TEXT);
                $stmt->bindValue(':ip', $ip, SQLITE3_TEXT);
                $stmt->bindValue(':action', $action, SQLITE3_TEXT);
                $stmt->execute();
                
                return true;
            }
        }
        
        return false;
    } catch (Exception $e) {
        error_log("Rate limit check failed: " . $e->getMessage());
        return false;
    }
}

/**
 * Record a failed attempt
 * @param string $action Action being rate limited
 */
function recordFailedAttempt($action = 'login') {
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    
    try {
        require_once __DIR__ . '/utils.php';
        $dbPath = getDatabasePath();
        $db = new SQLite3($dbPath);
        $db->enableExceptions(true);
        
        // Ensure rate_limits table exists
        $db->exec('CREATE TABLE IF NOT EXISTS rate_limits (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            ip_address TEXT NOT NULL,
            action TEXT NOT NULL,
            attempts INTEGER DEFAULT 1,
            first_attempt DATETIME DEFAULT CURRENT_TIMESTAMP,
            last_attempt DATETIME DEFAULT CURRENT_TIMESTAMP,
            blocked_until DATETIME,
            UNIQUE(ip_address, action)
        )');
        
        $stmt = $db->prepare('INSERT INTO rate_limits (ip_address, action, attempts, first_attempt, last_attempt) 
                              VALUES (:ip, :action, 1, datetime("now"), datetime("now"))
                              ON CONFLICT(ip_address, action) DO UPDATE SET 
                              attempts = attempts + 1, 
                              last_attempt = datetime("now")');
        $stmt->bindValue(':ip', $ip, SQLITE3_TEXT);
        $stmt->bindValue(':action', $action, SQLITE3_TEXT);
        $stmt->execute();
    } catch (Exception $e) {
        error_log("Failed to record rate limit attempt: " . $e->getMessage());
    }
}

/**
 * Clear rate limit for successful login
 * @param string $action Action to clear
 */
function clearRateLimit($action = 'login') {
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    
    try {
        require_once __DIR__ . '/utils.php';
        $dbPath = getDatabasePath();
        $db = new SQLite3($dbPath);
        $db->enableExceptions(true);
        $stmt = $db->prepare('DELETE FROM rate_limits WHERE ip_address = :ip AND action = :action');
        $stmt->bindValue(':ip', $ip, SQLITE3_TEXT);
        $stmt->bindValue(':action', $action, SQLITE3_TEXT);
        $stmt->execute();
    } catch (Exception $e) {
    }
}

