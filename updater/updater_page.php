<?php
// Get base directory and normalize path
$baseDir = realpath(dirname(__DIR__)) ?: dirname(__DIR__);
require_once $baseDir . DIRECTORY_SEPARATOR . 'includes' . DIRECTORY_SEPARATOR . 'error_reporting.php';
require_once $baseDir . DIRECTORY_SEPARATOR . 'includes' . DIRECTORY_SEPARATOR . 'security_headers.php';
require $baseDir . DIRECTORY_SEPARATOR . 'includes' . DIRECTORY_SEPARATOR . 'auth.php';
requireAuth();

$config = include $baseDir . DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR . 'config.php';
if (isset($config['timezone'])) {
    date_default_timezone_set($config['timezone']);
}

require_once $baseDir . DIRECTORY_SEPARATOR . 'includes' . DIRECTORY_SEPARATOR . 'utils.php';
require_once $baseDir . DIRECTORY_SEPARATOR . 'includes' . DIRECTORY_SEPARATOR . 'version.php';
require_once $baseDir . DIRECTORY_SEPARATOR . 'includes' . DIRECTORY_SEPARATOR . 'csrf.php';

// Calculate base path for assets
$basePath = '../';

// Check admin access
if (!isAdmin()) {
    header('Location: ../pages/dashboard.php');
    exit();
}

// Load updater class
require_once __DIR__ . '/updater.php';

$projectRoot = realpath(dirname(__DIR__)) ?: dirname(__DIR__);
$updater = new Updater($projectRoot);

