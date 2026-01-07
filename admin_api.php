<?php
/**
 * Admin API endpoint for AJAX requests
 * Handles admin login and logout operations
 */

// Start output buffering to prevent any accidental output
ob_start();

require_once __DIR__ . '/includes/error_reporting.php';

// Only allow POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit();
}

// Check if config exists
$configFile = __DIR__ . '/config/config.php';
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

require_once __DIR__ . '/includes/csrf.php';
require_once __DIR__ . '/includes/rate_limit.php';
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/includes/utils.php';
require_once __DIR__ . '/config/config.php';

$config = include $configFile;

// For older installations: add ask_for_install_notification config option if it doesn't exist
if (!isset($config['ask_for_install_notification'])) {
    updateConfig('ask_for_install_notification', true);
    // Reload config after update
    $config = include $configFile;
}

if (isset($config['timezone'])) {
    date_default_timezone_set($config['timezone']);
}

// Clear any output that might have been generated
ob_clean();
header('Content-Type: application/json');

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
        
        // Set session flag to show install notification dialog after admin login
        if (isset($config['ask_for_install_notification']) && $config['ask_for_install_notification'] === true) {
            $_SESSION['show_install_notification'] = true;
        }
        
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

