<?php
/**
 * API endpoint for install notification
 * Handles sending notification to IFTTT webhook and updating config
 */

// Start output buffering to prevent any accidental output
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
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/utils.php';
require_once __DIR__ . '/../includes/version.php';

// Require authentication
if (!isAuthenticated()) {
    http_response_code(401);
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'error' => 'Not authenticated']);
    exit();
}

$config = include $configFile;

if (isset($config['timezone'])) {
    date_default_timezone_set($config['timezone']);
}

// Clear any output that might have been generated
ob_clean();
header('Content-Type: application/json');

// Handle install notification
if (isset($_POST['action'])) {
    $token = $_POST['csrf_token'] ?? '';
    if (!isset($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $token)) {
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => 'CSRF-Token-Validierung fehlgeschlagen. Bitte Seite neu laden.']);
        exit();
    }
    
    $action = $_POST['action'];
    
    if ($action === 'send') {
        // Send notification to IFTTT webhook
        $webhookUrl = 'https://maker.ifttt.com/trigger/Git_Repo_Install/json/with/key/NQCJaGe5GqJFuId-QeW4T7rWCnPf2B8JqJsn_xJtUX';
        
        $datetime = date('Y-m-d H:i:s');
        $organization = isset($_POST['organization']) ? trim($_POST['organization']) : '';
        
        // Build payload with new structure
        $payload = [
            'Datum' => $datetime,
            'app' => GITHUB_REPO_NAME,
            'organisation' => $organization
        ];
        
        $success = false;
        $error = null;
        
        if (function_exists('curl_init')) {
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $webhookUrl);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'Content-Type: application/json'
            ]);
            curl_setopt($ch, CURLOPT_TIMEOUT, 10);
            curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);
            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
            
            // Try with SSL verification first
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
            
            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $curlError = curl_error($ch);
            $curlErrno = curl_errno($ch);
            
            // If SSL verification failed, retry without verification (less secure but works)
            if ($response === false && ($curlErrno === CURLE_SSL_CACERT || $curlErrno === CURLE_SSL_PEER_CERTIFICATE || 
                strpos($curlError, 'SSL') !== false || strpos($curlError, 'certificate') !== false)) {
                // Retry without SSL verification
                curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
                curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
                
                $response = curl_exec($ch);
                $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                $curlError = curl_error($ch);
                $curlErrno = curl_errno($ch);
                
                // Log warning about disabled SSL verification
                error_log("Install notification: SSL verification disabled due to certificate issues");
            }
            
            curl_close($ch);
            
            if ($response === false) {
                // cURL execution failed
                if ($curlErrno === CURLE_OPERATION_TIMEOUTED || $curlErrno === CURLE_OPERATION_TIMEDOUT) {
                    $error = 'Zeitüberschreitung beim Senden der Anfrage. Bitte versuchen Sie es später erneut.';
                } elseif ($curlErrno === CURLE_COULDNT_CONNECT) {
                    $error = 'Verbindung zum Server konnte nicht hergestellt werden. Bitte überprüfen Sie Ihre Internetverbindung.';
                } elseif ($curlErrno === CURLE_SSL_CONNECT_ERROR) {
                    $error = 'SSL-Verbindungsfehler. Bitte versuchen Sie es später erneut.';
                } else {
                    $error = 'Netzwerkfehler: ' . ($curlError ?: 'Unbekannter Fehler (Code: ' . $curlErrno . ')');
                }
            } elseif ($httpCode === 0) {
                // HTTP code 0 usually means the request didn't complete
                $error = 'Die Anfrage konnte nicht abgeschlossen werden. Bitte überprüfen Sie Ihre Internetverbindung oder Firewall-Einstellungen.';
            } elseif ($httpCode === 200) {
                $success = true;
            } else {
                $error = "HTTP Fehler: {$httpCode}";
                if ($response) {
                    $error .= " - " . substr(strip_tags($response), 0, 100);
                }
            }
        } elseif (ini_get('allow_url_fopen')) {
            $context = stream_context_create([
                'http' => [
                    'method' => 'POST',
                    'header' => [
                        'Content-Type: application/json'
                    ],
                    'content' => json_encode($payload),
                    'timeout' => 10
                ]
            ]);
            
            $response = @file_get_contents($webhookUrl, false, $context);
            if ($response !== false) {
                $success = true;
            } else {
                $error = 'Fehler beim Senden der Anfrage';
            }
        } else {
            $error = 'cURL und allow_url_fopen sind nicht verfügbar';
        }
        
        // Update config to false regardless of success/failure
        updateConfig('ask_for_install_notification', false);
        
        if ($success) {
            echo json_encode(['success' => true, 'message' => 'Benachrichtigung erfolgreich gesendet.']);
        } else {
            echo json_encode(['success' => false, 'error' => $error ?? 'Fehler beim Senden der Benachrichtigung.']);
        }
        exit();
    } elseif ($action === 'dismiss') {
        // User clicked "No" - just update config to false
        updateConfig('ask_for_install_notification', false);
        echo json_encode(['success' => true, 'message' => 'Einstellung aktualisiert.']);
        exit();
    }
}

// If we get here, the request was invalid
http_response_code(400);
echo json_encode(['success' => false, 'error' => 'Invalid request']);
exit();