// Check requirements
$requirements = $updater->checkRequirements();
$requirementsError = null;
if (!$requirements['available']) {
    $requirementsError = "Warnung: Erforderliche PHP-Erweiterungen fehlen: " . implode(', ', $requirements['missing']) . 
                        ". Bitte installieren Sie die fehlenden Erweiterungen, bevor Sie ein Update durchführen.";
}

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
    <?php include $baseDir . DIRECTORY_SEPARATOR . 'includes' . DIRECTORY_SEPARATOR . 'header.php'; ?>
    <main>
        <h1>Update Tool</h1>
        
        <!-- Error message container -->
        <div id="error-message-container" class="error-message" style="display: none;"></div>
        
        <!-- Success message container -->
        <div id="success-message-container" class="success-message" style="display: none;"></div>
        
        <!-- Requirements warning -->
        <?php if ($requirementsError): ?>
            <div class="error-message" style="display: block; margin-bottom: 1.5rem;">
                <strong>⚠️ Systemanforderungen:</strong><br>
                <?php echo nl2br(htmlspecialchars($requirementsError, ENT_QUOTES, 'UTF-8')); ?>
                <div style="margin-top: 1rem; padding: 1rem; background: #fff3cd; border-radius: 8px; border-left: 4px solid #ffc107;">
                    <strong>⚠️ PHP zip-Erweiterung ist nicht aktiviert!</strong><br><br>
                    <strong>Ihre PHP-Version:</strong> <?php echo PHP_VERSION; ?><br>
                    <strong>php.ini:</strong> <?php echo php_ini_loaded_file() ?: 'Nicht gefunden'; ?><br>
                    <strong>Extension-Verzeichnis:</strong> <?php echo ini_get('extension_dir') ?: 'Standard'; ?><br><br>
                    <strong>Installationsmethoden (in dieser Reihenfolge versuchen):</strong><br><br>
                    <strong>Methode 1 - Paket installieren:</strong><br>
                    <code>sudo apt-get install php-zip</code><br>
                    Dann prüfen, ob die Extension-Datei existiert:<br>
                    <code>find /usr -name 'zip.so' 2>/dev/null</code><br><br>
                    <strong>Methode 2 - Manuelle Aktivierung (falls zip.so existiert, aber nicht geladen wird):</strong><br>
                    <?php 
                    $phpVersion = explode('.', PHP_VERSION)[0] . '.' . explode('.', PHP_VERSION)[1];
                    $zipSoPath = shell_exec('find /usr -name "zip.so" 2>/dev/null | head -1');
                    $zipSoExists = !empty(trim($zipSoPath));
                    $zipSoPath = trim($zipSoPath);
                    
                    // Check API version
                    $wrongVersion = false;
                    $foundApiVersion = null;
                    if ($zipSoExists && preg_match('/\/(\d{8})\/zip\.so$/', $zipSoPath, $matches)) {
                        $foundApiVersion = $matches[1];
                        // PHP 8.2 should have 20220829
                        $expectedApi = version_compare(PHP_VERSION, '8.3', '>=') ? '20230831' : 
                                      (version_compare(PHP_VERSION, '8.2', '>=') ? '20220829' : 
                                      (version_compare(PHP_VERSION, '8.1', '>=') ? '20210902' : 
                                      (version_compare(PHP_VERSION, '8.0', '>=') ? '20200930' : '20190902')));
                        $wrongVersion = ($foundApiVersion !== $expectedApi);
                    }
                    ?>
                    <?php if ($zipSoExists): ?>
                        <div style="background: <?php echo $wrongVersion ? '#f8d7da' : '#d1ecf1'; ?>; padding: 0.5rem; border-radius: 4px; margin: 0.5rem 0; border-left: 4px solid <?php echo $wrongVersion ? '#dc3545' : '#0c5460'; ?>;">
                            <strong><?php echo $wrongVersion ? '⚠️' : '✓'; ?> zip.so gefunden:</strong> <?php echo htmlspecialchars($zipSoPath); ?><br>
                            <?php if ($wrongVersion): ?>
                                <strong>PROBLEM:</strong> Diese zip.so ist für PHP <?php 
                                    $apiMap = ['20190902' => '7.4', '20200930' => '8.0', '20210902' => '8.1', '20220829' => '8.2', '20230831' => '8.3'];
                                    echo $apiMap[$foundApiVersion] ?? 'unbekannt';
                                ?> (API <?php echo $foundApiVersion; ?>), aber Sie verwenden PHP <?php echo PHP_VERSION; ?>!<br>
                                Sie benötigen zip.so für PHP <?php echo $phpVersion; ?> (API <?php echo $expectedApi; ?>).
                            <?php else: ?>
                                Die Extension-Datei existiert, muss aber aktiviert werden!
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                    <?php if ($wrongVersion): ?>
                        <div style="background: #fff3cd; padding: 1rem; border-radius: 4px; margin: 0.5rem 0; border-left: 4px solid #ffc107;">
                            <strong>⚠️ PROBLEM: zip.so ist für eine andere PHP-Version!</strong><br><br>
                            <strong>Lösung:</strong><br>
                            <ol style="margin: 0.5rem 0; padding-left: 1.5rem;">
                                <li>php-zip neu installieren (um die richtige Version zu bekommen):<br>
                                    <code>sudo apt-get remove php-zip</code><br>
                                    <code>sudo apt-get install php-zip</code></li>
                                <li>Prüfen, ob richtige zip.so existiert:<br>
                                    <code>find /usr/lib/php/<?php echo $expectedApi; ?>/zip.so</code><br>
                                    (Sollte zeigen: /usr/lib/php/<?php echo $expectedApi; ?>/zip.so)</li>
                                <li>Falls vorhanden, aktivieren:<br>
                                    <code>echo 'extension=zip' | sudo tee /etc/php/<?php echo $phpVersion; ?>/fpm/conf.d/20-zip.ini</code></li>
                                <li>extension_dir prüfen:<br>
                                    <code>php -i | grep extension_dir</code><br>
                                    (Sollte zeigen: /usr/lib/php/<?php echo $expectedApi; ?>)</li>
                                <li>PHP-FPM neu starten:<br>
                                    <code>sudo systemctl restart php<?php echo $phpVersion; ?>-fpm</code></li>
                                <li>Überprüfen:<br>
                                    <code>php -m | grep zip</code> (sollte "zip" anzeigen)</li>
                            </ol>
                        </div>
                    <?php else: ?>
                        <ol style="margin: 0.5rem 0; padding-left: 1.5rem;">
                            <li>Extension-Datei prüfen: <code>find /usr -name 'zip.so' 2>/dev/null</code></li>
                            <li>PHP-FPM conf.d Verzeichnis finden: <code>ls /etc/php/<?php echo $phpVersion; ?>/fpm/conf.d/</code></li>
                            <li>Extension aktivieren: <code>echo 'extension=zip' | sudo tee /etc/php/<?php echo $phpVersion; ?>/fpm/conf.d/20-zip.ini</code></li>
                            <li>PHP-FPM neu starten: <code>sudo systemctl restart php<?php echo $phpVersion; ?>-fpm</code></li>
                        </ol>
                    <?php endif; ?>
                    <strong>Hinweis:</strong> Falls die Extension immer noch nicht geladen wird, prüfen Sie:<br>
                    - <code>php --ini</code> (zeigt alle geladenen ini-Dateien)<br>
                    - <code>php -i | grep extension_dir</code> (zeigt Extension-Verzeichnis)<br>
                    - Die extension_dir sollte auf das Verzeichnis zeigen, in dem zip.so liegt
                    <strong>PHP-FPM neu starten:</strong><br>
                    <code>sudo systemctl restart php<?php echo explode('.', PHP_VERSION)[0] . '.' . explode('.', PHP_VERSION)[1]; ?>-fpm</code><br>
                    (oder: <code>sudo systemctl restart php-fpm</code>)<br><br>
                    <strong>Installation überprüfen:</strong><br>
                    <code>php -m | grep zip</code> (sollte "zip" anzeigen)<br>
                    <code>php -i | grep zip</code> (zeigt zip Extension-Info)
                </div>
            </div>
        <?php endif; ?>
        
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
    
    <?php include $baseDir . DIRECTORY_SEPARATOR . 'includes' . DIRECTORY_SEPARATOR . 'footer.php'; ?>
    
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
