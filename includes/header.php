<?php
require_once __DIR__ . '/error_reporting.php';

$configFile = dirname(__DIR__) . '/config/config.php';
// Calculate base path for assets (relative to document root)
// This works whether header.php is included from root, pages/, or updater/ directory
$basePath = '';
if (strpos($_SERVER['PHP_SELF'], '/pages/') !== false || strpos($_SERVER['PHP_SELF'], '/updater/') !== false) {
    $basePath = '../';
}
if (!file_exists($configFile)) {
    header('Location: ' . dirname($_SERVER['PHP_SELF']) . '/setup.php');
    exit;
}

require_once __DIR__ . '/security_headers.php';
require_once __DIR__ . '/version.php';
require_once __DIR__ . '/utils.php';
$config = include $configFile;

if (isset($config['timezone'])) {
    date_default_timezone_set($config['timezone']);
}

require_once __DIR__ . '/auth.php';
$is_admin = isAdmin();

// Check for pending migrations
$hasPendingMigrations = false;
try {
    require_once __DIR__ . '/migration_runner.php';
    $hasPendingMigrations = hasPendingMigrations();
} catch (Exception $e) {
    // Silently fail if database doesn't exist yet
    $hasPendingMigrations = false;
}

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (isset($_GET['test_version'])) {
    if ($_GET['test_version'] === '1') {
        $_SESSION['test_version_update'] = true;
    } elseif ($_GET['test_version'] === '0') {
        unset($_SESSION['test_version_update']);
    }
}

$testVersionUpdate = isset($_SESSION['test_version_update']) && $_SESSION['test_version_update'] === true;

if ($testVersionUpdate) {
    $versionCheck = [
        'available' => true,
        'version' => '1.1.0',
        'url' => 'https://github.com/' . GITHUB_REPO_OWNER . '/' . GITHUB_REPO_NAME . '/releases/latest'
    ];
    $hasUpdate = true;
} else {
    $versionCheck = checkGitHubVersion(APP_VERSION, GITHUB_REPO_OWNER, GITHUB_REPO_NAME);
    $hasUpdate = $versionCheck && $versionCheck['available'];
}

