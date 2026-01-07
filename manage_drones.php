<?php
require_once __DIR__ . '/includes/error_reporting.php';
require_once __DIR__ . '/includes/security_headers.php';
require 'auth.php';
requireAuth();

// Set timezone from config
$config = include __DIR__ . '/config/config.php';
if (isset($config['timezone'])) {
    date_default_timezone_set($config['timezone']);
}

require_once __DIR__ . '/includes/utils.php';
require_once __DIR__ . '/version.php';

// Note: POST/GET handling has been moved to api/drones.php
// This page now only renders HTML. Data is fetched via API in manage_drones.js
?>

<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Drohnen verwalten</title>
    <link rel="stylesheet" href="css/styles.css?v=<?php echo APP_VERSION; ?>">
    <script src="js/manage_drones.js"></script>
</head>
<body>
    <?php include 'includes/header.php'; ?>
    <main>
        <h1>Drohnen verwalten</h1>

        <!-- Message containers -->
        <div id="message-container"></div>
        <div id="error-container"></div>

        <!-- Add Drone Form -->
        <form id="add-drone-form">
            <?php require_once __DIR__ . '/includes/csrf.php'; csrf_field(); ?>
            <div>
                <label for="drone_name">Drohnenname</label>
                <input type="text" id="drone_name" name="drone_name" placeholder="Name der Drohne" required>
            </div>
            <br><br>
            <button type="submit" class="button-full">Drohne hinzufügen</button>
        </form>

        <!-- Drones Table -->
        <h2>Vorhandene Drohnen</h2>
        <div id="loading-indicator" style="display: none;">Lade Daten...</div>
        <table id="drones-table">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Drohnenname</th>
                    <th>Aktionen</th>
                </tr>
            </thead>
            <tbody id="drones-tbody">
                <!-- Will be populated by JavaScript -->
            </tbody>
        </table>
    </main>
    <?php include 'includes/footer.php'; ?>
</body>
</html>