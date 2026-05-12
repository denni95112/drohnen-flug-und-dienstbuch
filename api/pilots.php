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
$db->enableExceptions(true);
$db->exec('PRAGMA foreign_keys = ON');

// Get request method and action
$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? '';

// Route requests
if ($method === 'GET' && $action === 'list') {
    handleGetPilotsList($db);
} elseif ($method === 'POST' && $action === 'create') {
    handleCreatePilot($db);
} elseif ($method === 'POST' && $action === 'update' && isset($_GET['id'])) {
    handleUpdatePilot($db, intval($_GET['id']));
} elseif ($method === 'DELETE' && isset($_GET['id'])) {
    handleDeletePilot($db, intval($_GET['id']));
} elseif ($method === 'PUT' && isset($_GET['id']) && $action === 'minutes') {
    handleUpdatePilotMinutes($db, intval($_GET['id']));
} else {
    sendErrorResponse('Invalid endpoint. Use ?action=list|create|update or ?id=X&action=minutes for PUT/DELETE', 'INVALID_ENDPOINT', 404);
}

/**
 * Get pilots list. By default excludes disabled pilots; pass include_disabled=1 for manage UI.
 */
function handleGetPilotsList($db) {
    $pilots = [];
    $includeDisabled = isset($_GET['include_disabled']) && $_GET['include_disabled'] === '1';
    $sql = 'SELECT * FROM pilots';
    if (!$includeDisabled && pilotsHasDisabledColumn($db)) {
        $sql .= ' WHERE (disabled IS NULL OR disabled = 0)';
    }
    $sql .= ' ORDER BY name';
    $stmt = $db->prepare($sql);
    $result = $stmt->execute();
    $hasLockColumns = null;
    while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
        if ($hasLockColumns === null) {
            $hasLockColumns = array_key_exists('lock_on_invalid_license', $row);
        }
        $row['is_locked_license'] = $hasLockColumns ? computeIsLockedLicense($row) : false;
        $pilots[] = $row;
    }
    sendSuccessResponse(['pilots' => $pilots]);
}

/**
 * Compute whether a pilot is locked (no valid license) when lock_on_invalid_license is set.
 * When lock is on and no license (or no valid date) is provided, pilot is locked.
 */
function computeIsLockedLicense(array $row): bool {
    $lockOnInvalid = isset($row['lock_on_invalid_license']) && $row['lock_on_invalid_license'] == 1;
    if (!$lockOnInvalid) {
        return false;
    }
    $hasValidLicense = false;
    $currentDate = new DateTime('now', new DateTimeZone('UTC'));
    if (!empty($row['a1_a3_license_valid_until'])) {
        $validUntil = new DateTime($row['a1_a3_license_valid_until'], new DateTimeZone('UTC'));
        if ($validUntil >= $currentDate) {
            $hasValidLicense = true;
        }
    }
    if (!$hasValidLicense && !empty($row['a2_license_valid_until'])) {
        $validUntil = new DateTime($row['a2_license_valid_until'], new DateTimeZone('UTC'));
        if ($validUntil >= $currentDate) {
            $hasValidLicense = true;
        }
    }
    return !$hasValidLicense;
}

/**
 * Create a new pilot
 */
