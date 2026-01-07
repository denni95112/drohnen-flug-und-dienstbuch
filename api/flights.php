<?php
/**
 * Flights API Endpoint
 * Handles all flight-related operations
 */

require_once __DIR__ . '/../includes/api_helpers.php';

// Initialize API
initApiEndpoint(true, false);

$dbPath = getDatabasePath();
$db = new SQLite3($dbPath);
$db->enableExceptions(true);
$db->exec('PRAGMA foreign_keys = ON');

// Get request method and action
$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? '';

// Route requests
if ($method === 'POST' && $action === 'start') {
    handleStartFlight($db);
} elseif ($method === 'POST' && $action === 'end') {
    handleEndFlight($db);
} elseif ($method === 'POST' && $action === 'create') {
    handleCreateFlight($db);
} elseif ($method === 'DELETE' && isset($_GET['id'])) {
    handleDeleteFlight($db, intval($_GET['id']));
} elseif ($method === 'GET' && $action === 'dashboard') {
    handleGetDashboard($db);
} elseif ($method === 'GET' && $action === 'list') {
    handleGetFlightsList($db);
} else {
    sendErrorResponse('Invalid endpoint. Use ?action=start|end|create|dashboard|list or ?id= for DELETE', 'INVALID_ENDPOINT', 404);
}

/**
 * Start a flight (from dashboard)
 */
function handleStartFlight($db) {
    verifyApiCsrf();
    $data = getJsonRequest();
    
    $pilotId = intval($data['pilot_id'] ?? 0);
    $droneId = intval($data['drone_id'] ?? 0);
    $locationId = intval($data['location_id'] ?? 0);
    $batteryNumber = intval($data['battery_number'] ?? 0);
    $requestId = $data['request_id'] ?? '';
    
    // Check for duplicate request
    checkDuplicateRequest($db, $requestId, 'start_flight');
    
    // Validation
    if ($pilotId <= 0) {
        sendErrorResponse('Invalid pilot ID', 'VALIDATION_ERROR', 400);
    }
    if ($droneId <= 0) {
        sendErrorResponse('Invalid drone ID', 'VALIDATION_ERROR', 400);
    }
    if ($batteryNumber <= 0) {
        sendErrorResponse('Bitte geben Sie eine gültige Batterienummer ein.', 'VALIDATION_ERROR', 400);
    }
    
    try {
        // Begin transaction
        $db->exec('BEGIN TRANSACTION');
        
        // Check if pilot already has ongoing flight
        $stmt = $db->prepare("SELECT id FROM flights WHERE pilot_id = :pilot_id AND flight_end_date IS NULL");
        $stmt->bindValue(':pilot_id', $pilotId, SQLITE3_INTEGER);
        $result = $stmt->execute();
        $ongoing = $result->fetchArray(SQLITE3_ASSOC);
        
        if ($ongoing) {
            $db->exec('ROLLBACK');
            sendErrorResponse('Pilot hat bereits einen laufenden Flug.', 'FLIGHT_ALREADY_STARTED', 409);
        }
        
        // Insert new flight
        $flightDate = getCurrentUTC();
        $stmt = $db->prepare("INSERT INTO flights (pilot_id, flight_date, flight_location_id, drone_id, battery_number) VALUES (:pilot_id, :flight_date, :location_id, :drone_id, :battery_number)");
        $stmt->bindValue(':pilot_id', $pilotId, SQLITE3_INTEGER);
        $stmt->bindValue(':flight_date', $flightDate, SQLITE3_TEXT);
        $stmt->bindValue(':location_id', $locationId > 0 ? $locationId : null, $locationId > 0 ? SQLITE3_INTEGER : SQLITE3_NULL);
        $stmt->bindValue(':drone_id', $droneId, SQLITE3_INTEGER);
        $stmt->bindValue(':battery_number', $batteryNumber, SQLITE3_INTEGER);
        
        if (!$stmt->execute()) {
            $db->exec('ROLLBACK');
            sendErrorResponse('Fehler beim Starten des Flugs.', 'DATABASE_ERROR', 500);
        }
        
        $flightId = $db->lastInsertRowID();
        
        // Commit transaction
        $db->exec('COMMIT');
        
        // Log request
        $response = ['success' => true, 'message' => 'Flug erfolgreich gestartet', 'data' => ['flight_id' => $flightId]];
        logRequest($db, $requestId, 'start_flight', $response, $pilotId, $flightId);
        
        sendSuccessResponse(['flight_id' => $flightId], 'Flug erfolgreich gestartet');
        
    } catch (Exception $e) {
        $db->exec('ROLLBACK');
        logError("Flight start error: " . $e->getMessage(), [
            'pilot_id' => $data['pilot_id'] ?? null,
            'drone_id' => $data['drone_id'] ?? null,
            'location_id' => $data['location_id'] ?? null,
            'file' => $e->getFile(),
            'line' => $e->getLine()
        ]);
        sendErrorResponse('Fehler beim Starten des Flugs.', 'DATABASE_ERROR', 500);
    }
}

