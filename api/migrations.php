<?php
/**
 * Migrations API Endpoint
 * Handles database migration operations
 */

require_once __DIR__ . '/../includes/api_helpers.php';
require_once __DIR__ . '/../includes/migration_runner.php';

// Initialize API (admin required for execution)
$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? '';

if ($method === 'POST' && $action === 'run') {
    // Require admin for running migrations
    initApiEndpoint(true, true);
} else {
    // List and status can be viewed by all authenticated users
    initApiEndpoint(true, false);
}

$dbPath = getDatabasePath();
$db = new SQLite3($dbPath);
$db->enableExceptions(true);
$db->exec('PRAGMA foreign_keys = ON');

// Route requests
if ($method === 'GET' && $action === 'list') {
    handleGetMigrationsList($db);
} elseif ($method === 'GET' && $action === 'status') {
    handleGetMigrationsStatus($db);
} elseif ($method === 'POST' && $action === 'run') {
    handleRunMigration($db);
} else {
    sendErrorResponse('Invalid endpoint. Use ?action=list|status|run', 'INVALID_ENDPOINT', 404);
}

/**
 * Get list of migrations with status
 */
function handleGetMigrationsList($db) {
    $files = getMigrationFiles();
    $executed = getExecutedMigrations($db);
    
    $migrations = [];
    foreach ($files as $file) {
        $isExecuted = in_array($file['name'], $executed);
        
        // Get execution info if executed
        $executionInfo = null;
        if ($isExecuted) {
            $stmt = $db->prepare('SELECT executed_at, executed_by, execution_time_ms FROM schema_migrations WHERE migration_name = :name');
            $stmt->bindValue(':name', $file['name'], SQLITE3_TEXT);
            $result = $stmt->execute();
            $executionInfo = $result->fetchArray(SQLITE3_ASSOC);
        }
        
        $migrations[] = [
            'number' => $file['number'],
            'name' => $file['name'],
            'filename' => $file['filename'],
            'executed' => $isExecuted,
            'execution_info' => $executionInfo
        ];
    }
    
    sendSuccessResponse(['migrations' => $migrations]);
}

/**
 * Get migration status (pending count)
 */
function handleGetMigrationsStatus($db) {
    $pending = getPendingMigrations($db);
    $hasPending = count($pending) > 0;
    
    sendSuccessResponse([
        'has_pending' => $hasPending,
        'pending_count' => count($pending),
        'pending_migrations' => array_map(function($m) {
            return ['number' => $m['number'], 'name' => $m['name']];
        }, $pending)
    ]);
}

/**
 * Run a migration
 */
function handleRunMigration($db) {
    verifyApiCsrf();
    $data = getJsonRequest();
    
    $migrationName = $data['migration_name'] ?? '';
    
    if (empty($migrationName)) {
        sendErrorResponse('Migration name is required', 'VALIDATION_ERROR', 400);
    }
    
    // Validate migration name (prevent path traversal)
    if (preg_match('/[^a-zA-Z0-9_]/', $migrationName)) {
        sendErrorResponse('Invalid migration name', 'VALIDATION_ERROR', 400);
    }
    
    // Find migration file
    $files = getMigrationFiles();
    $migrationFile = null;
    foreach ($files as $file) {
        if ($file['name'] === $migrationName) {
            $migrationFile = $file;
            break;
        }
    }
    
    if (!$migrationFile) {
        sendErrorResponse('Migration not found', 'MIGRATION_NOT_FOUND', 404);
    }
    
    // Get current user (for logging)
    $executedBy = isset($_SESSION['username']) ? $_SESSION['username'] : 'unknown';
    
    // Run migration
    $result = runMigration($db, $migrationFile['path'], $migrationName, $executedBy);
    
    if ($result['success']) {
        sendSuccessResponse([
            'migration_name' => $migrationName,
            'execution_time_ms' => $result['execution_time_ms']
        ], 'Migration erfolgreich ausgeführt');
    } else {
        sendErrorResponse($result['error'], 'MIGRATION_ERROR', 500);
    }
}

