<?php
require_once __DIR__ . '/../includes/error_reporting.php';
require_once __DIR__ . '/../includes/security_headers.php';
require __DIR__ . '/../includes/auth.php';
requireAuth();

// Set timezone from config
$config = include __DIR__ . '/../config/config.php';
if (isset($config['timezone'])) {
    date_default_timezone_set($config['timezone']);
}

require_once __DIR__ . '/../includes/utils.php';
require_once __DIR__ . '/../includes/version.php';

// Note: POST handling has been moved to api/flights.php
// This page now only renders HTML. Data is fetched via API in delete_flights.js
?>

<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>M30T Flüge löschen - Drohnenpiloten</title>
    <link rel="stylesheet" href="../css/styles.css?v=<?php echo APP_VERSION; ?>">
    <script src="../js/delete_flights.js"></script>
</head>
<body>
    <?php include __DIR__ . '/../includes/header.php'; ?>
    <main>
        <h1>Flüge löschen</h1>
        
        <!-- Message containers -->
        <div id="message-container"></div>
        <div id="error-container"></div>
        
        <div id="loading-indicator" style="display: none;">Lade Daten...</div>
        
        <table id="flights-table">
            <thead>
                <tr>
                    <th>Pilot</th>
                    <th>Flugdatum</th>
                    <th>Aktion</th>
                </tr>
            </thead>
            <tbody id="flights-tbody">
                <!-- Will be populated by JavaScript -->
            </tbody>
        </table>
    </main>
    <?php include __DIR__ . '/../includes/footer.php'; ?>
</body>
</html>