$url = trim($config['external_documentation_url'] ?? '');
$admin_error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['admin_password'])) {
    require_once __DIR__ . '/csrf.php';
    require_once __DIR__ . '/rate_limit.php';
    verify_csrf();
    
    $isAjax = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
    
    if (checkRateLimit('admin_login', 3, 1800)) {
        $admin_error = 'Zu viele fehlgeschlagene Admin-Anmeldeversuche. Bitte versuchen Sie es später erneut.';
        if ($isAjax) {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'error' => $admin_error]);
            exit();
        }
    } else {
        $admin_password = $_POST['admin_password'] ?? '';
        $verifyResult = verifyPassword($admin_password, $config['admin_hash']);
        
        if ($verifyResult['valid']) {
            // Update hash if needed (legacy SHA256 -> bcrypt migration)
            if ($verifyResult['needs_rehash'] && $verifyResult['new_hash']) {
                updateConfig('admin_hash', $verifyResult['new_hash']);
            }
            
            clearRateLimit('admin_login');
            session_regenerate_id(true);
            setAdminStatus(true);
            
            if ($isAjax) {
                header('Content-Type: application/json');
                echo json_encode([
                    'success' => true, 
                    'message' => 'Erfolgreich als Admin angemeldet!'
                ]);
                exit();
            } else {
                header("Location: " . $_SERVER['PHP_SELF']);
                exit();
            }
        } else {
            recordFailedAttempt('admin_login');
            $admin_error = 'Falsches Admin-Passwort!';
            sleep(1);
            if ($isAjax) {
                header('Content-Type: application/json');
                echo json_encode(['success' => false, 'error' => $admin_error]);
                exit();
            }
        }
    }
}
?>
<link rel="stylesheet" href="<?php echo $basePath; ?>css/navigation.css?v=<?php echo APP_VERSION; ?>">
<header>
    <div class="nav-backdrop" id="nav-backdrop"></div>
    <nav>
        <div class="nav-header">
            <div class="nav-title-container">
                <?php 
                $logo_path = $config['logo_path'] ?? '';
                if (!empty($logo_path) && file_exists(__DIR__ . '/../' . $logo_path)): 
                ?>
                    <a href="<?php echo $basePath; ?>pages/dashboard.php"><img src="<?php echo $basePath . htmlspecialchars($logo_path, ENT_QUOTES, 'UTF-8'); ?>" alt="Logo" class="nav-logo"></a>
                <?php endif; ?>
                <span class="nav-title"><?php echo $config['navigation_title']; ?>  <?php if ($is_admin): ?> - Admin <?php endif; ?></span>
            </div>
            <div class="nav-actions">
                <?php if ($is_admin): ?>
                    <span class="admin-icon clickable" id="admin-logout-icon" title="Als normaler Benutzer zurückwechseln">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <rect x="3" y="11" width="18" height="11" rx="2" ry="2"></rect>
                            <path d="M7 11V7a5 5 0 0 1 10 0v4"></path>
                        </svg>
                    </span>
                <?php endif; ?>
                <?php if ($hasPendingMigrations): ?>
                    <a href="<?php echo $basePath; ?>pages/migrations.php" 
                       class="migration-notification" 
                       title="Datenbank-Update verfügbar. Klicken Sie, um zur Update-Seite zu gehen.">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"></path>
                            <path d="M13.73 21a2 2 0 0 1-3.46 0"></path>
                        </svg>
                        <span class="migration-badge"></span>
                    </a>
                <?php endif; ?>
                <?php if ($hasUpdate): ?>
                    <a href="<?php echo $is_admin ? $basePath . 'updater/updater_page.php' : htmlspecialchars($versionCheck['url'], ENT_QUOTES, 'UTF-8'); ?>" 
                       <?php if (!$is_admin): ?>target="_blank" rel="noopener noreferrer"<?php endif; ?>
                       class="version-notification" 
                       title="Neue Version <?php echo htmlspecialchars($versionCheck['version'], ENT_QUOTES, 'UTF-8'); ?> verfügbar!<?php echo $is_admin ? ' Klicken Sie, um zum Update-Tool zu gehen.' : ' Klicken Sie, um zur GitHub-Seite zu gehen.'; ?>">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"></path>
                            <path d="M13.73 21a2 2 0 0 1-3.46 0"></path>
                        </svg>
                        <span class="version-badge">v<?php echo htmlspecialchars($versionCheck['version'], ENT_QUOTES, 'UTF-8'); ?></span>
                    </a>
                <?php endif; ?>
            </div>
            <button class="nav-toggle" aria-label="Menu ein/ausklappen" id="nav-toggle-btn" aria-expanded="false">
                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <line x1="3" y1="12" x2="21" y2="12"></line>
                    <line x1="3" y1="6" x2="21" y2="6"></line>
                    <line x1="3" y1="18" x2="21" y2="18"></line>
                </svg>
            </button>
        </div>
        <ul class="nav-menu" id="nav-menu">
            <li><a href="<?php echo $basePath; ?>pages/dashboard.php">Dashboard</a></li>
            <li><a href="<?php echo $basePath; ?>pages/manage_locations.php">Flugstandorte</a></li>
            <li><a href="<?php echo $basePath; ?>pages/view_flights.php">Alle Flüge anzeigen</a></li>
            <li><a href="<?php echo $basePath; ?>pages/battery_overview.php">Akku Übersicht</a></li>
            <li>
                <button class="nav-dropdown-toggle" id="nav-dropdown-toggle-btn">Verwaltung</button>
                <ul class="nav-dropdown" id="nav-dropdown">
                    <li><a href="<?php echo $basePath; ?>pages/add_flight.php">Manueller Eintrag</a></li>
                    <li><a href="<?php echo $basePath; ?>pages/manage_pilots.php">Piloten verwalten</a></li>
                    <li><a href="<?php echo $basePath; ?>pages/manage_drones.php">Drohnen verwalten</a></li>
                    <li><a href="<?php echo $basePath; ?>pages/add_events.php">Dienst anlegen</a></li>
                    <li><a href="<?php echo $basePath; ?>pages/view_events.php">Dienste ansehen</a></li>
                    <li><a href="<?php echo $basePath; ?>pages/documents.php">Dokumente</a></li>
                    <li><a href="<?php echo $basePath; ?>pages/migrations.php">Datenbank Update</a></li>
                    <?php if ($is_admin): ?>
                        <li><a href="<?php echo $basePath; ?>pages/admin.php">Admin-Seite</a></li>
                        <li><a href="<?php echo $basePath; ?>updater/updater_page.php">Update Tool</a></li>
                    <?php endif; ?>
                    <?php if (!$is_admin): ?>
                        <li><a href="#" id="admin-modal-link">Admin</a></li>
                    <?php endif; ?>
                </ul>
            </li>
            <?php if (!empty($url)): ?>
                <li><a href="<?= htmlspecialchars($url, ENT_QUOTES, 'UTF-8') ?>">Einsatzdoku</a></li>
            <?php endif; ?>
            <li><a href="https://github.com/<?php echo GITHUB_REPO_OWNER . '/' . GITHUB_REPO_NAME; ?>/wiki" target="_blank" rel="noopener noreferrer">Hilfe / Wiki</a></li>
            <li><a href="<?php echo $basePath; ?>pages/about.php">Über</a></li>
            <li><a href="<?php echo $basePath; ?>pages/logout.php">Logout</a></li>
        </ul>
    </nav>
</header>

<!-- Admin Login Modal -->
<div id="admin-modal" class="modal">
    <div class="modal-content">
        <span class="close" id="admin-modal-close">&times;</span>
        <h2 id="admin-modal-title">Admin Login</h2>
        <form id="admin-login-form" method="post" action="<?php echo $_SERVER['PHP_SELF']; ?>">
            <?php require_once __DIR__ . '/csrf.php'; csrf_field(); ?>
            <div id="admin-login-content">
                <div class="form-group">
                    <label for="admin_password">Passwort:</label>
                    <input type="password" id="admin_password" name="admin_password" required>
                </div>
                <div id="admin-message-container"></div>
                <br>
                <br>
                <button type="submit">Als Admin anmelden</button>
            </div>
            <div id="admin-logout-content" style="display: none;">
                <p>Möchten Sie wirklich zu einem normalen Benutzer zurückwechseln?</p>
                <div id="admin-logout-message-container"></div>
                <br>
                <br>
                <button type="button" id="admin-logout-confirm" class="modal-button">Zu normalem Benutzer wechseln</button>
            </div>
        </form>
    </div>
</div>

<script>
    // Define base path for API calls - works from root or pages/ directory
    window.basePath = '<?php echo $basePath; ?>';
</script>
<script src="<?php echo $basePath; ?>js/header.js"></script>
