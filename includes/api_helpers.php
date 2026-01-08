<?php
/**
 * API Helper Functions
 * Shared utilities for API endpoints
 */

require_once __DIR__ . '/error_reporting.php';
require_once __DIR__ . '/security_headers.php';
require_once __DIR__ . '/csrf.php';
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/utils.php';

/**
 * Send JSON response
 */
function sendJsonResponse($data, $statusCode = 200) {
    http_response_code($statusCode);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    exit;
}

/**
 * Send success response
 */
function sendSuccessResponse($data = null, $message = null) {
    $response = ['success' => true];
    if ($data !== null) {
        $response['data'] = $data;
    }
    if ($message !== null) {
        $response['message'] = $message;
    }
    sendJsonResponse($response);
}

/**
 * Send error response
 */
function sendErrorResponse($error, $errorCode = null, $statusCode = 400) {
    $response = ['success' => false, 'error' => $error];
    if ($errorCode !== null) {
        $response['error_code'] = $errorCode;
    }
    sendJsonResponse($response, $statusCode);
}

/**
 * Require authentication for API endpoint
 */
function requireApiAuth() {
    if (!isAuthenticated()) {
        sendErrorResponse('Authentication required', 'AUTHENTICATION_ERROR', 401);
    }
}

/**
 * Require admin privileges for API endpoint
 */
function requireApiAdmin() {
    requireApiAuth();
    if (!isAdmin()) {
        sendErrorResponse('Admin privileges required', 'AUTHORIZATION_ERROR', 403);
    }
}

/**
 * Verify CSRF token from JSON request
 */
function verifyApiCsrf() {
    $input = json_decode(file_get_contents('php://input'), true);
    $token = $input['csrf_token'] ?? $_POST['csrf_token'] ?? '';
    
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    
    if (!isset($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $token)) {
        sendErrorResponse('CSRF token validation failed', 'CSRF_ERROR', 403);
    }
}

/**
 * Get JSON request data
 */
function getJsonRequest() {
    $input = file_get_contents('php://input');
    $data = json_decode($input, true);
    
    if (json_last_error() !== JSON_ERROR_NONE) {
        sendErrorResponse('Invalid JSON in request body', 'VALIDATION_ERROR', 400);
    }
    
    return $data;
}

/**
 * Check and handle duplicate requests
 */
function checkDuplicateRequest($db, $requestId, $action) {
    if (empty($requestId)) {
        return null;
    }
    
    // Check if request_log table exists (might not exist before migrations)
    $tableExists = $db->querySingle("SELECT name FROM sqlite_master WHERE type='table' AND name='request_log'");
    if (!$tableExists) {
        return null; // Table doesn't exist yet, skip deduplication
    }
    
    // Check if request was already processed
    $stmt = $db->prepare('SELECT response_data FROM request_log WHERE request_id = :request_id AND expires_at > datetime("now")');
    $stmt->bindValue(':request_id', $requestId, SQLITE3_TEXT);
    $result = $stmt->execute();
    $row = $result->fetchArray(SQLITE3_ASSOC);
    
    if ($row) {
        // Return cached response
        $cachedResponse = json_decode($row['response_data'], true);
        if ($cachedResponse) {
            sendJsonResponse($cachedResponse);
        }
    }
    
    return null;
}

/**
 * Log processed request
 */
function logRequest($db, $requestId, $action, $response, $pilotId = null, $flightId = null) {
    if (empty($requestId)) {
        return;
    }
    
    // Check if request_log table exists (might not exist before migrations)
    $tableExists = $db->querySingle("SELECT name FROM sqlite_master WHERE type='table' AND name='request_log'");
    if (!$tableExists) {
        return; // Table doesn't exist yet, skip logging
    }
    
    // Request IDs expire after 5 minutes
    $expiresAt = new DateTime('now', new DateTimeZone('UTC'));
    $expiresAt->modify('+5 minutes');
    
    $stmt = $db->prepare('INSERT OR REPLACE INTO request_log (request_id, action, pilot_id, flight_id, expires_at, response_data) VALUES (:request_id, :action, :pilot_id, :flight_id, :expires_at, :response_data)');
    $stmt->bindValue(':request_id', $requestId, SQLITE3_TEXT);
    $stmt->bindValue(':action', $action, SQLITE3_TEXT);
    $stmt->bindValue(':pilot_id', $pilotId, SQLITE3_INTEGER);
    $stmt->bindValue(':flight_id', $flightId, SQLITE3_INTEGER);
    $stmt->bindValue(':expires_at', $expiresAt->format('Y-m-d H:i:s'), SQLITE3_TEXT);
    $stmt->bindValue(':response_data', json_encode($response), SQLITE3_TEXT);
    $stmt->execute();
}

/**
 * Clean up expired request logs
 * Should be called periodically
 */
function cleanupExpiredRequestLogs($db) {
    $db->exec('DELETE FROM request_log WHERE expires_at < datetime("now")');
}

/**
 * Initialize API endpoint
 * Sets up error handling, security headers, authentication
 */
function initApiEndpoint($requireAuth = true, $requireAdmin = false) {
    ob_start(); // Start output buffering
    
    // Set security headers
    require_once __DIR__ . '/security_headers.php';
    
    // Start session if needed
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    
    // Require authentication
    if ($requireAuth) {
        requireApiAuth();
    }
    
    // Require admin
    if ($requireAdmin) {
        requireApiAdmin();
    }
    
    // Clear any output
    ob_clean();
    
    // Set JSON header
    header('Content-Type: application/json; charset=utf-8');
}

