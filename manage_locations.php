<?php
require_once __DIR__ . '/includes/error_reporting.php';
require_once __DIR__ . '/includes/security_headers.php';
require 'auth.php';
requireAuth();

$config = include __DIR__ . '/config/config.php';

// Set timezone from config
if (isset($config['timezone'])) {
    date_default_timezone_set($config['timezone']);
}

require_once __DIR__ . '/includes/utils.php';
require_once __DIR__ . '/version.php';
$dbPath = getDatabasePath();
$db = new SQLite3($dbPath);

// Handle adding a location
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['location_name'])) {
    require_once __DIR__ . '/includes/csrf.php';
    verify_csrf();
    require_once __DIR__ . '/includes/utils.php';
    
    $location_name = trim($_POST['location_name']);
    $latitude = isset($_POST['latitude']) ? validateLatitude($_POST['latitude']) : false;
    $longitude = isset($_POST['longitude']) ? validateLongitude($_POST['longitude']) : false;
    $description = isset($_POST['description']) ? trim($_POST['description']) : null;
    $training = isset($_POST['training']) ? 1 : 0;
    // Store in UTC
    $created_at = getCurrentUTC();

    if (!empty($location_name) && $latitude !== false && $longitude !== false) {
        $stmt = $db->prepare('INSERT INTO flight_locations (location_name, latitude, longitude, description, training, created_at) VALUES (:location_name, :latitude, :longitude, :description, :training, :created_at)');
        $stmt->bindValue(':location_name', $location_name, SQLITE3_TEXT);
        $stmt->bindValue(':latitude', $latitude, SQLITE3_FLOAT);
        $stmt->bindValue(':longitude', $longitude, SQLITE3_FLOAT);
        $stmt->bindValue(':description', $description, SQLITE3_TEXT);
        $stmt->bindValue(':training', $training, SQLITE3_INTEGER);
        $stmt->bindValue(':created_at', $created_at, SQLITE3_TEXT);
        $stmt->execute();
        $message = "Standort erfolgreich hinzugefügt.";
    } else {
        $error = "Bitte füllen Sie alle erforderlichen Felder aus.";
    }
}