function handleCreatePilot($db) {
    verifyApiCsrf();
    $data = getJsonRequest();
    
    $name = trim($data['name'] ?? '');
    $requestId = $data['request_id'] ?? '';
    $minutes = max(1, intval($data['minutes_of_flights_needed'] ?? 45));
    $lockOnInvalid = isset($data['lock_on_invalid_license']) && $data['lock_on_invalid_license'] == '1';
    $disabled = !empty($data['disabled']) && ($data['disabled'] === '1' || $data['disabled'] === true || $data['disabled'] === 1);
    
    // License fields (optional)
    $a1A3LicenseId = !empty($data['a1_a3_license_id']) ? trim($data['a1_a3_license_id']) : null;
    $a1A3LicenseValidUntil = !empty($data['a1_a3_license_valid_until']) ? $data['a1_a3_license_valid_until'] : null;
    $a2LicenseId = !empty($data['a2_license_id']) ? trim($data['a2_license_id']) : null;
    $a2LicenseValidUntil = !empty($data['a2_license_valid_until']) ? $data['a2_license_valid_until'] : null;
    
    // Check for duplicate request
    checkDuplicateRequest($db, $requestId, 'create_pilot');
    
    if (empty($name)) {
        sendErrorResponse('Der Name darf nicht leer sein.', 'VALIDATION_ERROR', 400);
    }
    
    try {
        // Check if license columns exist (for backward compatibility)
        $result = $db->query("PRAGMA table_info(pilots)");
        $columns = [];
        while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
            $columns[] = $row['name'];
        }
        $result->finalize();
        
        $hasLicenseColumns = in_array('a1_a3_license_id', $columns);
        $hasLockColumn = in_array('lock_on_invalid_license', $columns);
        $hasDisabledColumn = in_array('disabled', $columns);
        
        if ($hasLicenseColumns && $hasLockColumn) {
            $disabledSql = $hasDisabledColumn ? ', disabled' : '';
            $disabledVal = $hasDisabledColumn ? ', :disabled' : '';
            $stmt = $db->prepare("INSERT INTO pilots (name, minutes_of_flights_needed, a1_a3_license_id, a1_a3_license_valid_until, a2_license_id, a2_license_valid_until, lock_on_invalid_license{$disabledSql}) 
                                   VALUES (:name, :minutes, :a1_a3_id, :a1_a3_valid_until, :a2_id, :a2_valid_until, :lock_on_invalid{$disabledVal})");
            $stmt->bindValue(':name', $name, SQLITE3_TEXT);
            $stmt->bindValue(':minutes', $minutes, SQLITE3_INTEGER);
            $stmt->bindValue(':a1_a3_id', $a1A3LicenseId, $a1A3LicenseId !== null ? SQLITE3_TEXT : SQLITE3_NULL);
            $stmt->bindValue(':a1_a3_valid_until', $a1A3LicenseValidUntil, $a1A3LicenseValidUntil !== null ? SQLITE3_TEXT : SQLITE3_NULL);
            $stmt->bindValue(':a2_id', $a2LicenseId, $a2LicenseId !== null ? SQLITE3_TEXT : SQLITE3_NULL);
            $stmt->bindValue(':a2_valid_until', $a2LicenseValidUntil, $a2LicenseValidUntil !== null ? SQLITE3_TEXT : SQLITE3_NULL);
            $stmt->bindValue(':lock_on_invalid', $lockOnInvalid ? 1 : 0, SQLITE3_INTEGER);
            if ($hasDisabledColumn) {
                $stmt->bindValue(':disabled', $disabled ? 1 : 0, SQLITE3_INTEGER);
            }
        } elseif ($hasLicenseColumns) {
            $disabledSql = $hasDisabledColumn ? ', disabled' : '';
            $disabledVal = $hasDisabledColumn ? ', :disabled' : '';
            $stmt = $db->prepare("INSERT INTO pilots (name, minutes_of_flights_needed, a1_a3_license_id, a1_a3_license_valid_until, a2_license_id, a2_license_valid_until{$disabledSql}) 
                                   VALUES (:name, :minutes, :a1_a3_id, :a1_a3_valid_until, :a2_id, :a2_valid_until{$disabledVal})");
            $stmt->bindValue(':name', $name, SQLITE3_TEXT);
            $stmt->bindValue(':minutes', $minutes, SQLITE3_INTEGER);
            $stmt->bindValue(':a1_a3_id', $a1A3LicenseId, $a1A3LicenseId !== null ? SQLITE3_TEXT : SQLITE3_NULL);
            $stmt->bindValue(':a1_a3_valid_until', $a1A3LicenseValidUntil, $a1A3LicenseValidUntil !== null ? SQLITE3_TEXT : SQLITE3_NULL);
            $stmt->bindValue(':a2_id', $a2LicenseId, $a2LicenseId !== null ? SQLITE3_TEXT : SQLITE3_NULL);
            $stmt->bindValue(':a2_valid_until', $a2LicenseValidUntil, $a2LicenseValidUntil !== null ? SQLITE3_TEXT : SQLITE3_NULL);
            if ($hasDisabledColumn) {
                $stmt->bindValue(':disabled', $disabled ? 1 : 0, SQLITE3_INTEGER);
            }
        } else {
            // Fallback for databases without license columns
            if ($hasDisabledColumn) {
                $stmt = $db->prepare('INSERT INTO pilots (name, minutes_of_flights_needed, disabled) VALUES (:name, :minutes, :disabled)');
                $stmt->bindValue(':name', $name, SQLITE3_TEXT);
                $stmt->bindValue(':minutes', $minutes, SQLITE3_INTEGER);
                $stmt->bindValue(':disabled', $disabled ? 1 : 0, SQLITE3_INTEGER);
            } else {
                $stmt = $db->prepare('INSERT INTO pilots (name, minutes_of_flights_needed) VALUES (:name, :minutes)');
                $stmt->bindValue(':name', $name, SQLITE3_TEXT);
                $stmt->bindValue(':minutes', $minutes, SQLITE3_INTEGER);
            }
        }
        
        if (!$stmt->execute()) {
            sendErrorResponse('Fehler beim Hinzufügen des Piloten.', 'DATABASE_ERROR', 500);
        }
        
        $pilotId = $db->lastInsertRowID();
        
        // Log request
        $response = ['success' => true, 'message' => 'Pilot erfolgreich hinzugefügt', 'data' => ['pilot_id' => $pilotId]];
        logRequest($db, $requestId, 'create_pilot', $response);
        
        sendSuccessResponse(['pilot_id' => $pilotId], 'Pilot erfolgreich hinzugefügt');
        
    } catch (Exception $e) {
        logError("Pilot create error: " . $e->getMessage(), [
            'pilot_name' => $data['name'] ?? null,
            'file' => $e->getFile(),
            'line' => $e->getLine()
        ]);
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
        logError("Pilot delete error: " . $e->getMessage(), [
            'pilot_id' => $pilotId ?? null,
            'file' => $e->getFile(),
            'line' => $e->getLine()
        ]);
        sendErrorResponse('Fehler beim Löschen des Piloten.', 'DATABASE_ERROR', 500);
    }
}

/**
 * Update a pilot (all fields)
 */
function handleUpdatePilot($db, $pilotId) {
    verifyApiCsrf();
    $data = getJsonRequest();
    
    if ($pilotId <= 0) {
        sendErrorResponse('Invalid pilot ID', 'VALIDATION_ERROR', 400);
    }
    
    $name = trim($data['name'] ?? '');
    $minutes = max(1, intval($data['minutes_of_flights_needed'] ?? 45));
    $lockOnInvalid = isset($data['lock_on_invalid_license']) && $data['lock_on_invalid_license'] == '1';
    $disabled = !empty($data['disabled']) && ($data['disabled'] === '1' || $data['disabled'] === true || $data['disabled'] === 1);
    
    // License fields (optional)
    $a1A3LicenseId = !empty($data['a1_a3_license_id']) ? trim($data['a1_a3_license_id']) : null;
    $a1A3LicenseValidUntil = !empty($data['a1_a3_license_valid_until']) ? $data['a1_a3_license_valid_until'] : null;
    $a2LicenseId = !empty($data['a2_license_id']) ? trim($data['a2_license_id']) : null;
    $a2LicenseValidUntil = !empty($data['a2_license_valid_until']) ? $data['a2_license_valid_until'] : null;
    
    if (empty($name)) {
        sendErrorResponse('Der Name darf nicht leer sein.', 'VALIDATION_ERROR', 400);
    }
    
    if ($minutes <= 0) {
        sendErrorResponse('Anzahl der benötigten Flugminuten muss mindestens 1 sein.', 'VALIDATION_ERROR', 400);
    }
    
    try {
        // Check if pilot exists
        $stmt = $db->prepare('SELECT id FROM pilots WHERE id = :id');
        $stmt->bindValue(':id', $pilotId, SQLITE3_INTEGER);
        $result = $stmt->execute()->fetchArray(SQLITE3_ASSOC);
        
        if (!$result) {
            sendErrorResponse('Pilot nicht gefunden.', 'PILOT_NOT_FOUND', 404);
        }
        
        // Check if license columns exist (for backward compatibility)
        $result = $db->query("PRAGMA table_info(pilots)");
        $columns = [];
        while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
            $columns[] = $row['name'];
        }
        $result->finalize();
        
        $hasLicenseColumns = in_array('a1_a3_license_id', $columns);
        $hasLockColumn = in_array('lock_on_invalid_license', $columns);
        $hasDisabledColumn = in_array('disabled', $columns);
        
        if ($hasLicenseColumns && $hasLockColumn) {
            $disabledSet = $hasDisabledColumn ? ', disabled = :disabled' : '';
            $stmt = $db->prepare("UPDATE pilots SET name = :name, minutes_of_flights_needed = :minutes, 
                                   a1_a3_license_id = :a1_a3_id, a1_a3_license_valid_until = :a1_a3_valid_until, 
                                   a2_license_id = :a2_id, a2_license_valid_until = :a2_valid_until,
                                   lock_on_invalid_license = :lock_on_invalid{$disabledSet}
                                   WHERE id = :id");
            $stmt->bindValue(':name', $name, SQLITE3_TEXT);
            $stmt->bindValue(':minutes', $minutes, SQLITE3_INTEGER);
            $stmt->bindValue(':a1_a3_id', $a1A3LicenseId, $a1A3LicenseId !== null ? SQLITE3_TEXT : SQLITE3_NULL);
            $stmt->bindValue(':a1_a3_valid_until', $a1A3LicenseValidUntil, $a1A3LicenseValidUntil !== null ? SQLITE3_TEXT : SQLITE3_NULL);
            $stmt->bindValue(':a2_id', $a2LicenseId, $a2LicenseId !== null ? SQLITE3_TEXT : SQLITE3_NULL);
            $stmt->bindValue(':a2_valid_until', $a2LicenseValidUntil, $a2LicenseValidUntil !== null ? SQLITE3_TEXT : SQLITE3_NULL);
            $stmt->bindValue(':lock_on_invalid', $lockOnInvalid ? 1 : 0, SQLITE3_INTEGER);
            $stmt->bindValue(':id', $pilotId, SQLITE3_INTEGER);
            if ($hasDisabledColumn) {
                $stmt->bindValue(':disabled', $disabled ? 1 : 0, SQLITE3_INTEGER);
            }
        } elseif ($hasLicenseColumns) {
            $disabledSet = $hasDisabledColumn ? ', disabled = :disabled' : '';
            $stmt = $db->prepare("UPDATE pilots SET name = :name, minutes_of_flights_needed = :minutes, 
                                   a1_a3_license_id = :a1_a3_id, a1_a3_license_valid_until = :a1_a3_valid_until, 
                                   a2_license_id = :a2_id, a2_license_valid_until = :a2_valid_until{$disabledSet}
                                   WHERE id = :id");
            $stmt->bindValue(':name', $name, SQLITE3_TEXT);
            $stmt->bindValue(':minutes', $minutes, SQLITE3_INTEGER);
            $stmt->bindValue(':a1_a3_id', $a1A3LicenseId, $a1A3LicenseId !== null ? SQLITE3_TEXT : SQLITE3_NULL);
            $stmt->bindValue(':a1_a3_valid_until', $a1A3LicenseValidUntil, $a1A3LicenseValidUntil !== null ? SQLITE3_TEXT : SQLITE3_NULL);
            $stmt->bindValue(':a2_id', $a2LicenseId, $a2LicenseId !== null ? SQLITE3_TEXT : SQLITE3_NULL);
            $stmt->bindValue(':a2_valid_until', $a2LicenseValidUntil, $a2LicenseValidUntil !== null ? SQLITE3_TEXT : SQLITE3_NULL);
            $stmt->bindValue(':id', $pilotId, SQLITE3_INTEGER);
            if ($hasDisabledColumn) {
                $stmt->bindValue(':disabled', $disabled ? 1 : 0, SQLITE3_INTEGER);
            }
        } else {
            // Fallback for databases without license columns
            if ($hasDisabledColumn) {
                $stmt = $db->prepare('UPDATE pilots SET name = :name, minutes_of_flights_needed = :minutes, disabled = :disabled WHERE id = :id');
                $stmt->bindValue(':name', $name, SQLITE3_TEXT);
                $stmt->bindValue(':minutes', $minutes, SQLITE3_INTEGER);
                $stmt->bindValue(':disabled', $disabled ? 1 : 0, SQLITE3_INTEGER);
                $stmt->bindValue(':id', $pilotId, SQLITE3_INTEGER);
            } else {
                $stmt = $db->prepare('UPDATE pilots SET name = :name, minutes_of_flights_needed = :minutes WHERE id = :id');
                $stmt->bindValue(':name', $name, SQLITE3_TEXT);
                $stmt->bindValue(':minutes', $minutes, SQLITE3_INTEGER);
                $stmt->bindValue(':id', $pilotId, SQLITE3_INTEGER);
            }
        }
        
        if (!$stmt->execute()) {
            sendErrorResponse('Fehler beim Aktualisieren des Piloten.', 'DATABASE_ERROR', 500);
        }
        
        if ($db->changes() === 0) {
            sendErrorResponse('Pilot nicht gefunden.', 'PILOT_NOT_FOUND', 404);
        }
        
        sendSuccessResponse(null, 'Pilot erfolgreich aktualisiert');
        
    } catch (Exception $e) {
        logError("Pilot update error: " . $e->getMessage(), [
            'pilot_id' => $pilotId ?? null,
            'file' => $e->getFile(),
            'line' => $e->getLine()
        ]);
        sendErrorResponse('Fehler beim Aktualisieren des Piloten.', 'DATABASE_ERROR', 500);
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
        logError("Pilot update error: " . $e->getMessage(), [
            'pilot_id' => $pilotId ?? null,
            'file' => $e->getFile(),
            'line' => $e->getLine()
        ]);
        sendErrorResponse('Fehler beim Aktualisieren des Piloten.', 'DATABASE_ERROR', 500);
    }
}

