<?php
/**
 * Drones API Endpoint
 * Handles all drone-related operations
 */

require_once __DIR__ . '/../includes/api_helpers.php';

// Initialize API
initApiEndpoint(true, false);

$dbPath = getDatabasePath();
$db = new SQLite3($dbPath);
$db->exec('PRAGMA foreign_keys = ON');

// Get request method and action
$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? '';

// Route requests
if ($method === 'GET' && $action === 'list') {
    handleGetDronesList($db);
} elseif ($method === 'POST' && $action === 'create') {
    handleCreateDrone($db);
} elseif ($method === 'DELETE' && isset($_GET['id'])) {
    handleDeleteDrone($db, intval($_GET['id']));
} else {
    sendErrorResponse('Invalid endpoint. Use ?action=list|create or ?id=X for DELETE', 'INVALID_ENDPOINT', 404);
}

/**
 * Get list of all drones
 */
function handleGetDronesList($db) {
    $drones = [];
    $stmt = $db->prepare('SELECT * FROM drones ORDER BY drone_name ASC');
    $result = $stmt->execute();
    
    while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
        $drones[] = $row;
    }
    
    sendSuccessResponse(['drones' => $drones]);
}

/**
 * Create a new drone
 */
function handleCreateDrone($db) {
    verifyApiCsrf();
    $data = getJsonRequest();
    
    $droneName = trim($data['drone_name'] ?? '');
    $requestId = $data['request_id'] ?? '';
    
    // Check for duplicate request
    checkDuplicateRequest($db, $requestId, 'create_drone');
    
    if (empty($droneName)) {
        sendErrorResponse('Bitte geben Sie einen Namen für die Drohne ein.', 'VALIDATION_ERROR', 400);
    }
    
    try {
        $stmt = $db->prepare('INSERT INTO drones (drone_name) VALUES (:drone_name)');
        $stmt->bindValue(':drone_name', $droneName, SQLITE3_TEXT);
        
        if (!$stmt->execute()) {
            sendErrorResponse('Fehler beim Hinzufügen der Drohne.', 'DATABASE_ERROR', 500);
        }
        
        $droneId = $db->lastInsertRowID();
        
        // Log request
        $response = ['success' => true, 'message' => 'Drohne erfolgreich hinzugefügt', 'data' => ['drone_id' => $droneId]];
        logRequest($db, $requestId, 'create_drone', $response);
        
        sendSuccessResponse(['drone_id' => $droneId], 'Drohne erfolgreich hinzugefügt');
        
    } catch (Exception $e) {
        error_log("Drone create error: " . $e->getMessage());
        sendErrorResponse('Fehler beim Hinzufügen der Drohne.', 'DATABASE_ERROR', 500);
    }
}

/**
 * Delete a drone
 */
function handleDeleteDrone($db, $droneId) {
    verifyApiCsrf();
    
    if ($droneId <= 0) {
        sendErrorResponse('Invalid drone ID', 'VALIDATION_ERROR', 400);
    }
    
    try {
        $stmt = $db->prepare('DELETE FROM drones WHERE id = :id');
        $stmt->bindValue(':id', $droneId, SQLITE3_INTEGER);
        
        if (!$stmt->execute()) {
            sendErrorResponse('Fehler beim Löschen der Drohne.', 'DATABASE_ERROR', 500);
        }
        
        if ($db->changes() === 0) {
            sendErrorResponse('Drohne nicht gefunden.', 'DRONE_NOT_FOUND', 404);
        }
        
        sendSuccessResponse(null, 'Drohne erfolgreich gelöscht');
        
    } catch (Exception $e) {
        error_log("Drone delete error: " . $e->getMessage());
        sendErrorResponse('Fehler beim Löschen der Drohne.', 'DATABASE_ERROR', 500);
    }
}

