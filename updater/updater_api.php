<?php
/**
 * Updater API endpoint
 * Handles AJAX requests for update operations
 */

// Start output buffering
ob_start();

require_once __DIR__ . '/../includes/error_reporting.php';

// Only allow POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit();
}

// Check if config exists
$configFile = __DIR__ . '/../config/config.php';
if (!file_exists($configFile)) {
    http_response_code(500);
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'error' => 'Configuration file not found']);
    exit();
}

// Start session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../includes/csrf.php';
require_once __DIR__ . '/../includes/rate_limit.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/utils.php';

$config = include $configFile;

if (isset($config['timezone'])) {
    date_default_timezone_set($config['timezone']);
}

// Clear any output
ob_clean();
header('Content-Type: application/json');

// Check admin authentication
if (!isAdmin()) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Admin access required']);
    exit();
}

// Verify CSRF token
$token = $_POST['csrf_token'] ?? '';
if (!isset($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $token)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'CSRF-Token-Validierung fehlgeschlagen. Bitte Seite neu laden.']);
    exit();
}

// Get action
$action = $_POST['action'] ?? '';

// Load updater class
require_once __DIR__ . '/updater.php';

try {
    $projectRoot = dirname(__DIR__);
    $updater = new Updater($projectRoot);
    
    switch ($action) {
        case 'check':
            // Check for updates
            if (checkRateLimit('updater_check', 1, 60)) {
                http_response_code(429);
                echo json_encode([
                    'success' => false,
                    'error' => 'Zu viele Update-Prüfungen. Bitte versuchen Sie es später erneut.'
                ]);
                exit();
            }
            
            $result = $updater->checkForUpdates();
            
            if ($result['error']) {
                echo json_encode([
                    'success' => false,
                    'error' => $result['error'],
                    'data' => $result
                ]);
            } else {
                echo json_encode([
                    'success' => true,
                    'data' => $result
                ]);
            }
            break;
            
        case 'update':
            // Perform update
            if (checkRateLimit('updater_update', 1, 300)) {
                http_response_code(429);
                echo json_encode([
                    'success' => false,
                    'error' => 'Zu viele Update-Versuche. Bitte versuchen Sie es später erneut.'
                ]);
                exit();
            }
            
            $version = $_POST['version'] ?? '';
            if (empty($version)) {
                http_response_code(400);
                echo json_encode([
                    'success' => false,
                    'error' => 'Version nicht angegeben'
                ]);
                exit();
            }
            
            // Validate version format
            $version = ltrim($version, 'v');
            if (!preg_match('/^\d+\.\d+\.\d+$/', $version)) {
                http_response_code(400);
                echo json_encode([
                    'success' => false,
                    'error' => 'Ungültiges Versionsformat'
                ]);
                exit();
            }
            
            // Perform update
            $result = $updater->performUpdate($version);
            
            if ($result['success']) {
                echo json_encode([
                    'success' => true,
                    'message' => $result['message'],
                    'files_updated' => $result['files_updated'],
                    'files_removed' => $result['files_removed'],
                    'backup_path' => $result['backup_path']
                ]);
            } else {
                http_response_code(500);
                echo json_encode([
                    'success' => false,
                    'error' => $result['error'] ?? 'Update fehlgeschlagen',
                    'rollback' => true
                ]);
            }
            break;
            
        default:
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'error' => 'Ungültige Aktion'
            ]);
            break;
    }
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Fehler: ' . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8')
    ]);
    
    // Log error
    if (function_exists('logError')) {
        logError('Updater API error: ' . $e->getMessage(), [
            'action' => $action,
            'file' => $e->getFile(),
            'line' => $e->getLine()
        ]);
    }
}

exit();
