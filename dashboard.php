<?php
require_once __DIR__ . '/includes/error_reporting.php';
require_once __DIR__ . '/includes/security_headers.php';
require 'auth.php';
requireAuth();

$config = include __DIR__ . '/config/config.php';
if (isset($config['timezone'])) {
    date_default_timezone_set($config['timezone']);
}

require_once __DIR__ . '/includes/utils.php';
require_once __DIR__ . '/includes/dashboard_helpers.php';
require_once __DIR__ . '/version.php';

// Note: POST handling has been moved to api/flights.php
// This page now only renders HTML. Data is fetched via API in dashboard.js

?>

<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard</title>
    <link rel="stylesheet" href="css/styles.css?v=<?php echo APP_VERSION; ?>">
    <link rel="stylesheet" href="css/dashboard.css?v=<?php echo APP_VERSION; ?>">
    <link rel="manifest" href="manifest.json">
    <script src="js/dashboard.js"></script>
</head>
    <body>
        <?php include 'includes/header.php'; ?>
        <main>
            <h1>Dashboard</h1>

            <!-- Error message container -->
            <div id="error-message-container" class="error-message" style="display: none;"></div>
            
            <!-- Success message container -->
            <div id="success-message-container" class="success-message" style="display: none;"></div>
            
            <!-- Loading indicator -->
            <div id="loading-indicator" style="display: none;">Lade Daten...</div>

            <div id="dashboard-content">
                <!-- Content will be loaded via API -->
                <div id="welcome-section" class="welcome-section" style="display: none;">
                    <div class="welcome-section">
                        <h3>Willkommen zum Drohnenflug-Management Dashboard!</h3>
                        <p>Es scheint, als ob du dein Dashboard gerade erst erstellt hast. Diese Dinge müssen noch erledigt werden.</p>
                        <p>Vorweg ein paar Tips:</p>
                        <ul class="welcome-tips">
                            <li>Lege vor jedem Flug einen <a href="manage_locations.php">Flugstandort</a> an. Es werden nur die vom aktuellen Tag zur Auswahl gestellt.</li>
                            <li>Nutzt du auch die Einsatzdoku? Diese übernimmt automatisch den zuletzt hinzugefügten Flugstandort. Danach übernimmt die Doku alles, du musst hier in der Zeit nichts mehr machen</li>
                            <li>Flüge im Dashboard sollten am besten nur von einem Gerät gleichzeit gestartet und beendet werden.</li>
                            <li>Das Dashboard ist eine WebApp, anstannt eine Verknüfung zu erstllen, kannst du sie aus dem Browser heraus installieren.</li>
                            <li>Mit deinem Admin Passwort kannst du mehr Dinge tun.</li>
                            <li>Diese WebApp ist nun Open Source. Sie entstand für die Anforderungen an eine Drohnegruppe durch dessen Leiter. Passt sie nicht ganz zu dir, steht es dir frei sie anzupassen.</li>
                            <li>Diese Anwendung legt mehr Fokus aus Funktionaliät anstatt auf Aussehen</li>
                            <li>Schau regelmäßg bei GIT Hub vorbei, für Anleitungen und Updates</li>
                            <li>Sobald du Piloten eingefügt hast, verschwindet dieser Text</li>
                        </ul>
                        <div class="welcome-actions">
                            <p>Füge deine Drohnen unter <a href="manage_drones.php">Drohnen verwalten</a> hinzu</p>
                            <p>Füge deine Drohnenpiloten unter <a href="manage_pilots.php">Piloten verwalten</a> hinzu.</p>
                        </div>
                    </div>
                </div>
                
                <!-- Pilots container - will be populated by JavaScript -->
                <div id="pilots-container"></div>
            </div>

        </main>
        <?php include 'includes/footer.php'; ?>
    </body>
</html>