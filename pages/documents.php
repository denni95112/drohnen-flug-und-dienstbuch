<?php
require_once __DIR__ . '/../includes/error_reporting.php';
require_once __DIR__ . '/../includes/security_headers.php';
require __DIR__ . '/../includes/auth.php';
requireAuth();

$config = include __DIR__ . '/../config/config.php';

// Set timezone from config
if (isset($config['timezone'])) {
    date_default_timezone_set($config['timezone']);
}

require_once __DIR__ . '/../includes/utils.php';
require_once __DIR__ . '/../includes/version.php';
require_once __DIR__ . '/../includes/auth.php';
$is_admin = isAdmin();
$dbPath = getDatabasePath();
$db = new SQLite3($dbPath);

// Handle download request - requires authentication
if (isset($_GET['download_file']) && isset($_GET['document_id'])) {
    $document_id = intval($_GET['document_id']);
    $stmt = $db->prepare('SELECT file_path, original_filename FROM documents WHERE id = :id');
    $stmt->bindValue(':id', $document_id, SQLITE3_INTEGER);
    $result = $stmt->execute()->fetchArray(SQLITE3_ASSOC);

    if ($result && $result['file_path']) {
        $filePath = dirname(__DIR__) . '/' . $result['file_path'];

        if (file_exists($filePath)) {
            // Read the encrypted file content
            $encryptedFileContent = file_get_contents($filePath);
            
            // Extract IV and encrypted content (format: IV:encrypted_content)
            $parts = explode(':', $encryptedFileContent, 2);
            if (count($parts) !== 2) {
                die('<p>Fehler: Ungültiges Dateiformat.</p>');
            }
            
            $ivHex = $parts[0];
            $encryptedContent = $parts[1];
            $iv = hex2bin($ivHex);
            
            // Get encryption key from session
            $encryptionKey = getEncryptionKey();
            $encryptionMethod = $config['encryption']['method'];

            // Decrypt the file content
            $decryptedContent = openssl_decrypt($encryptedContent, $encryptionMethod, $encryptionKey, 0, $iv);

            if ($decryptedContent === false) {
                die('<p>Fehler beim Entschlüsseln der Datei.</p>');
            } else {
                // Use original filename
                $fileName = $result['original_filename'];
                
                // Set headers for the decrypted file download
                header('Content-Type: application/pdf');
                header('Content-Disposition: attachment; filename="' . htmlspecialchars($fileName) . '"');
                header('Content-Length: ' . strlen($decryptedContent));

                // Output the decrypted content directly for download
                echo $decryptedContent;

                exit; // Ensure the script stops here after sending the file
            }
        } else {
            die('<p>Die Datei wurde nicht gefunden.</p>');
        }
    } else {
        die('<p>Keine Datei gefunden.</p>');
    }

    exit; // Stop further execution
}

// Handle preview request - requires authentication
if (isset($_GET['preview_file']) && isset($_GET['document_id'])) {
    $document_id = intval($_GET['document_id']);
    $stmt = $db->prepare('SELECT file_path, original_filename FROM documents WHERE id = :id');
    $stmt->bindValue(':id', $document_id, SQLITE3_INTEGER);
    $result = $stmt->execute()->fetchArray(SQLITE3_ASSOC);

    if ($result && $result['file_path']) {
        $filePath = dirname(__DIR__) . '/' . $result['file_path'];

        if (file_exists($filePath)) {
            // Read the encrypted file content
            $encryptedFileContent = file_get_contents($filePath);
            
            // Extract IV and encrypted content (format: IV:encrypted_content)
            $parts = explode(':', $encryptedFileContent, 2);
            if (count($parts) !== 2) {
                die('<p>Fehler: Ungültiges Dateiformat.</p>');
            }
            
            $ivHex = $parts[0];
            $encryptedContent = $parts[1];
            $iv = hex2bin($ivHex);
            
            // Get encryption key from session
            $encryptionKey = getEncryptionKey();
            $encryptionMethod = $config['encryption']['method'];

            // Decrypt the file content
            $decryptedContent = openssl_decrypt($encryptedContent, $encryptionMethod, $encryptionKey, 0, $iv);

            if ($decryptedContent === false) {
                die('<p>Fehler beim Entschlüsseln der Datei.</p>');
            } else {
                // Set headers for PDF preview
                header('Content-Type: application/pdf');
                header('Content-Disposition: inline; filename="' . htmlspecialchars($result['original_filename']) . '"');
                header('Content-Length: ' . strlen($decryptedContent));

                // Output the decrypted content for preview
                echo $decryptedContent;

                exit; // Ensure the script stops here after sending the file
            }
        } else {
            die('<p>Die Datei wurde nicht gefunden.</p>');
        }
    } else {
        die('<p>Keine Datei gefunden.</p>');
    }

    exit; // Stop further execution
}