// Handle file upload for existing locations
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['location_id']) && isset($_FILES['location_file'])) {
    require_once __DIR__ . '/includes/csrf.php';
    verify_csrf();
    
    $location_id = intval($_POST['location_id']);
    $file = $_FILES['location_file'];
    
    // Validate file upload
    if ($file['error'] !== UPLOAD_ERR_OK) {
        $error = "Fehler beim Hochladen der Datei (Error Code: " . $file['error'] . ").";
    } else {
        // File size limit: 10MB
        $maxSize = 10 * 1024 * 1024; // 10MB
        if ($file['size'] > $maxSize) {
            $error = "Die Datei ist zu groß. Maximale Größe: 10MB.";
        } else {
            // Validate file type (whitelist)
            $allowedTypes = ['application/pdf', 'image/jpeg', 'image/png', 'image/gif', 'application/msword', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'];
            
            // Try to get MIME type using fileinfo extension if available
            $mimeType = null;
            if (extension_loaded('fileinfo')) {
                $finfo = finfo_open(FILEINFO_MIME_TYPE);
                $mimeType = finfo_file($finfo, $file['tmp_name']);
                finfo_close($finfo);
            } else {
                // Fallback: use file extension if fileinfo is not available
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
                $error = "Dateityp nicht erlaubt. Erlaubte Typen: PDF, Bilder (JPEG, PNG, GIF), Word-Dokumente.";
            } else {
                // Sanitize filename
                $originalName = basename($file['name']);
                $safeName = preg_replace('/[^a-zA-Z0-9._-]/', '_', $originalName);
                $safeName = substr($safeName, 0, 255); // Limit length
                
                // Read the file content
                $fileContent = file_get_contents($file['tmp_name']);
                
                // Get encryption key from session (not cookie)
                $encryptionKey = getEncryptionKey();
                $encryptionMethod = $config['encryption']['method'];
                
                // Generate unique IV for each file
                $iv = openssl_random_pseudo_bytes(openssl_cipher_iv_length($encryptionMethod));
                $ivHex = bin2hex($iv);
                
                // Encrypt the file content
                $encryptedContent = openssl_encrypt($fileContent, $encryptionMethod, $encryptionKey, 0, $iv);
                
                if ($encryptedContent === false) {
                    $error = "Fehler beim Verschlüsseln der Datei.";
                } else {
                    // Ensure uploads directory exists
                    $uploadDir = __DIR__ . '/uploads/';
                    if (!is_dir($uploadDir)) {
                        mkdir($uploadDir, 0755, true);
                    }
                    
                    // Save encrypted content with unique filename
                    $uniqueId = bin2hex(random_bytes(8));
                    $encryptedFilePath = 'uploads/' . $uniqueId . '_' . $safeName . '.enc';
                    $fullPath = __DIR__ . '/' . $encryptedFilePath;
                    
                    // Store IV with encrypted file (prepend to file)
                    file_put_contents($fullPath, $ivHex . ':' . $encryptedContent);
                    
                    // Update database with file path and IV
                    $stmt = $db->prepare('UPDATE flight_locations SET file_path = :file_path WHERE id = :id');
                    $stmt->bindValue(':file_path', $encryptedFilePath, SQLITE3_TEXT);
                    $stmt->bindValue(':id', $location_id, SQLITE3_INTEGER);
                    $stmt->execute();
                    $message = "Datei erfolgreich hochgeladen und verschlüsselt.";
                }
            }
        }
    }
}

// Handle download request
if (isset($_GET['download_file']) && isset($_GET['location_id'])) {
    $location_id = intval($_GET['location_id']);
    $stmt = $db->prepare('SELECT file_path FROM flight_locations WHERE id = :id');
    $stmt->bindValue(':id', $location_id, SQLITE3_INTEGER);
    $result = $stmt->execute()->fetchArray(SQLITE3_ASSOC);

    if ($result && $result['file_path']) {
        $filePath = __DIR__ . '/' . $result['file_path'];

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
                // Extract original filename from encrypted filename
                $fileName = basename($result['file_path']);
                // Remove unique ID and .enc extension
                $fileName = preg_replace('/^[a-f0-9]+_/', '', $fileName);
                $fileName = preg_replace('/\.enc$/', '', $fileName);
                
                // Set headers for the decrypted file download
                header('Content-Type: application/octet-stream');
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

// Fetch all locations
$filter_training = isset($_GET['filter_training']) && $_GET['filter_training'] === 'false';
if ($filter_training) {
    $stmt = $db->prepare('SELECT * FROM flight_locations WHERE training = 0 ORDER BY created_at DESC');
} else {
    $stmt = $db->prepare('SELECT * FROM flight_locations ORDER BY created_at DESC');
}
$locations = $stmt->execute();

// Use centralized toLocalTime function from utils.php

?>

<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Flugstandorte verwalten</title>
    <link rel="stylesheet" href="css/styles.css?v=<?php echo APP_VERSION; ?>">
    <link rel="stylesheet" href="css/manage_locations.css?v=<?php echo APP_VERSION; ?>">
    <script src="js/manage_locations.js"></script>
</head>
<body>
    <?php include 'includes/header.php'; ?>
    <main>
        <h1>Flugstandorte verwalten</h1>

        <?php if (isset($message)): ?>
            <p class="message"><?= htmlspecialchars($message); ?></p>
        <?php endif; ?>
        <?php if (isset($error)): ?>
            <p class="error"><?= htmlspecialchars($error); ?></p>
        <?php endif; ?>

        <!-- Add Location Form -->
        <form method="post" action="manage_locations.php">
            <?php require_once __DIR__ . '/includes/csrf.php'; csrf_field(); ?>
            <div>
                <label for="location_name">Standortname</label>
                <input type="text" id="location_name" name="location_name" placeholder="Name des Standorts" required>
            </div>
            <br>
            <div>
                <label for="latitude">Breitengrad</label>
                <input type="text" id="latitude" name="latitude" required>
            </div>
            <div>
                <label for="longitude">Längengrad</label>
                <input type="text" id="longitude" name="longitude" required>
            </div>
            <button type="button" class="button-full" id="set-location-btn">
                <span id="set-location-text">Position automatisch setzen</span>
                <span id="location-spinner" class="spinner" style="display: none;"></span>
            </button>
            <div>
                <label for="description">Beschreibung (optional)</label>
                <br>
                <textarea id="description" name="description" placeholder="Beschreibung des Standorts"></textarea>
            </div>
            <div>
                <label>
                    <input type="checkbox" name="training" checked>
                    Ist ein Übungsflug
                </label>
            </div>

            <button type="submit" class="button-full">Standort hinzufügen</button>
        </form>

        <!-- Locations Table -->
        <h2>Vorhandene Standorte</h2>

        <div class="filter-container">
            <form method="get" action="manage_locations.php" class="filter-form">
                <div class="filter-checkbox-group">
                    <input type="checkbox" id="filter_training" name="filter_training" value="false" <?= $filter_training ? 'checked' : ''; ?>>
                    <label for="filter_training">Nur Einsätze anzeigen</label>
                </div>
                <button class="button-full" type="submit">Filter anwenden</button>
            </form>
        </div>

        <table>
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Standortname</th>
                    <th>Breitengrad</th>
                    <th>Längengrad</th>
                    <th>Beschreibung</th>
                    <th>Erstellt am</th>
                    <th>Einsatz</th>
                    <th>Datei hochladen</th>
                    <th>Datei herunterladen</th>
                </tr>
            </thead>
            <tbody>
                <?php while ($location = $locations->fetchArray(SQLITE3_ASSOC)): ?>
                    <tr class="<?= !$location['training'] ? 'training-false' : ''; ?>">
                        <td data-label="ID"><?= htmlspecialchars($location['id']); ?></td>
                        <td data-label="Standortname"><?= htmlspecialchars($location['location_name']); ?></td>
                        <td data-label="Breitengrad"><?= htmlspecialchars($location['latitude']); ?></td>
                        <td data-label="Längengrad"><?= htmlspecialchars($location['longitude']); ?></td>
                        <td data-label="Beschreibung"><?= htmlspecialchars($location['description']); ?></td>
                        <td data-label="Erstellt am"><?= htmlspecialchars(toLocalTime($location['created_at'])); ?></td>
                        <td data-label="Einsatz"><?= !$location['training'] ? 'Ja' : 'Nein'; ?></td>

                        <!-- File upload and download -->
                        <td data-label="Datei hochladen">
                            <?php if (!$location['file_path']): ?>
                                <form method="post" enctype="multipart/form-data">
                                    <?php require_once __DIR__ . '/includes/csrf.php'; csrf_field(); ?>
                                    <input type="hidden" name="location_id" value="<?= $location['id']; ?>">
                                    <input type="file" name="location_file" accept=".pdf,.jpg,.jpeg,.png,.gif,.doc,.docx" required>
                                    <br>
                                    <br>
                                    <button type="submit" class="button-full">Hochladen</button>
                                </form>
                            <?php else: ?>
                                <p>Datei bereits hochgeladen</p>
                            <?php endif; ?>
                        </td>

                        <td data-label="Datei herunterladen">
                            <?php if ($location['file_path']): ?>
                                <a href="manage_locations.php?download_file=true&location_id=<?= $location['id']; ?>">
                                    <button type="button" class="button-full">Herunterladen</button>
                                </a>
                            <?php else: ?>
                                <button type="button" disabled>Keine Datei</button>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
    </main>
    <?php include 'includes/footer.php'; ?>
</body>
</html>
