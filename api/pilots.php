<?php
/**
 * Pilots API Endpoint
 * Handles all pilot-related operations
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
    handleGetPilotsList($db);
} elseif ($method === 'POST' && $action === 'create') {
    handleCreatePilot($db);
} elseif ($method === 'DELETE' && isset($_GET['id'])) {
    handleDeletePilot($db, intval($_GET['id']));
} elseif ($method === 'PUT' && isset($_GET['id']) && $action === 'minutes') {
    handleUpdatePilotMinutes($db, intval($_GET['id']));
} else {
    sendErrorResponse('Invalid endpoint. Use ?action=list|create or ?id=X&action=minutes for PUT/DELETE', 'INVALID_ENDPOINT', 404);
}

/**
 * Get list of all pilots
 */
function handleGetPilotsList($db) {
    $pilots = [];
    $stmt = $db->prepare('SELECT * FROM pilots ORDER BY name');
    $result = $stmt->execute();
    
    while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
        $pilots[] = $row;
    }
    
    sendSuccessResponse(['pilots' => $pilots]);
}

/**
 * Create a new pilot
 */
function handleCreatePilot($db) {
    verifyApiCsrf();
    $data = getJsonRequest();
    
    $name = trim($data['name'] ?? '');
    $requestId = $data['request_id'] ?? '';
    
    // Check for duplicate request
    checkDuplicateRequest($db, $requestId, 'create_pilot');
    
    if (empty($name)) {
        sendErrorResponse('Der Name darf nicht leer sein.', 'VALIDATION_ERROR', 400);
    }
    
    try {
        $stmt = $db->prepare('INSERT INTO pilots (name) VALUES (:name)');
        $stmt->bindValue(':name', $name, SQLITE3_TEXT);
        
        if (!$stmt->execute()) {
            sendErrorResponse('Fehler beim Hinzufügen des Piloten.', 'DATABASE_ERROR', 500);
        }
        
        $pilotId = $db->lastInsertRowID();
        
        // Log request
        $response = ['success' => true, 'message' => 'Pilot erfolgreich hinzugefügt', 'data' => ['pilot_id' => $pilotId]];
        logRequest($db, $requestId, 'create_pilot', $response);
        
        sendSuccessResponse(['pilot_id' => $pilotId], 'Pilot erfolgreich hinzugefügt');
        
    } catch (Exception $e) {
        error_log("Pilot create error: " . $e->getMessage());
        sendErrorResponse('Fehler beim Hinzufügen des Piloten.', 'DATABASE_ERROR', 500);
    }
}

/**
 * Delete a pilot
 */
function handleDeletePilot($db, $pilotId) {
    verifyApiCsrf();
    
    if ($pilotId <= 0) {
        sendErrorResponse('Invalid pilot ID', 'VALIDATION_ERROR', 400);
    }
    
    try {
        $stmt = $db->prepare('DELETE FROM pilots WHERE id = :id');
        $stmt->bindValue(':id', $pilotId, SQLITE3_INTEGER);
        
        if (!$stmt->execute()) {
            sendErrorResponse('Fehler beim Löschen des Piloten.', 'DATABASE_ERROR', 500);
        }
        
        if ($db->changes() === 0) {
            sendErrorResponse('Pilot nicht gefunden.', 'PILOT_NOT_FOUND', 404);
        }
        
        sendSuccessResponse(null, 'Pilot erfolgreich gelöscht');
        
    } catch (Exception $e) {
        error_log("Pilot delete error: " . $e->getMessage());
        sendErrorResponse('Fehler beim Löschen des Piloten.', 'DATABASE_ERROR', 500);
    }
}

/**
 * Update pilot's required minutes
 */
function handleUpdatePilotMinutes($db, $pilotId) {
    verifyApiCsrf();
    $data = getJsonRequest();
    
    if ($pilotId <= 0) {
        sendErrorResponse('Invalid pilot ID', 'VALIDATION_ERROR', 400);
    }
    
    $minutes = max(1, intval($data['minutes_of_flights_needed'] ?? 0));
    
    if ($minutes <= 0) {
        sendErrorResponse('Anzahl der benötigten Flugminuten muss mindestens 1 sein.', 'VALIDATION_ERROR', 400);
    }
    
    try {
        $stmt = $db->prepare('UPDATE pilots SET minutes_of_flights_needed = :minutes WHERE id = :id');
        $stmt->bindValue(':minutes', $minutes, SQLITE3_INTEGER);
        $stmt->bindValue(':id', $pilotId, SQLITE3_INTEGER);
        
        if (!$stmt->execute()) {
            sendErrorResponse('Fehler beim Aktualisieren des Piloten.', 'DATABASE_ERROR', 500);
        }
        
        if ($db->changes() === 0) {
            sendErrorResponse('Pilot nicht gefunden.', 'PILOT_NOT_FOUND', 404);
        }
        
        sendSuccessResponse(null, 'Anzahl der benötigten Flugminuten erfolgreich aktualisiert');
        
    } catch (Exception $e) {
        error_log("Pilot update error: " . $e->getMessage());
        sendErrorResponse('Fehler beim Aktualisieren des Piloten.', 'DATABASE_ERROR', 500);
    }
}

