<?php
/**
 * Documents API Endpoint
 * Handles all document-related operations including file uploads, downloads, and deletions
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
    handleGetDocumentsList($db);
} elseif ($method === 'POST' && $action === 'upload') {
    handleUploadDocument($db);
} elseif ($method === 'DELETE' && isset($_GET['id'])) {
    handleDeleteDocument($db, intval($_GET['id']));
} else {
    sendErrorResponse('Invalid endpoint. Use ?action=list|upload or ?id=X for DELETE', 'INVALID_ENDPOINT', 404);
}

/**
 * Get list of documents
 */
function handleGetDocumentsList($db) {
    $stmt = $db->prepare("SELECT * FROM documents ORDER BY uploaded_at DESC");
    $result = $stmt->execute();
    $documents = [];
    
    require_once __DIR__ . '/../includes/utils.php';
    
    while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
        // Convert UTC datetime to local time for display
        if (isset($row['uploaded_at'])) {
            $row['uploaded_at'] = toLocalTime($row['uploaded_at']);
        }
        $documents[] = $row;
    }
    
    sendSuccessResponse(['documents' => $documents]);
}

/**
 * Handle document upload
 * Note: File uploads need to use multipart/form-data, not JSON
 */
function handleUploadDocument($db) {
    verifyApiCsrf();
    
    // Check if user is admin
    require_once __DIR__ . '/../includes/auth.php';
    if (!isAdmin()) {
        sendErrorResponse('Nur Administratoren können Dokumente hochladen.', 'PERMISSION_DENIED', 403);
    }
    
    if (!isset($_FILES['document_file'])) {
        sendErrorResponse('Datei ist erforderlich', 'VALIDATION_ERROR', 400);
    }
    
    $file = $_FILES['document_file'];
    
    // Validate file upload
    if ($file['error'] !== UPLOAD_ERR_OK) {
        sendErrorResponse("Fehler beim Hochladen der Datei (Error Code: " . $file['error'] . ").", 'UPLOAD_ERROR', 400);
    }
    
    // File size limit: 10MB
    $maxSize = 10 * 1024 * 1024;
    if ($file['size'] > $maxSize) {
        sendErrorResponse("Die Datei ist zu groß. Maximale Größe: 10MB.", 'VALIDATION_ERROR', 400);
    }
    
    // Validate file type - only PDFs allowed
    $allowedTypes = ['application/pdf'];
    
    $mimeType = null;
    if (extension_loaded('fileinfo')) {
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mimeType = finfo_file($finfo, $file['tmp_name']);
        finfo_close($finfo);
    } else {
        $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if ($extension === 'pdf') {
            $mimeType = 'application/pdf';
        }
    }
    
    if (!$mimeType || !in_array($mimeType, $allowedTypes)) {
        sendErrorResponse("Nur PDF-Dateien sind erlaubt.", 'VALIDATION_ERROR', 400);
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
        $uploadDir = __DIR__ . '/../uploads/documents/';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }
        
        // Save encrypted file
        $uniqueId = bin2hex(random_bytes(8));
        $encryptedFilePath = 'uploads/documents/' . $uniqueId . '_' . $safeName . '.enc';
        $fullPath = __DIR__ . '/../' . $encryptedFilePath;
        
        // Store IV with encrypted file
        file_put_contents($fullPath, $ivHex . ':' . $encryptedContent);
        
        // Get description if provided
        $description = isset($_POST['description']) ? trim($_POST['description']) : null;
        
        // Insert into database
        $stmt = $db->prepare('INSERT INTO documents (filename, original_filename, file_path, file_size, description) VALUES (:filename, :original_filename, :file_path, :file_size, :description)');
        $stmt->bindValue(':filename', $safeName, SQLITE3_TEXT);
        $stmt->bindValue(':original_filename', $originalName, SQLITE3_TEXT);
        $stmt->bindValue(':file_path', $encryptedFilePath, SQLITE3_TEXT);
        $stmt->bindValue(':file_size', $file['size'], SQLITE3_INTEGER);
        $stmt->bindValue(':description', $description, SQLITE3_TEXT);
        $stmt->execute();
        
        sendSuccessResponse(null, 'Dokument erfolgreich hochgeladen und verschlüsselt');
        
    } catch (Exception $e) {
        logError("Document upload error: " . $e->getMessage(), [
            'file_name' => $_FILES['document_file']['name'] ?? null,
            'file' => $e->getFile(),
            'line' => $e->getLine()
        ]);
        sendErrorResponse('Fehler beim Hochladen des Dokuments.', 'UPLOAD_ERROR', 500);
    }
}

/**
 * Delete a document
 */
function handleDeleteDocument($db, $documentId) {
    verifyApiCsrf();
    
    // Check if user is admin
    require_once __DIR__ . '/../includes/auth.php';
    if (!isAdmin()) {
        sendErrorResponse('Nur Administratoren können Dokumente löschen.', 'PERMISSION_DENIED', 403);
    }
    
    try {
        // Get document info
        $stmt = $db->prepare('SELECT file_path FROM documents WHERE id = :id');
        $stmt->bindValue(':id', $documentId, SQLITE3_INTEGER);
        $result = $stmt->execute()->fetchArray(SQLITE3_ASSOC);
        
        if (!$result) {
            sendErrorResponse('Dokument nicht gefunden.', 'NOT_FOUND', 404);
        }
        
        // Delete file
        $filePath = __DIR__ . '/../' . $result['file_path'];
        if (file_exists($filePath)) {
            unlink($filePath);
        }
        
        // Delete from database
        $stmt = $db->prepare('DELETE FROM documents WHERE id = :id');
        $stmt->bindValue(':id', $documentId, SQLITE3_INTEGER);
        $stmt->execute();
        
        sendSuccessResponse(null, 'Dokument erfolgreich gelöscht');
        
    } catch (Exception $e) {
        logError("Document delete error: " . $e->getMessage(), [
            'document_id' => $documentId,
            'file' => $e->getFile(),
            'line' => $e->getLine()
        ]);
        sendErrorResponse('Fehler beim Löschen des Dokuments.', 'DATABASE_ERROR', 500);
    }
}