/**
 * End a flight (from dashboard)
 */
function handleEndFlight($db) {
    verifyApiCsrf();
    $data = getJsonRequest();
    
    $flightId = intval($data['flight_id'] ?? 0);
    $requestId = $data['request_id'] ?? '';
    
    // Check for duplicate request
    checkDuplicateRequest($db, $requestId, 'end_flight');
    
    if ($flightId <= 0) {
        sendErrorResponse('Invalid flight ID', 'VALIDATION_ERROR', 400);
    }
    
    try {
        // Begin transaction
        $db->exec('BEGIN TRANSACTION');
        
        // Check if flight exists and is still ongoing (optimistic lock)
        $stmt = $db->prepare("SELECT id, pilot_id, flight_end_date FROM flights WHERE id = :flight_id");
        $stmt->bindValue(':flight_id', $flightId, SQLITE3_INTEGER);
        $result = $stmt->execute();
        $flight = $result->fetchArray(SQLITE3_ASSOC);
        
        if (!$flight) {
            $db->exec('ROLLBACK');
            sendErrorResponse('Flug nicht gefunden.', 'FLIGHT_NOT_FOUND', 404);
        }
        
        if ($flight['flight_end_date'] !== null) {
            $db->exec('ROLLBACK');
            sendErrorResponse('Flug wurde bereits beendet.', 'FLIGHT_ALREADY_ENDED', 409);
        }
        
        // Update flight with end date
        $flightEndDate = getCurrentUTC();
        $stmt = $db->prepare("UPDATE flights SET flight_end_date = :flight_end_date WHERE id = :flight_id AND flight_end_date IS NULL");
        $stmt->bindValue(':flight_end_date', $flightEndDate, SQLITE3_TEXT);
        $stmt->bindValue(':flight_id', $flightId, SQLITE3_INTEGER);
        
        if (!$stmt->execute()) {
            $db->exec('ROLLBACK');
            sendErrorResponse('Fehler beim Beenden des Flugs.', 'DATABASE_ERROR', 500);
        }
        
        // Check if update actually happened (another user might have ended it)
        if ($db->changes() === 0) {
            $db->exec('ROLLBACK');
            sendErrorResponse('Flug wurde bereits beendet.', 'FLIGHT_ALREADY_ENDED', 409);
        }
        
        // Commit transaction
        $db->exec('COMMIT');
        
        // Log request
        $response = ['success' => true, 'message' => 'Flug erfolgreich beendet'];
        logRequest($db, $requestId, 'end_flight', $response, $flight['pilot_id'], $flightId);
        
        sendSuccessResponse(null, 'Flug erfolgreich beendet');
        
    } catch (Exception $e) {
        $db->exec('ROLLBACK');
        logError("Flight end error: " . $e->getMessage(), [
            'flight_id' => $data['flight_id'] ?? null,
            'file' => $e->getFile(),
            'line' => $e->getLine()
        ]);
        sendErrorResponse('Fehler beim Beenden des Flugs.', 'DATABASE_ERROR', 500);
    }
}

/**
 * Create a flight with dates (from add_flight.php)
 */
