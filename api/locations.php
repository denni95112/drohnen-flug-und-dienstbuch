<?php
/**
 * Locations API Endpoint
 * Handles all location-related operations including file uploads
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
    handleGetLocationsList($db);
} elseif ($method === 'POST' && $action === 'create') {
    handleCreateLocation($db);
} elseif ($method === 'POST' && $action === 'upload') {
    handleUploadFile($db);
} elseif ($method === 'DELETE' && isset($_GET['id'])) {
    handleDeleteLocation($db, intval($_GET['id']));
} else {
    sendErrorResponse('Invalid endpoint. Use ?action=list|create|upload or ?id=X for DELETE', 'INVALID_ENDPOINT', 404);
}

/**
 * Get list of locations (optionally filtered by date)
 */
function handleGetLocationsList($db) {
    $dateFilter = $_GET['date'] ?? null;
    
    if ($dateFilter) {
        // Filter by date (for dashboard - today's locations)
        $currentDateLocal = $dateFilter;
        require_once __DIR__ . '/../includes/utils.php';
        $currentDateUTCStart = toUTC($currentDateLocal . ' 00:00:00');
        $currentDateUTCEnd = toUTC($currentDateLocal . ' 23:59:59');
        
        $stmt = $db->prepare("SELECT id, location_name FROM flight_locations WHERE created_at >= :start_date AND created_at <= :end_date");
        $stmt->bindValue(':start_date', $currentDateUTCStart, SQLITE3_TEXT);
        $stmt->bindValue(':end_date', $currentDateUTCEnd, SQLITE3_TEXT);
    } else {
        // Get all locations
        $stmt = $db->prepare("SELECT * FROM flight_locations ORDER BY created_at DESC");
    }
    
    $result = $stmt->execute();
    $locations = [];
    
    require_once __DIR__ . '/../includes/utils.php';
    
    while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
        // Convert UTC datetime to local time for display
        if (isset($row['created_at'])) {
            $row['created_at'] = toLocalTime($row['created_at']);
        }
        // Convert training to boolean (SQLite3 returns integers, ensure proper conversion)
        if (isset($row['training'])) {
            $row['training'] = (int)$row['training'] !== 0;
        }
        $locations[] = $row;
    }
    
    sendSuccessResponse(['locations' => $locations]);
}

/**
 * Create a new location
 */
function handleCreateLocation($db) {
    verifyApiCsrf();
    $data = getJsonRequest();
    
    $locationName = trim($data['location_name'] ?? '');
    $latitude = isset($data['latitude']) ? floatval($data['latitude']) : null;
    $longitude = isset($data['longitude']) ? floatval($data['longitude']) : null;
    $description = isset($data['description']) ? trim($data['description']) : null;
    $training = isset($data['training']) ? (bool)$data['training'] : true;
    $requestId = $data['request_id'] ?? '';
    
    // Check for duplicate request
    checkDuplicateRequest($db, $requestId, 'create_location');
    
    // Validation
    require_once __DIR__ . '/../includes/utils.php';
    
    if (empty($locationName)) {
        sendErrorResponse('Bitte geben Sie einen Standortnamen ein.', 'VALIDATION_ERROR', 400);
    }
    
    $validLat = validateLatitude($latitude);
    $validLng = validateLongitude($longitude);
    
    if ($validLat === false || $validLng === false) {
        sendErrorResponse('Bitte geben Sie gültige Koordinaten ein.', 'VALIDATION_ERROR', 400);
    }
    
    try {
        $createdAt = getCurrentUTC();
        
        $stmt = $db->prepare('INSERT INTO flight_locations (location_name, latitude, longitude, description, training, created_at) VALUES (:location_name, :latitude, :longitude, :description, :training, :created_at)');
        $stmt->bindValue(':location_name', $locationName, SQLITE3_TEXT);
        $stmt->bindValue(':latitude', $validLat, SQLITE3_FLOAT);
        $stmt->bindValue(':longitude', $validLng, SQLITE3_FLOAT);
        $stmt->bindValue(':description', $description, SQLITE3_TEXT);
        $stmt->bindValue(':training', $training ? 1 : 0, SQLITE3_INTEGER);
        $stmt->bindValue(':created_at', $createdAt, SQLITE3_TEXT);
        
        if (!$stmt->execute()) {
            sendErrorResponse('Fehler beim Hinzufügen des Standorts.', 'DATABASE_ERROR', 500);
        }
        
        $locationId = $db->lastInsertRowID();
        
        // Log request
        $response = ['success' => true, 'message' => 'Standort erfolgreich hinzugefügt', 'data' => ['location_id' => $locationId]];
        logRequest($db, $requestId, 'create_location', $response);
        
        sendSuccessResponse(['location_id' => $locationId], 'Standort erfolgreich hinzugefügt');
        
    } catch (Exception $e) {
        logError("Location create error: " . $e->getMessage(), [
            'location_name' => $data['location_name'] ?? null,
            'file' => $e->getFile(),
            'line' => $e->getLine()
        ]);
        sendErrorResponse('Fehler beim Hinzufügen des Standorts.', 'DATABASE_ERROR', 500);
    }
}

/**
 * Handle file upload for location
 * Note: File uploads need to use multipart/form-data, not JSON
 */
