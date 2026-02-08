<?php
/**
 * Admin API endpoint for AJAX requests
 * Handles admin login, logout, and API token management
 */

// Start output buffering to prevent any accidental output
ob_start();

require_once __DIR__ . '/../includes/error_reporting.php';

// Allow GET for list_tokens, POST for everything else
$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? $_POST['action'] ?? '';

if ($method === 'GET' && $action !== 'list_tokens') {
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
require_once __DIR__ . '/../includes/api_auth.php';

$config = include $configFile;

if (isset($config['timezone'])) {
    date_default_timezone_set($config['timezone']);
}

// Clear any output that might have been generated
ob_clean();
header('Content-Type: application/json; charset=utf-8');

// ---- API Token actions (require admin) ----
if ($action === 'list_tokens' && $method === 'GET') {
    if (!isAuthenticated() || !isAdmin()) {
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => 'Admin privileges required']);
        exit();
    }
    $tokens = listApiTokens();
    echo json_encode(['success' => true, 'data' => ['tokens' => $tokens]]);
    exit();
}

if ($action === 'create_token' && $method === 'POST') {
    if (!isAuthenticated() || !isAdmin()) {
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => 'Admin privileges required']);
        exit();
    }
    $token = $_POST['csrf_token'] ?? '';
    if (!isset($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $token)) {
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => 'CSRF-Token-Validierung fehlgeschlagen. Bitte Seite neu laden.']);
        exit();
    }
    $name = trim($_POST['token_name'] ?? '');
    if ($name === '') {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Token-Name ist erforderlich.']);
        exit();
    }
    $expiresAt = trim($_POST['expires_at'] ?? '');
    if ($expiresAt === '') {
        $expiresAt = null;
    } elseif (preg_match('/^\d{4}-\d{2}-\d{2}( \d{2}:\d{2}:\d{2})?$/', $expiresAt)) {
        if (strlen($expiresAt) === 10) {
            $expiresAt .= ' 23:59:59';
        }
    } else {
        $expiresAt = null;
    }
    try {
        $result = createApiToken($name, $expiresAt);
        echo json_encode(['success' => true, 'data' => $result]);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
    exit();
}

if ($action === 'revoke_token' && $method === 'POST') {
    if (!isAuthenticated() || !isAdmin()) {
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => 'Admin privileges required']);
        exit();
    }
    $token = $_POST['csrf_token'] ?? '';
    if (!isset($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $token)) {
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => 'CSRF-Token-Validierung fehlgeschlagen. Bitte Seite neu laden.']);
        exit();
    }
    $id = isset($_POST['id']) ? (int) $_POST['id'] : 0;
    if ($id <= 0) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Ungültige Token-ID.']);
        exit();
    }
    if (revokeApiToken($id)) {
        echo json_encode(['success' => true, 'message' => 'Token wurde widerrufen.']);
    } else {
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => 'Token konnte nicht widerrufen werden.']);
    }
    exit();
}

// ---- Legacy: admin login / logout (POST only, no action) ----
// Handle admin logout
if (isset($_POST['admin_logout'])) {
    $token = $_POST['csrf_token'] ?? '';
    if (!isset($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $token)) {
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => 'CSRF-Token-Validierung fehlgeschlagen. Bitte Seite neu laden.']);
        exit();
    }
    
    setAdminStatus(false);
    echo json_encode(['success' => true, 'message' => 'Erfolgreich zu normalem Benutzer zurückgewechselt!']);
    exit();
}

// Handle admin login
if (isset($_POST['admin_password'])) {
    $token = $_POST['csrf_token'] ?? '';
    if (!isset($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $token)) {
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => 'CSRF-Token-Validierung fehlgeschlagen. Bitte Seite neu laden.']);
        exit();
    }
    
    if (checkRateLimit('admin_login', 3, 1800)) {
        http_response_code(429);
        echo json_encode(['success' => false, 'error' => 'Zu viele fehlgeschlagene Admin-Anmeldeversuche. Bitte versuchen Sie es später erneut.']);
        exit();
    }
    
    $admin_password = $_POST['admin_password'] ?? '';
    
    $passwordValid = false;
    
    if (password_verify($admin_password, $config['admin_hash'])) {
        $passwordValid = true;
    } elseif (hash('sha256', $admin_password) === $config['admin_hash']) {
        $passwordValid = true;
    }
    
    if ($passwordValid) {
        clearRateLimit('admin_login');
        session_regenerate_id(true);
        setAdminStatus(true);
        
        echo json_encode([
            'success' => true, 
            'message' => 'Erfolgreich als Admin angemeldet!'
        ]);
        exit();
    } else {
        recordFailedAttempt('admin_login');
        sleep(1);
        http_response_code(401);
        echo json_encode(['success' => false, 'error' => 'Falsches Admin-Passwort!']);
        exit();
    }
}

// If we get here, the request was invalid
http_response_code(400);
echo json_encode(['success' => false, 'error' => 'Invalid request']);
exit();

