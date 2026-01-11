<?php
require_once __DIR__ . '/../includes/error_reporting.php';
require_once __DIR__ . '/../includes/security_headers.php';
require __DIR__ . '/../includes/auth.php';
requireAuth();

$config = include __DIR__ . '/../config/config.php';
if (isset($config['timezone'])) {
    date_default_timezone_set($config['timezone']);
}

require_once __DIR__ . '/../includes/utils.php';
require_once __DIR__ . '/../includes/version.php';
require_once __DIR__ . '/../includes/csrf.php';

// Calculate base path for assets
$basePath = '../';

// Check admin access
if (!isAdmin()) {
    header('Location: ../pages/dashboard.php');
    exit();
}

// Load updater class
require_once __DIR__ . '/updater.php';

$projectRoot = dirname(__DIR__);
$updater = new Updater($projectRoot);

// Get current version info
$currentVersion = APP_VERSION;
$updateInfo = null;
$error = null;

// Try to check for updates on page load (non-blocking)
try {
    $updateInfo = $updater->checkForUpdates();
} catch (Exception $e) {
    $error = $e->getMessage();
}

?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Update Tool - Admin</title>
    <link rel="stylesheet" href="<?php echo $basePath; ?>css/styles.css?v=<?php echo APP_VERSION; ?>">
    <link rel="stylesheet" href="<?php echo $basePath; ?>updater/updater.css?v=<?php echo APP_VERSION; ?>">
    <link rel="manifest" href="<?php echo $basePath; ?>manifest.json">
</head>
<body>
    <?php include __DIR__ . '/../includes/header.php'; ?>
    <main>
        <h1>Update Tool</h1>
        
        <!-- Error message container -->
        <div id="error-message-container" class="error-message" style="display: none;"></div>
        
        <!-- Success message container -->
        <div id="success-message-container" class="success-message" style="display: none;"></div>
        
        <div class="updater-container">
            <!-- Current Version Section -->
            <div class="version-section">
                <h2>Aktuelle Version</h2>
                <div class="version-badge current-version">
                    v<?php echo htmlspecialchars($currentVersion, ENT_QUOTES, 'UTF-8'); ?>
                </div>
            </div>
            
            <!-- Update Check Section -->
            <div class="update-check-section">
                <h2>Update prüfen</h2>
                <button id="check-updates-btn" class="btn-primary">
                    <span class="btn-text">Auf Updates prüfen</span>
                    <span class="btn-spinner" style="display: none;">⏳</span>
                </button>
            </div>
            
            <!-- Available Update Section -->
            <div id="update-available-section" class="update-available-section" style="display: none;">
                <h2>Update verfügbar</h2>
                <div class="update-info">
                    <div class="version-comparison">
                        <span class="version-badge current-version">v<?php echo htmlspecialchars($currentVersion, ENT_QUOTES, 'UTF-8'); ?></span>
                        <span class="version-arrow">→</span>
                        <span class="version-badge new-version" id="new-version-badge">v?.?.?</span>
                    </div>
                    <div id="release-notes" class="release-notes" style="display: none;"></div>
                    <a id="release-url" href="#" target="_blank" rel="noopener noreferrer" class="release-link" style="display: none;">
                        Release auf GitHub ansehen
                    </a>
                </div>
                <div class="update-actions">
                    <button id="update-now-btn" class="btn-update">
                        <span class="btn-text">Jetzt aktualisieren</span>
                        <span class="btn-spinner" style="display: none;">⏳</span>
                    </button>
                </div>
            </div>
            
            <!-- No Update Section -->
            <div id="no-update-section" class="no-update-section" style="display: none;">
                <h2>Kein Update verfügbar</h2>
                <p>Sie verwenden bereits die neueste Version.</p>
            </div>
            
            <!-- Update Progress Section -->
            <div id="update-progress-section" class="update-progress-section" style="display: none;">
                <h2>Update wird durchgeführt</h2>
                <div class="progress-container">
                    <div class="progress-bar">
                        <div id="progress-bar-fill" class="progress-bar-fill" style="width: 0%;"></div>
                    </div>
                    <div id="progress-text" class="progress-text">Vorbereitung...</div>
                </div>
                <div id="update-status" class="update-status"></div>
            </div>
            
            <!-- Update Complete Section -->
            <div id="update-complete-section" class="update-complete-section" style="display: none;">
                <h2>Update abgeschlossen</h2>
                <div id="update-results" class="update-results"></div>
                <div class="update-actions">
                    <button id="reload-page-btn" class="btn-primary">Seite neu laden</button>
                </div>
            </div>
        </div>
        
        <!-- Info Section -->
        <div class="info-section">
            <h3>Hinweise</h3>
            <ul>
                <li>Vor dem Update wird automatisch ein Backup erstellt</li>
                <li>Konfigurationsdateien, Uploads und Datenbanken werden geschützt</li>
                <li>Bei einem Fehler wird automatisch ein Rollback durchgeführt</li>
                <li>Das Update kann einige Minuten dauern</li>
            </ul>
        </div>
    </main>
    
    <?php include __DIR__ . '/../includes/footer.php'; ?>
    
    <?php
    // Calculate base path for assets
    $basePath = '../';
    ?>
    <script>
        // Make config available to JavaScript
        window.updaterConfig = {
            currentVersion: <?php echo json_encode($currentVersion); ?>,
            updateInfo: <?php echo json_encode($updateInfo); ?>,
            csrfToken: <?php echo json_encode(getCSRFToken()); ?>,
            basePath: <?php echo json_encode($basePath); ?>
        };
    </script>
    <script src="<?php echo $basePath; ?>updater/updater.js"></script>
</body>
</html>