function handleUploadFile($db) {
    verifyApiCsrf();
    
    if (!isset($_POST['location_id']) || !isset($_FILES['location_file'])) {
        sendErrorResponse('Location ID and file are required', 'VALIDATION_ERROR', 400);
    }
    
    $locationId = intval($_POST['location_id']);
    $file = $_FILES['location_file'];
    
    // Validate file upload
    if ($file['error'] !== UPLOAD_ERR_OK) {
        sendErrorResponse("Fehler beim Hochladen der Datei (Error Code: " . $file['error'] . ").", 'UPLOAD_ERROR', 400);
    }
    
    // File size limit: 10MB
    $maxSize = 10 * 1024 * 1024;
    if ($file['size'] > $maxSize) {
        sendErrorResponse("Die Datei ist zu groß. Maximale Größe: 10MB.", 'VALIDATION_ERROR', 400);
    }
    
    // Validate file type
    $allowedTypes = ['application/pdf', 'image/jpeg', 'image/png', 'image/gif', 'application/msword', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'];
    
    $mimeType = null;
    if (extension_loaded('fileinfo')) {
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mimeType = finfo_file($finfo, $file['tmp_name']);
        finfo_close($finfo);
    } else {
        $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        $extensionMap = [
            'pdf' => 'application/pdf',
            'jpg' => 'image/jpeg',
            'jpeg' => 'image/jpeg',
            'png' => 'image/png',
            'gif' => 'image/gif',
            'doc' => 'application/msword',
            'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'
        ];
        $mimeType = $extensionMap[$extension] ?? null;
    }
    
    if (!$mimeType || !in_array($mimeType, $allowedTypes)) {
        sendErrorResponse("Dateityp nicht erlaubt. Erlaubte Typen: PDF, Bilder (JPEG, PNG, GIF), Word-Dokumente.", 'VALIDATION_ERROR', 400);
    }
    
    try {
        require_once __DIR__ . '/../config/config.php';
        $config = include __DIR__ . '/../config/config.php';
        require_once __DIR__ . '/../includes/auth.php';
        
        // Sanitize filename
        $originalName = basename($file['name']);
        $safeName = preg_replace('/[^a-zA-Z0-9._-]/', '_', $originalName);
        $safeName = substr($safeName, 0, 255);
        
        // Read file content
        $fileContent = file_get_contents($file['tmp_name']);
        
        // Get encryption key
        $encryptionKey = getEncryptionKey();
        $encryptionMethod = $config['encryption']['method'];
        
        // Generate IV
        $iv = openssl_random_pseudo_bytes(openssl_cipher_iv_length($encryptionMethod));
        $ivHex = bin2hex($iv);
        
        // Encrypt content
        $encryptedContent = openssl_encrypt($fileContent, $encryptionMethod, $encryptionKey, 0, $iv);
        
        if ($encryptedContent === false) {
            sendErrorResponse('Fehler beim Verschlüsseln der Datei.', 'ENCRYPTION_ERROR', 500);
        }
        
        // Ensure uploads directory exists
        $uploadDir = __DIR__ . '/../uploads/';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }
        
        // Save encrypted file
        $uniqueId = bin2hex(random_bytes(8));
        $encryptedFilePath = 'uploads/' . $uniqueId . '_' . $safeName . '.enc';
        $fullPath = __DIR__ . '/../' . $encryptedFilePath;
        
        // Store IV with encrypted file
        file_put_contents($fullPath, $ivHex . ':' . $encryptedContent);
        
        // Update database
        $stmt = $db->prepare('UPDATE flight_locations SET file_path = :file_path WHERE id = :id');
        $stmt->bindValue(':file_path', $encryptedFilePath, SQLITE3_TEXT);
        $stmt->bindValue(':id', $locationId, SQLITE3_INTEGER);
        $stmt->execute();
        
        sendSuccessResponse(null, 'Datei erfolgreich hochgeladen und verschlüsselt');
        
    } catch (Exception $e) {
        logError("File upload error: " . $e->getMessage(), [
            'location_id' => $locationId ?? null,
            'file_name' => $_FILES['location_file']['name'] ?? null,
            'file' => $e->getFile(),
            'line' => $e->getLine()
        ]);
        sendErrorResponse('Fehler beim Hochladen der Datei.', 'UPLOAD_ERROR', 500);
    }
}

/**
 * Delete a location
 */
function handleDeleteLocation($db, $locationId) {
    verifyApiCsrf();
    
    if ($locationId <= 0) {
        sendErrorResponse('Invalid location ID', 'VALIDATION_ERROR', 400);
    }
    
    try {
        // Get file path if exists
        $stmt = $db->prepare('SELECT file_path FROM flight_locations WHERE id = :id');
        $stmt->bindValue(':id', $locationId, SQLITE3_INTEGER);
        $result = $stmt->execute();
        $location = $result->fetchArray(SQLITE3_ASSOC);
        
        // Delete location
        $stmt = $db->prepare('DELETE FROM flight_locations WHERE id = :id');
        $stmt->bindValue(':id', $locationId, SQLITE3_INTEGER);
        
        if (!$stmt->execute()) {
            sendErrorResponse('Fehler beim Löschen des Standorts.', 'DATABASE_ERROR', 500);
        }
        
        if ($db->changes() === 0) {
            sendErrorResponse('Standort nicht gefunden.', 'LOCATION_NOT_FOUND', 404);
        }
        
        // Delete associated file if exists
        if ($location && $location['file_path']) {
            $filePath = __DIR__ . '/../' . $location['file_path'];
            if (file_exists($filePath)) {
                @unlink($filePath);
            }
        }
        
        sendSuccessResponse(null, 'Standort erfolgreich gelöscht');
        
    } catch (Exception $e) {
        logError("Location delete error: " . $e->getMessage(), [
            'location_id' => $locationId ?? null,
            'file' => $e->getFile(),
            'line' => $e->getLine()
        ]);
        sendErrorResponse('Fehler beim Löschen des Standorts.', 'DATABASE_ERROR', 500);
    }
}

