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
$dbPath = getDatabasePath();
$db = new SQLite3($dbPath);

// Note: POST handling for location creation and file upload has been moved to api/locations.php
// File downloads still handled here (decryption requires session key)

// Handle download request
if (isset($_GET['download_file']) && isset($_GET['location_id'])) {
    $location_id = intval($_GET['location_id']);
    $stmt = $db->prepare('SELECT file_path FROM flight_locations WHERE id = :id');
    $stmt->bindValue(':id', $location_id, SQLITE3_INTEGER);
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

// Note: Location listing is now handled via API in manage_locations.js
// Use centralized toLocalTime function from utils.php

?>

<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Flugstandorte verwalten</title>
    <link rel="stylesheet" href="../css/styles.css?v=<?php echo APP_VERSION; ?>">
    <link rel="stylesheet" href="../css/manage_locations.css?v=<?php echo APP_VERSION; ?>">
    <script src="../js/manage_locations.js"></script>
</head>
<body>
    <?php include __DIR__ . '/../includes/header.php'; ?>
    <main>
        <h1>Flugstandorte verwalten</h1>

        <!-- Message containers -->
        <div id="message-container"></div>
        <div id="error-container"></div>

        <!-- Add Location Form -->
        <form id="add-location-form">
            <?php require_once __DIR__ . '/../includes/csrf.php'; csrf_field(); ?>
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
            <div class="filter-form">
                <div class="filter-checkbox-group">
                    <input type="checkbox" id="filter_training" name="filter_training">
                    <label for="filter_training">Nur Einsätze anzeigen</label>
                </div>
                <button class="button-full" type="button" id="apply-filter-btn">Filter anwenden</button>
            </div>
        </div>

        <div id="loading-indicator" style="display: none;">Lade Daten...</div>

        <table id="locations-table">
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
                    <th>Aktionen</th>
                </tr>
            </thead>
            <tbody id="locations-tbody">
                <!-- Will be populated by JavaScript -->
            </tbody>
        </table>
    </main>
    <?php include __DIR__ . '/../includes/footer.php'; ?>
</body>
</html>
