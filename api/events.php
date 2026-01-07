<?php
/**
 * Events API Endpoint
 * Handles all event-related operations
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
    handleGetEventsList($db);
} elseif ($method === 'POST' && $action === 'create') {
    handleCreateEvent($db);
} elseif ($method === 'DELETE' && isset($_GET['id'])) {
    handleDeleteEvent($db, intval($_GET['id']));
} else {
    sendErrorResponse('Invalid endpoint. Use ?action=list|create or ?id=X for DELETE', 'INVALID_ENDPOINT', 404);
}

/**
 * Get list of events
 */
function handleGetEventsList($db) {
    $year = isset($_GET['year']) ? intval($_GET['year']) : date('Y');
    
    // Get events for the year
    $startDate = "$year-01-01 00:00:00";
    $endDate = "$year-12-31 23:59:59";
    
    require_once __DIR__ . '/../includes/utils.php';
    $startDateUTC = toUTC($startDate);
    $endDateUTC = toUTC($endDate);
    
    $events = [];
    $stmt = $db->prepare("
        SELECT 
            e.id,
            e.event_start_date,
            e.event_end_date,
            e.type_id,
            e.notes,
            GROUP_CONCAT(p.id) as pilot_ids,
            GROUP_CONCAT(p.name) as pilot_names
        FROM events e
        LEFT JOIN pilot_events pe ON e.id = pe.event_id
        LEFT JOIN pilots p ON pe.pilot_id = p.id
        WHERE e.event_start_date >= :start_date AND e.event_start_date <= :end_date
        GROUP BY e.id
        ORDER BY e.event_start_date DESC
    ");
    $stmt->bindValue(':start_date', $startDateUTC, SQLITE3_TEXT);
    $stmt->bindValue(':end_date', $endDateUTC, SQLITE3_TEXT);
    $result = $stmt->execute();
    
    while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
        $events[] = [
            'id' => $row['id'],
            'event_start_date' => toLocalTime($row['event_start_date']),
            'event_end_date' => toLocalTime($row['event_end_date']),
            'type_id' => $row['type_id'],
            'notes' => $row['notes'],
            'pilot_ids' => $row['pilot_ids'] ? explode(',', $row['pilot_ids']) : [],
            'pilot_names' => $row['pilot_names'] ? explode(',', $row['pilot_names']) : []
        ];
    }
    
    sendSuccessResponse(['events' => $events]);
}

/**
 * Create a new event with pilots
 */
function handleCreateEvent($db) {
    verifyApiCsrf();
    $data = getJsonRequest();
    
    $eventStartDate = $data['event_start_date'] ?? '';
    $eventEndDate = $data['event_end_date'] ?? '';
    $typeId = intval($data['type_id'] ?? 0);
    $notes = trim($data['notes'] ?? '');
    $pilotIds = $data['pilot_ids'] ?? [];
    $requestId = $data['request_id'] ?? '';
    
    // Check for duplicate request
    checkDuplicateRequest($db, $requestId, 'create_event');
    
    // Validation
    if (empty($eventStartDate) || empty($eventEndDate) || empty($typeId) || empty($notes)) {
        sendErrorResponse('Bitte füllen Sie alle erforderlichen Felder aus.', 'VALIDATION_ERROR', 400);
    }
    
    if (!in_array($typeId, [1, 2, 3])) {
        sendErrorResponse('Ungültiger Ereignistyp.', 'VALIDATION_ERROR', 400);
    }
    
    if (strtotime($eventEndDate) <= strtotime($eventStartDate)) {
        sendErrorResponse('Das Enddatum muss nach dem Startdatum liegen.', 'VALIDATION_ERROR', 400);
    }
    
    if (empty($pilotIds) || !is_array($pilotIds)) {
        sendErrorResponse('Bitte mindestens einen Piloten auswählen.', 'VALIDATION_ERROR', 400);
    }
    
    try {
        require_once __DIR__ . '/../includes/utils.php';
        
        // Convert to UTC
        $eventStartDateUTC = toUTC($eventStartDate);
        $eventEndDateUTC = toUTC($eventEndDate);
        
        // Begin transaction
        $db->exec('BEGIN TRANSACTION');
        
        // Insert event
        $stmt = $db->prepare('INSERT INTO events (event_start_date, event_end_date, type_id, notes) VALUES (:event_start_date, :event_end_date, :type_id, :notes)');
        $stmt->bindValue(':event_start_date', $eventStartDateUTC, SQLITE3_TEXT);
        $stmt->bindValue(':event_end_date', $eventEndDateUTC, SQLITE3_TEXT);
        $stmt->bindValue(':type_id', $typeId, SQLITE3_INTEGER);
        $stmt->bindValue(':notes', $notes, SQLITE3_TEXT);
        
        if (!$stmt->execute()) {
            $db->exec('ROLLBACK');
            sendErrorResponse('Fehler beim Erstellen des Ereignisses.', 'DATABASE_ERROR', 500);
        }
        
        $eventId = $db->lastInsertRowID();
        
        // Insert pilot associations
        foreach ($pilotIds as $pilotId) {
            $pilotIdInt = intval($pilotId);
            if ($pilotIdInt > 0) {
                $stmt = $db->prepare('INSERT INTO pilot_events (event_id, pilot_id) VALUES (:event_id, :pilot_id)');
                $stmt->bindValue(':event_id', $eventId, SQLITE3_INTEGER);
                $stmt->bindValue(':pilot_id', $pilotIdInt, SQLITE3_INTEGER);
                $stmt->execute();
            }
        }
        
        // Commit transaction
        $db->exec('COMMIT');
        
        // Log request
        $response = ['success' => true, 'message' => 'Ereignis erfolgreich erstellt', 'data' => ['event_id' => $eventId]];
        logRequest($db, $requestId, 'create_event', $response);
        
        sendSuccessResponse(['event_id' => $eventId], 'Das Ereignis wurde erfolgreich hinzugefügt.');
        
    } catch (Exception $e) {
        $db->exec('ROLLBACK');
        error_log("Event create error: " . $e->getMessage());
        sendErrorResponse('Fehler beim Erstellen des Ereignisses.', 'DATABASE_ERROR', 500);
    }
}

/**
 * Delete an event
 */
function handleDeleteEvent($db, $eventId) {
    verifyApiCsrf();
    
    if ($eventId <= 0) {
        sendErrorResponse('Invalid event ID', 'VALIDATION_ERROR', 400);
    }
    
    try {
        // Delete event (cascade will delete pilot_events)
        $stmt = $db->prepare('DELETE FROM events WHERE id = :id');
        $stmt->bindValue(':id', $eventId, SQLITE3_INTEGER);
        
        if (!$stmt->execute()) {
            sendErrorResponse('Fehler beim Löschen des Ereignisses.', 'DATABASE_ERROR', 500);
        }
        
        if ($db->changes() === 0) {
            sendErrorResponse('Ereignis nicht gefunden.', 'EVENT_NOT_FOUND', 404);
        }
        
        sendSuccessResponse(null, 'Ereignis erfolgreich gelöscht');
        
    } catch (Exception $e) {
        error_log("Event delete error: " . $e->getMessage());
        sendErrorResponse('Fehler beim Löschen des Ereignisses.', 'DATABASE_ERROR', 500);
    }
}

