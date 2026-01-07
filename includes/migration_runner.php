<?php
/**
 * Migration Runner
 * 
 * Handles execution of database migrations
 */

require_once __DIR__ . '/utils.php';

/**
 * Ensure schema_migrations table exists
 * This is called before any migration operations
 */
function ensureSchemaMigrationsTable($db) {
    $tableExists = $db->querySingle("SELECT name FROM sqlite_master WHERE type='table' AND name='schema_migrations'");
    
    if (!$tableExists) {
        $db->exec('CREATE TABLE IF NOT EXISTS schema_migrations (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            migration_name TEXT NOT NULL UNIQUE,
            executed_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            executed_by TEXT,
            execution_time_ms INTEGER
        )');
        
        $db->exec('CREATE INDEX IF NOT EXISTS idx_schema_migrations_name ON schema_migrations(migration_name)');
    }
}

/**
 * Get list of migration files
 * Returns array of migration files sorted by number (highest first)
 */
function getMigrationFiles() {
    $migrationsDir = __DIR__ . '/../migrations';
    $files = [];
    
    if (!is_dir($migrationsDir)) {
        return $files;
    }
    
    $items = scandir($migrationsDir);
    
    foreach ($items as $item) {
        if ($item === '.' || $item === '..') {
            continue;
        }
        
        $path = $migrationsDir . '/' . $item;
        if (is_file($path) && pathinfo($path, PATHINFO_EXTENSION) === 'php') {
            // Extract number from filename (e.g., "001_name.php" -> 1)
            if (preg_match('/^(\d+)_/', $item, $matches)) {
                $number = intval($matches[1]);
                $files[] = [
                    'number' => $number,
                    'filename' => $item,
                    'path' => $path,
                    'name' => pathinfo($item, PATHINFO_FILENAME)
                ];
            }
        }
    }
    
    // Sort by number descending (highest first)
    usort($files, function($a, $b) {
        return $b['number'] - $a['number'];
    });
    
    return $files;
}

/**
 * Get executed migrations from database
 */
function getExecutedMigrations($db) {
    ensureSchemaMigrationsTable($db);
    
    $stmt = $db->prepare('SELECT migration_name FROM schema_migrations');
    $result = $stmt->execute();
    
    $executed = [];
    while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
        $executed[] = $row['migration_name'];
    }
    
    return $executed;
}

/**
 * Check if migration has been executed
 */
function isMigrationExecuted($db, $migrationName) {
    ensureSchemaMigrationsTable($db);
    
    $stmt = $db->prepare('SELECT COUNT(*) FROM schema_migrations WHERE migration_name = :name');
    $stmt->bindValue(':name', $migrationName, SQLITE3_TEXT);
    $result = $stmt->execute();
    $count = $result->fetchArray(SQLITE3_NUM)[0];
    
    return $count > 0;
}

/**
 * Get pending migrations
 */
function getPendingMigrations($db) {
    $files = getMigrationFiles();
    $executed = getExecutedMigrations($db);
    
    $pending = [];
    foreach ($files as $file) {
        if (!in_array($file['name'], $executed)) {
            $pending[] = $file;
        }
    }
    
    return $pending;
}

/**
 * Execute a migration
 */
function runMigration($db, $migrationPath, $migrationName, $executedBy = null) {
    ensureSchemaMigrationsTable($db);
    
    // Check if already executed
    if (isMigrationExecuted($db, $migrationName)) {
        return ['success' => false, 'error' => 'Migration already executed'];
    }
    
    // Load migration file
    if (!file_exists($migrationPath)) {
        return ['success' => false, 'error' => 'Migration file not found'];
    }
    
    // Include migration file
    require_once $migrationPath;
    
    if (!function_exists('up')) {
        return ['success' => false, 'error' => 'Migration file does not define up() function'];
    }
    
    $startTime = microtime(true);
    
    try {
        // Begin transaction
        $db->exec('BEGIN TRANSACTION');
        
        // Execute migration
        $result = up($db);
        
        if ($result !== true) {
            throw new Exception('Migration up() function did not return true');
        }
        
        // Record migration
        $executionTime = round((microtime(true) - $startTime) * 1000);
        $stmt = $db->prepare('INSERT INTO schema_migrations (migration_name, executed_by, execution_time_ms) VALUES (:name, :by, :time)');
        $stmt->bindValue(':name', $migrationName, SQLITE3_TEXT);
        $stmt->bindValue(':by', $executedBy, SQLITE3_TEXT);
        $stmt->bindValue(':time', $executionTime, SQLITE3_INTEGER);
        $stmt->execute();
        
        // Commit transaction
        $db->exec('COMMIT');
        
        return ['success' => true, 'execution_time_ms' => $executionTime];
        
    } catch (Exception $e) {
        // Rollback on error
        $db->exec('ROLLBACK');
        return ['success' => false, 'error' => $e->getMessage()];
    }
}

/**
 * Check if there are pending migrations
 */
function hasPendingMigrations($db = null) {
    if ($db === null) {
        try {
            $dbPath = getDatabasePath();
            if (!file_exists($dbPath)) {
                return false;
            }
            $db = new SQLite3($dbPath);
        } catch (Exception $e) {
            return false;
        }
    }
    
    $pending = getPendingMigrations($db);
    return count($pending) > 0;
}

