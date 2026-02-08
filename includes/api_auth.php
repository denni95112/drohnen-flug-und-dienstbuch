<?php
/**
 * API Token Authentication
 * Functions for managing API tokens and token-based authentication
 */

require_once __DIR__ . '/utils.php';

/**
 * Verify API token from Authorization header
 * Returns token info array or null if invalid
 * 
 * @return array|null Token info with keys: id, name, created_at, last_used_at, expires_at, is_active
 */
function verifyApiToken(): ?array {
    // Get Authorization header
    $authHeader = $_SERVER['HTTP_AUTHORIZATION'] ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? '';
    
    if (empty($authHeader)) {
        return null;
    }
    
    // Parse Bearer token
    if (preg_match('/Bearer\s+(.+)$/i', $authHeader, $matches)) {
        $plainToken = trim($matches[1]);
    } else {
        return null;
    }
    
    if (empty($plainToken)) {
        return null;
    }
    
    // Hash token for lookup
    $tokenHash = hash('sha256', $plainToken);
    
    try {
        $dbPath = getDatabasePath();
        $db = new SQLite3($dbPath);
        $db->enableExceptions(true);
        
        // Check if table exists (might not exist before migration)
        $tableExists = $db->querySingle("SELECT name FROM sqlite_master WHERE type='table' AND name='api_tokens'");
        if (!$tableExists) {
            return null;
        }
        
        // Look up token
        $stmt = $db->prepare('SELECT id, name, created_at, last_used_at, expires_at, is_active 
                              FROM api_tokens 
                              WHERE token = :token 
                              AND is_active = 1');
        $stmt->bindValue(':token', $tokenHash, SQLITE3_TEXT);
        $result = $stmt->execute();
        $row = $result->fetchArray(SQLITE3_ASSOC);
        
        if (!$row) {
            return null;
        }
        
        // Check expiration
        if (!empty($row['expires_at'])) {
            $expiresAt = new DateTime($row['expires_at']);
            $now = new DateTime('now');
            if ($expiresAt < $now) {
                return null; // Token expired
            }
        }
        
        // Update last_used_at
        $updateStmt = $db->prepare('UPDATE api_tokens SET last_used_at = datetime("now") WHERE id = :id');
        $updateStmt->bindValue(':id', $row['id'], SQLITE3_INTEGER);
        $updateStmt->execute();
        
        // Convert is_active to boolean
        $row['is_active'] = (int)$row['is_active'] === 1;
        
        return $row;
        
    } catch (Exception $e) {
        error_log("API token verification error: " . $e->getMessage());
        return null;
    }
}

/**
 * Create a new API token
 * 
 * @param string $name Token name/description
 * @param string|null $expiresAt Expiration date (Y-m-d H:i:s format) or null for no expiration
 * @return array Returns ['token' => plain_token, 'id' => token_id, 'name' => name, 'created_at' => ...]
 */
function createApiToken(string $name, ?string $expiresAt = null): array {
    // Generate secure random token (64 hex chars = 32 bytes)
    $plainToken = bin2hex(random_bytes(32));
    $tokenHash = hash('sha256', $plainToken);
    
    try {
        $dbPath = getDatabasePath();
        $db = new SQLite3($dbPath);
        $db->enableExceptions(true);
        
        // Ensure table exists (might not exist before migration)
        $tableExists = $db->querySingle("SELECT name FROM sqlite_master WHERE type='table' AND name='api_tokens'");
        if (!$tableExists) {
            // Create table if it doesn't exist (for backward compatibility)
            $db->exec('CREATE TABLE IF NOT EXISTS api_tokens (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                token TEXT NOT NULL UNIQUE,
                name TEXT NOT NULL,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                last_used_at DATETIME,
                expires_at DATETIME,
                is_active INTEGER DEFAULT 1
            )');
            $db->exec('CREATE INDEX IF NOT EXISTS idx_api_tokens_token ON api_tokens(token)');
            $db->exec('CREATE INDEX IF NOT EXISTS idx_api_tokens_active ON api_tokens(is_active)');
        }
        
        // Insert token
        $stmt = $db->prepare('INSERT INTO api_tokens (token, name, expires_at) VALUES (:token, :name, :expires_at)');
        $stmt->bindValue(':token', $tokenHash, SQLITE3_TEXT);
        $stmt->bindValue(':name', $name, SQLITE3_TEXT);
        $stmt->bindValue(':expires_at', $expiresAt, $expiresAt ? SQLITE3_TEXT : SQLITE3_NULL);
        $stmt->execute();
        
        $tokenId = $db->lastInsertRowID();
        
        // Get created token info
        $infoStmt = $db->prepare('SELECT created_at FROM api_tokens WHERE id = :id');
        $infoStmt->bindValue(':id', $tokenId, SQLITE3_INTEGER);
        $infoResult = $infoStmt->execute();
        $infoRow = $infoResult->fetchArray(SQLITE3_ASSOC);
        
        return [
            'token' => $plainToken, // Return plain token only once
            'id' => $tokenId,
            'name' => $name,
            'created_at' => $infoRow['created_at'] ?? date('Y-m-d H:i:s'),
            'expires_at' => $expiresAt
        ];
        
    } catch (Exception $e) {
        error_log("API token creation error: " . $e->getMessage());
        throw new Exception("Failed to create API token: " . $e->getMessage());
    }
}

/**
 * Revoke (deactivate) an API token
 * 
 * @param int $tokenId Token ID to revoke
 * @return bool True on success, false on failure
 */
function revokeApiToken(int $tokenId): bool {
    try {
        $dbPath = getDatabasePath();
        $db = new SQLite3($dbPath);
        $db->enableExceptions(true);
        
        $stmt = $db->prepare('UPDATE api_tokens SET is_active = 0 WHERE id = :id');
        $stmt->bindValue(':id', $tokenId, SQLITE3_INTEGER);
        $stmt->execute();
        
        return true;
        
    } catch (Exception $e) {
        error_log("API token revocation error: " . $e->getMessage());
        return false;
    }
}

/**
 * List all API tokens
 * 
 * @return array Array of token info (without plain tokens)
 */
function listApiTokens(): array {
    try {
        $dbPath = getDatabasePath();
        $db = new SQLite3($dbPath);
        $db->enableExceptions(true);
        
        // Check if table exists
        $tableExists = $db->querySingle("SELECT name FROM sqlite_master WHERE type='table' AND name='api_tokens'");
        if (!$tableExists) {
            return [];
        }
        
        $stmt = $db->prepare('SELECT id, name, created_at, last_used_at, expires_at, is_active 
                              FROM api_tokens 
                              ORDER BY created_at DESC');
        $result = $stmt->execute();
        
        $tokens = [];
        while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
            // Convert is_active to boolean
            $row['is_active'] = (int)$row['is_active'] === 1;
            
            // Mask token (show first 8 chars of hash for identification)
            // We don't store plain tokens, so we can't show the actual token
            // Instead, we'll show a masked version based on the hash
            $row['token_mask'] = substr($row['name'], 0, 8) . '...';
            
            $tokens[] = $row;
        }
        
        return $tokens;
        
    } catch (Exception $e) {
        error_log("API token listing error: " . $e->getMessage());
        return [];
    }
}

/**
 * Get API token by ID
 * 
 * @param int $tokenId Token ID
 * @return array|null Token info or null if not found
 */
function getApiTokenById(int $tokenId): ?array {
    try {
        $dbPath = getDatabasePath();
        $db = new SQLite3($dbPath);
        $db->enableExceptions(true);
        
        $stmt = $db->prepare('SELECT id, name, created_at, last_used_at, expires_at, is_active 
                              FROM api_tokens 
                              WHERE id = :id');
        $stmt->bindValue(':id', $tokenId, SQLITE3_INTEGER);
        $result = $stmt->execute();
        $row = $result->fetchArray(SQLITE3_ASSOC);
        
        if (!$row) {
            return null;
        }
        
        $row['is_active'] = (int)$row['is_active'] === 1;
        return $row;
        
    } catch (Exception $e) {
        error_log("API token get error: " . $e->getMessage());
        return null;
    }
}
