<?php
require_once __DIR__ . '/../includes/error_reporting.php';

$configFile = dirname(__DIR__) . '/config/config.php';
if (!file_exists($configFile)) {
    header('Location: ' . dirname($_SERVER['PHP_SELF']) . '/setup.php');
    exit;
}

require_once __DIR__ . '/../includes/security_headers.php';
require_once __DIR__ . '/../version.php';
require_once __DIR__ . '/../includes/utils.php';
$config = include $configFile;

if (isset($config['timezone'])) {
    date_default_timezone_set($config['timezone']);
}

require_once __DIR__ . '/../auth.php';
$is_admin = isAdmin();

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

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['admin_password'])) {
    require_once __DIR__ . '/../includes/csrf.php';
    require_once __DIR__ . '/../includes/rate_limit.php';
    verify_csrf();
    
    if (checkRateLimit('admin_login', 3, 1800)) {
        $admin_error = 'Zu viele fehlgeschlagene Admin-Anmeldeversuche. Bitte versuchen Sie es später erneut.';
    } else {
        $admin_password = $_POST['admin_password'] ?? '';
        
        $passwordValid = false;
        
        if (password_verify($admin_password, $config['admin_hash'])) {
            $passwordValid = true;
        } elseif (hash('sha256', $admin_password) === $config['admin_hash']) {
            $passwordValid = true;
        }
        
        if ($passwordValid) {
            clearRateLimit('admin_login');
            session_regenerate_id(true);
            setAdminStatus(true);
            header("Location: " . $_SERVER['PHP_SELF']);
            exit();
        } else {
            recordFailedAttempt('admin_login');
            $admin_error = 'Falsches Admin-Passwort!';
            sleep(1);
        }
    }
}
?>
<link rel="stylesheet" href="css/navigation.css">
<header>
    <div class="nav-backdrop" id="nav-backdrop"></div>
    <nav>
        <div class="nav-header">
            <div class="nav-title-container">
                <?php 
                $logo_path = $config['logo_path'] ?? '';
                if (!empty($logo_path) && file_exists(__DIR__ . '/../' . $logo_path)): 
                ?>
                    <a href="dashboard.php"><img src="<?php echo htmlspecialchars($logo_path, ENT_QUOTES, 'UTF-8'); ?>" alt="Logo" class="nav-logo"></a>
                <?php endif; ?>
                <span class="nav-title"><?php echo $config['navigation_title']; ?>  <?php if ($is_admin): ?> - Admin <?php endif; ?></span>
            </div>
            <div class="nav-actions">
                <?php if ($hasUpdate): ?>
                    <a href="<?php echo htmlspecialchars($versionCheck['url'], ENT_QUOTES, 'UTF-8'); ?>" 
                       target="_blank" 
                       rel="noopener noreferrer"
                       class="version-notification" 
                       title="Neue Version <?php echo htmlspecialchars($versionCheck['version'], ENT_QUOTES, 'UTF-8'); ?> verfügbar! Klicken Sie, um zur GitHub-Seite zu gehen.">
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
            <li><a href="dashboard.php">Dashboard</a></li>
            <li><a href="manage_locations.php">Flugstandorte</a></li>
            <li><a href="view_flights.php">Alle Flüge anzeigen</a></li>
            <li><a href="battery_overview.php">Akku Übersicht</a></li>
            <li>
                <button class="nav-dropdown-toggle" id="nav-dropdown-toggle-btn">Verwaltung</button>
                <ul class="nav-dropdown" id="nav-dropdown">
                    <li><a href="add_flight.php">Manueller Eintrag</a></li>
                    <li><a href="delete_flights.php">Einträge löschen</a></li>
                    <li><a href="manage_pilots.php">Piloten verwalten</a></li>
                    <li><a href="manage_drones.php">Drohnen verwalten</a></li>
                    <li><a href="add_events.php">Dienst anlegen</a></li>
                    <li><a href="view_events.php">Dienste ansehen</a></li>
                    <li><a href="#" id="admin-modal-link">Admin</a></li>
                </ul>
            </li>
            <?php if (!empty($url)): ?>
                <li><a href="<?= htmlspecialchars($url, ENT_QUOTES, 'UTF-8') ?>">Einsatzdoku</a></li>
            <?php endif; ?>

            <li><a href="logout.php">Logout</a></li>
        </ul>
    </nav>
</header>

<!-- Admin Login Modal -->
<div id="admin-modal" class="modal">
    <div class="modal-content">
        <span class="close" id="admin-modal-close">&times;</span>
        <h2>Admin Login</h2>
        <form method="post" action="<?php echo $_SERVER['PHP_SELF']; ?>">
            <?php require_once __DIR__ . '/../includes/csrf.php'; csrf_field(); ?>
            <div class="form-group">
                <label for="admin_password">Passwort:</label>
                <input type="password" id="admin_password" name="admin_password" required>
            </div>
            <br>
            <br>
            <button type="submit">Als Admin anmelden</button>
            <?php if (isset($admin_error)): ?>
                <p class="admin-error"><?php echo htmlspecialchars($admin_error); ?></p>
            <?php endif; ?>
        </form>
    </div>
</div>

<script src="js/header.js"></script>