function handleCreateFlight($db) {
    verifyApiCsrf();
    $data = getJsonRequest();
    
    $pilotId = intval($data['pilot_id'] ?? 0);
    $flightDate = $data['flight_date'] ?? '';
    $flightEndDate = $data['flight_end_date'] ?? '';
    $droneId = intval($data['drone_id'] ?? 0);
    $locationId = isset($data['location_id']) ? intval($data['location_id']) : null;
    $batteryNumber = intval($data['battery_number'] ?? 0);
    $requestId = $data['request_id'] ?? '';
    
    // Check for duplicate request
    checkDuplicateRequest($db, $requestId, 'create_flight');
    
    // Validation
    if ($pilotId <= 0) {
        sendErrorResponse('Invalid pilot ID', 'VALIDATION_ERROR', 400);
    }
    if (empty($flightDate) || empty($flightEndDate)) {
        sendErrorResponse('Start- und Enddatum sind erforderlich.', 'VALIDATION_ERROR', 400);
    }
    if ($droneId <= 0) {
        sendErrorResponse('Invalid drone ID', 'VALIDATION_ERROR', 400);
    }
    if ($batteryNumber <= 0) {
        sendErrorResponse('Bitte geben Sie eine gültige Batterienummer ein.', 'VALIDATION_ERROR', 400);
    }
    
    // Validate dates
    $startTimestamp = strtotime($flightDate);
    $endTimestamp = strtotime($flightEndDate);
    if ($endTimestamp <= $startTimestamp) {
        sendErrorResponse('Das Enddatum muss nach dem Startdatum liegen.', 'VALIDATION_ERROR', 400);
    }
    
    try {
        // Convert to UTC
        $flightDateUTC = toUTC($flightDate);
        $flightEndDateUTC = toUTC($flightEndDate);
        
        // Insert flight
        $stmt = $db->prepare("INSERT INTO flights (pilot_id, flight_date, flight_end_date, flight_location_id, drone_id, battery_number) VALUES (:pilot_id, :flight_date, :flight_end_date, :location_id, :drone_id, :battery_number)");
        $stmt->bindValue(':pilot_id', $pilotId, SQLITE3_INTEGER);
        $stmt->bindValue(':flight_date', $flightDateUTC, SQLITE3_TEXT);
        $stmt->bindValue(':flight_end_date', $flightEndDateUTC, SQLITE3_TEXT);
        $stmt->bindValue(':location_id', $locationId, $locationId ? SQLITE3_INTEGER : SQLITE3_NULL);
        $stmt->bindValue(':drone_id', $droneId, SQLITE3_INTEGER);
        $stmt->bindValue(':battery_number', $batteryNumber, SQLITE3_INTEGER);
        
        if (!$stmt->execute()) {
            sendErrorResponse('Fehler beim Eintragen des Flugs.', 'DATABASE_ERROR', 500);
        }
        
        $flightId = $db->lastInsertRowID();
        
        // Update pilot's last_flight
        $stmt = $db->prepare("UPDATE pilots SET last_flight = :flight_date WHERE id = :pilot_id");
        $stmt->bindValue(':flight_date', $flightDateUTC, SQLITE3_TEXT);
        $stmt->bindValue(':pilot_id', $pilotId, SQLITE3_INTEGER);
        $stmt->execute();
        
        // Log request
        $response = ['success' => true, 'message' => 'Flug erfolgreich eingetragen', 'data' => ['flight_id' => $flightId]];
        logRequest($db, $requestId, 'create_flight', $response, $pilotId, $flightId);
        
        sendSuccessResponse(['flight_id' => $flightId], 'Flug erfolgreich eingetragen');
        
    } catch (Exception $e) {
        logError("Flight create error: " . $e->getMessage(), [
            'pilot_id' => $data['pilot_id'] ?? null,
            'flight_date' => $data['flight_date'] ?? null,
            'file' => $e->getFile(),
            'line' => $e->getLine()
        ]);
        sendErrorResponse('Fehler beim Eintragen des Flugs.', 'DATABASE_ERROR', 500);
    }
}

/**
 * Delete a flight
 */
function handleDeleteFlight($db, $flightId) {
    verifyApiCsrf();
    
    if ($flightId <= 0) {
        sendErrorResponse('Invalid flight ID', 'VALIDATION_ERROR', 400);
    }
    
    try {
        $stmt = $db->prepare("DELETE FROM flights WHERE id = :flight_id");
        $stmt->bindValue(':flight_id', $flightId, SQLITE3_INTEGER);
        
        if (!$stmt->execute()) {
            sendErrorResponse('Fehler beim Löschen des Flugs.', 'DATABASE_ERROR', 500);
        }
        
        if ($db->changes() === 0) {
            sendErrorResponse('Flug nicht gefunden.', 'FLIGHT_NOT_FOUND', 404);
        }
        
        sendSuccessResponse(null, 'Flug erfolgreich gelöscht');
        
    } catch (Exception $e) {
        logError("Flight delete error: " . $e->getMessage(), [
            'flight_id' => $flightId ?? null,
            'file' => $e->getFile(),
            'line' => $e->getLine()
        ]);
        sendErrorResponse('Fehler beim Löschen des Flugs.', 'DATABASE_ERROR', 500);
    }
}

/**
 * Get dashboard data
 */