?>

<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dokumente</title>
    <link rel="stylesheet" href="../css/styles.css?v=<?php echo APP_VERSION; ?>">
    <script>
        window.isAdmin = <?php echo $is_admin ? 'true' : 'false'; ?>;
    </script>
    <script src="../js/documents.js"></script>
    <style>
        .documents-container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
        }
        
        .upload-section {
            background: #f9f9f9;
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 30px;
        }
        
        .documents-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }
        
        .documents-table th,
        .documents-table td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid #ddd;
        }
        
        .documents-table th {
            background-color: #f2f2f2;
            font-weight: bold;
        }
        
        .documents-table tr:hover {
            background-color: #f5f5f5;
        }
        
        .action-buttons {
            display: flex;
            gap: 10px;
        }
        
        .btn-preview,
        .btn-download,
        .btn-delete {
            padding: 6px 12px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            text-decoration: none;
            display: inline-block;
            font-size: 14px;
        }
        
        .btn-preview {
            background-color: #4CAF50;
            color: white;
        }
        
        .btn-preview:hover {
            background-color: #45a049;
        }
        
        .btn-download {
            background-color: #2196F3;
            color: white;
        }
        
        .btn-download:hover {
            background-color: #0b7dda;
        }
        
        .btn-delete {
            background-color: #f44336;
            color: white;
        }
        
        .btn-delete:hover {
            background-color: #da190b;
        }
        
        .file-size {
            color: #666;
        }
        
        #message-container,
        #error-container {
            margin: 20px 0;
        }
        
        .upload-form-group {
            margin-bottom: 15px;
        }
        
        .upload-form-group label {
            display: block;
            margin-bottom: 5px;
            font-weight: bold;
        }
        
        .upload-form-group input[type="file"],
        .upload-form-group textarea {
            width: 100%;
            padding: 8px;
            border: 1px solid #ddd;
            border-radius: 4px;
            box-sizing: border-box;
        }
        
        .upload-form-group textarea {
            resize: vertical;
            min-height: 80px;
        }
        
        #loading-indicator {
            text-align: center;
            padding: 20px;
            font-style: italic;
            color: #666;
        }
        
        #search-documents {
            box-sizing: border-box;
        }
        
        #search-documents:focus {
            outline: none;
            border-color: #4CAF50;
            box-shadow: 0 0 5px rgba(76, 175, 80, 0.3);
        }
    </style>
</head>
<body>
    <?php include __DIR__ . '/../includes/header.php'; ?>
    <main>
        <div class="documents-container">
            <h1>Dokumente</h1>

            <!-- Message containers -->
            <div id="message-container"></div>
            <div id="error-container"></div>

            <!-- Upload Section (Admin only) -->
            <?php if ($is_admin): ?>
            <div class="upload-section">
                <h2>Dokument hochladen</h2>
                <form id="upload-document-form" enctype="multipart/form-data">
                    <?php require_once __DIR__ . '/../includes/csrf.php'; csrf_field(); ?>
                    <div class="upload-form-group">
                        <label for="document_file">PDF-Datei:</label>
                        <input type="file" id="document_file" name="document_file" accept=".pdf" required>
                    </div>
                    <div class="upload-form-group">
                        <label for="document_description">Beschreibung (optional):</label>
                        <textarea id="document_description" name="description" placeholder="Beschreibung des Dokuments"></textarea>
                    </div>
                    <button type="submit" class="button-full">Dokument hochladen</button>
                </form>
            </div>
            <?php endif; ?>

            <!-- Documents List -->
            <h2>Verfügbare Dokumente</h2>
            
            <!-- Search Box -->
            <div style="margin-bottom: 20px;">
                <input type="text" id="search-documents" placeholder="Dokumente suchen (Dateiname oder Beschreibung)..." style="width: 100%; max-width: 500px; padding: 10px; border: 1px solid #ddd; border-radius: 4px; font-size: 14px;">
            </div>
            
            <div id="loading-indicator" style="display: none;">Lade Dokumente...</div>
            <table class="documents-table" id="documents-table">
                <thead>
                    <tr>
                        <th>Dateiname</th>
                        <th>Beschreibung</th>
                        <th>Größe</th>
                        <th>Hochgeladen am</th>
                        <th>Aktionen</th>
                    </tr>
                </thead>
                <tbody id="documents-tbody">
                    <!-- Will be populated by JavaScript -->
                </tbody>
            </table>
        </div>
    </main>
    <?php include __DIR__ . '/../includes/footer.php'; ?>
</body>
</html>
