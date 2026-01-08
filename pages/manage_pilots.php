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

// Note: POST handling has been moved to api/pilots.php
// This page now only renders HTML. Data is fetched via API in manage_pilots.js
?>

<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Piloten verwalten</title>
    <link rel="stylesheet" href="../css/styles.css?v=<?php echo APP_VERSION; ?>">
    <link rel="stylesheet" href="../css/manage_pilots.css?v=<?php echo APP_VERSION; ?>">
    <script src="../js/manage_pilots.js"></script>
</head>
<body>
    <?php include __DIR__ . '/../includes/header.php'; ?>
    <main>
        <h1>Piloten verwalten</h1>

        <!-- Message containers -->
        <div id="message-container"></div>
        <div id="error-container"></div>

        <!-- Add Pilot Form -->
        <form id="add-pilot-form">
            <?php require_once __DIR__ . '/../includes/csrf.php'; csrf_field(); ?>
            <label for="name">Pilot hinzufügen:</label>
            <input type="text" id="name" name="name" placeholder="Name eingeben" required>
            <br><br>
            <button type="submit" class="button-full">Hinzufügen</button>
        </form>

        <!-- Pilots List -->
        <h2>Bestehende Piloten</h2>
        <div id="loading-indicator" style="display: none;">Lade Daten...</div>
        <table id="pilots-table">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Name</th>
                    <th>Benötigte Flugminuten</th>
                    <th>Aktionen</th>
                </tr>
            </thead>
            <tbody id="pilots-tbody">
                <!-- Will be populated by JavaScript -->
            </tbody>
        </table>
    </main>
    <?php include __DIR__ . '/../includes/footer.php'; ?>
</body>
</html>