function handleGetDashboard($db) {
    require_once __DIR__ . '/../includes/dashboard_helpers.php';
    
    // Calculate cutoff date for flight counts
    $cutoffDate = new DateTime('now', new DateTimeZone('UTC'));
    $cutoffDate->modify('-3 months');
    $cutoffDateUTC = $cutoffDate->format('Y-m-d H:i:s');
    
    // Optimized query: Get all pilot data with ongoing flights and flight counts in single query
    $query = "
        SELECT 
            p.*,
            of.id as ongoing_flight_id,
            of.flight_date as ongoing_flight_date,
            COUNT(DISTINCT CASE WHEN f.flight_date >= :cutoff_date THEN f.id END) as flight_count
        FROM pilots p
        LEFT JOIN flights of ON p.id = of.pilot_id AND of.flight_end_date IS NULL
        LEFT JOIN flights f ON p.id = f.pilot_id
        GROUP BY p.id
        ORDER BY p.name
    ";
    
    $stmt = $db->prepare($query);
    $stmt->bindValue(':cutoff_date', $cutoffDateUTC, SQLITE3_TEXT);
    $pilotsResult = $stmt->execute();
    
    $pilots = [];
    while ($row = $pilotsResult->fetchArray(SQLITE3_ASSOC)) {
        $pilotId = $row['id'];
        
        // Prepare ongoing flight data
        $ongoingFlight = null;
        if ($row['ongoing_flight_id']) {
            $ongoingFlight = [
                'id' => $row['ongoing_flight_id'],
                'flight_date' => $row['ongoing_flight_date']
            ];
        }
        
        $nextFlightDue = getNextDueDate($db, $pilotId);
        $flightTime = getPilotFlightTime($db, $pilotId);
        
        $pilots[] = [
            'id' => $row['id'],
            'name' => $row['name'],
            'flight_count' => $flightTime,
            'has_enough_flights' => $row['flight_count'] >= 3,
            'next_flight_due' => $nextFlightDue,
            'ongoing_flight' => $ongoingFlight,
            'required_minutes' => $row['minutes_of_flights_needed']
        ];
    }
    
    // Get locations for today
    $currentDateUTC = getCurrentUTC();
    $currentDateLocal = toLocalTime($currentDateUTC, 'Y-m-d');
    $currentDateUTCStart = toUTC($currentDateLocal . ' 00:00:00');
    $currentDateUTCEnd = toUTC($currentDateLocal . ' 23:59:59');
    
    $locations = [];
    $tableExists = $db->querySingle("SELECT name FROM sqlite_master WHERE type='table' AND name='flight_locations'");
    if ($tableExists) {
        $stmt = $db->prepare("SELECT id, location_name FROM flight_locations WHERE created_at >= :start_date AND created_at <= :end_date");
        $stmt->bindValue(':start_date', $currentDateUTCStart, SQLITE3_TEXT);
        $stmt->bindValue(':end_date', $currentDateUTCEnd, SQLITE3_TEXT);
        $locationsResult = $stmt->execute();
        while ($loc = $locationsResult->fetchArray(SQLITE3_ASSOC)) {
            $locations[] = $loc;
        }
    }
    
    // Get drones
    $drones = [];
    $dronesTableExists = $db->querySingle("SELECT name FROM sqlite_master WHERE type='table' AND name='drones'");
    if ($dronesTableExists) {
        $stmt = $db->prepare("SELECT id, drone_name FROM drones ORDER BY id");
        $dronesResult = $stmt->execute();
        while ($drone = $dronesResult->fetchArray(SQLITE3_ASSOC)) {
            $drones[] = $drone;
        }
    }
    
    sendSuccessResponse([
        'pilots' => $pilots,
        'locations' => $locations,
        'drones' => $drones
    ]);
}

/**
 * Get flights list
 */
function handleGetFlightsList($db) {
    $cutoffDate = new DateTime('now', new DateTimeZone('UTC'));
    $cutoffDate->modify('-3 months');
    $cutoffDateUTC = $cutoffDate->format('Y-m-d H:i:s');
    
    $flights = [];
    $stmt = $db->prepare("SELECT flights.id as flight_id, pilots.name as pilot_name, flights.flight_date FROM flights JOIN pilots ON flights.pilot_id = pilots.id WHERE flights.flight_date >= :cutoff_date ORDER BY flights.flight_date DESC");
    $stmt->bindValue(':cutoff_date', $cutoffDateUTC, SQLITE3_TEXT);
    $result = $stmt->execute();
    
    while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
        $flights[] = [
            'id' => $row['flight_id'],
            'pilot_name' => $row['pilot_name'],
            'flight_date' => toLocalTime($row['flight_date'])
        ];
    }
    
    sendSuccessResponse(['flights' => $flights]);
}

