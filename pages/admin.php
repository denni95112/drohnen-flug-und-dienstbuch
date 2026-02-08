<?php
/**
 * Admin page – database, logo, and configuration management
 */
require_once __DIR__ . '/../includes/error_reporting.php';
require_once __DIR__ . '/../includes/security_headers.php';
require_once __DIR__ . '/../includes/version.php';
require_once __DIR__ . '/../includes/utils.php';
require_once __DIR__ . '/../includes/auth.php';

requireAuth();
if (!isAdmin()) {
    header('Location: ../index.php');
    exit;
}

$configFile = __DIR__ . '/../config/config.php';
$config = include $configFile;
if (!is_array($config)) {
    $config = [];
}

if (isset($config['timezone'])) {
    date_default_timezone_set($config['timezone']);
}

$configPath = $configFile;
$databasePath = getDatabasePath();
$dbFile = $databasePath;

// Resolve DB file for display/operations (may be relative in config)
$configDbPath = $config['database_path'] ?? 'db/drone-dashboard-database.sqlite';
if (strpos($configDbPath, '/') !== 0 && (DIRECTORY_SEPARATOR === '\\' ? !preg_match('/^[A-Za-z]:[\\\\\\/]/', $configDbPath) : true)) {
    $dbFileForCheck = realpath(__DIR__ . '/../' . $configDbPath);
    if ($dbFileForCheck !== false) {
        $dbFile = $dbFileForCheck;
    }
}

// ---- Database download ----
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['download_db'])) {
    if (file_exists($databasePath)) {
        header('Content-Type: application/octet-stream');
        header('Content-Disposition: attachment; filename="flug-dienstbuch_backup_' . date('Y-m-d_His') . '.sqlite"');
        readfile($databasePath);
        exit;
    }
}

// ---- Database upload ----
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['db_upload']) && $_FILES['db_upload']['error'] === UPLOAD_ERR_OK) {
    require_once __DIR__ . '/../includes/csrf.php';
    verify_csrf();
    if (move_uploaded_file($_FILES['db_upload']['tmp_name'], $databasePath)) {
        header('Location: admin.php?success=db_hochgeladen');
        exit;
    }
}

// ---- Database delete ----
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_db'])) {
    require_once __DIR__ . '/../includes/csrf.php';
    verify_csrf();
    if (file_exists($databasePath)) {
        unlink($databasePath);
        header('Location: admin.php?success=db_geloescht');
        exit;
    }
}

// ---- Create database: redirect to setup_database with from_admin ----
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_db'])) {
    require_once __DIR__ . '/../includes/csrf.php';
    verify_csrf();
    header('Location: ../setup/setup_database.php?from_admin=1');
    exit;
}

// ---- Update unit name (navigation_title) ----
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_unit'])) {
    require_once __DIR__ . '/../includes/csrf.php';
    verify_csrf();
    $title = trim($_POST['navigation_title'] ?? '');
    if ($title !== '') {
        updateConfig('navigation_title', $title);
        header('Location: admin.php?success=einheit_geaendert');
        exit;
    }
}

// ---- Update admin password ----
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_admin_password'])) {
    require_once __DIR__ . '/../includes/csrf.php';
    verify_csrf();
    $newPassword = $_POST['admin_passwort'] ?? '';
    if (strlen($newPassword) >= 1) {
        $hash = password_hash($newPassword, PASSWORD_DEFAULT);
        updateConfig('admin_hash', $hash);
        header('Location: admin.php?success=admin_passwort_geaendert');
        exit;
    }
}

// ---- Update standard user password ----
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_password'])) {
    require_once __DIR__ . '/../includes/csrf.php';
    verify_csrf();
    $newPassword = $_POST['passwort'] ?? '';
    if (strlen($newPassword) >= 1) {
        $hash = password_hash($newPassword, PASSWORD_DEFAULT);
        updateConfig('password_hash', $hash);
        header('Location: admin.php?success=passwort_geaendert');
        exit;
    }
}

// ---- Logo upload ----
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['logo']) && $_FILES['logo']['error'] === UPLOAD_ERR_OK) {
    require_once __DIR__ . '/../includes/csrf.php';
    verify_csrf();
    $allowedTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif', 'image/svg+xml', 'image/webp'];
    $fileType = $_FILES['logo']['type'] ?? '';
    if (in_array($fileType, $allowedTypes)) {
        $uploadDir = __DIR__ . '/../uploads/logos/';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }
        $oldLogo = $config['logo_path'] ?? '';
        if ($oldLogo !== '') {
            $oldPath = __DIR__ . '/../' . $oldLogo;
            if (file_exists($oldPath)) {
                @unlink($oldPath);
            }
        }
        $ext = strtolower(pathinfo($_FILES['logo']['name'], PATHINFO_EXTENSION)) ?: 'png';
        $fileName = 'logo_' . time() . '_' . uniqid() . '.' . $ext;
        $filePath = $uploadDir . $fileName;
        if (move_uploaded_file($_FILES['logo']['tmp_name'], $filePath)) {
            updateConfig('logo_path', 'uploads/logos/' . $fileName);
            header('Location: admin.php?success=logo_geaendert');
            exit;
        }
    }
}

// ---- Logo delete ----
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_logo'])) {
    require_once __DIR__ . '/../includes/csrf.php';
    verify_csrf();
    $logoPath = $config['logo_path'] ?? '';
    if ($logoPath !== '') {
        $fullPath = __DIR__ . '/../' . $logoPath;
        if (file_exists($fullPath)) {
            @unlink($fullPath);
        }
        updateConfig('logo_path', '');
        header('Location: admin.php?success=logo_geloescht');
        exit;
    }
}

$logoFullPath = isset($config['logo_path']) && $config['logo_path'] !== ''
    ? (__DIR__ . '/../' . $config['logo_path'])
    : '';
$hasLogo = $logoFullPath !== '' && file_exists($logoFullPath);
$logoUrl = $hasLogo ? ('../' . $config['logo_path']) : '';
$dbExists = file_exists($databasePath);
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin – <?php echo htmlspecialchars($config['navigation_title'] ?? 'Flug-Dienstbuch'); ?></title>
    <link rel="stylesheet" href="../css/styles.css?v=<?php echo APP_VERSION; ?>">
    <link rel="stylesheet" href="../css/admin.css?v=<?php echo APP_VERSION; ?>">
    <link rel="manifest" href="../manifest.json">
    <?php require_once __DIR__ . '/../includes/csrf.php'; ?>
    <meta name="csrf-token" content="<?php echo htmlspecialchars($_SESSION['csrf_token'] ?? ''); ?>">
</head>
<body>
<?php include __DIR__ . '/../includes/header.php'; ?>
<main>
<h1>Admin Verwaltung</h1>

<?php if (isset($_GET['success'])): ?>
<div class="success-message">
    <?php
    $messages = [
        'db_hochgeladen' => 'Datenbank erfolgreich hochgeladen.',
        'db_geloescht' => 'Datenbankdatei gelöscht.',
        'db_neu_erstellt' => 'Datenbank wurde neu angelegt.',
        'einheit_geaendert' => 'Name der Einheit wurde geändert.',
        'admin_passwort_geaendert' => 'Admin-Passwort wurde geändert.',
        'passwort_geaendert' => 'Anmelde-Passwort wurde geändert.',
        'logo_geaendert' => 'Logo wurde hochgeladen.',
        'logo_geloescht' => 'Logo wurde gelöscht.',
    ];
    echo htmlspecialchars($messages[$_GET['success']] ?? 'Aktion erfolgreich.');
    ?>
</div>
<?php endif; ?>

<div class="admin-sections">
    <!-- Datenbank -->
    <section class="admin-section">
        <h2>Datenbank</h2>
        <div class="admin-actions">
            <form method="get" action="admin.php" class="admin-action-item">
                <input type="hidden" name="download_db" value="1">
                <button type="submit" class="btn-action" <?php echo $dbExists ? '' : 'disabled'; ?>>
                    Datenbank herunterladen
                </button>
            </form>
            <form method="post" action="admin.php" enctype="multipart/form-data" class="admin-action-item">
                <?php require_once __DIR__ . '/../includes/csrf.php'; csrf_field(); ?>
                <label class="file-input-label">
                    <input type="file" name="db_upload" accept=".sqlite,.db" class="file-input">
                    <span class="file-input-button">Datei wählen</span>
                    <span class="file-input-text">Keine Datei</span>
                </label>
                <button type="submit" class="btn-action">Datenbank hochladen</button>
            </form>
            <?php if (!$dbExists): ?>
            <form method="post" action="admin.php" class="admin-action-item">
                <?php csrf_field(); ?>
                <button type="submit" name="create_db" value="1" class="btn-action">Datenbank neu anlegen</button>
            </form>
            <?php endif; ?>
        </div>
        <?php if ($dbExists): ?>
        <form method="post" action="admin.php" class="admin-section-danger" onsubmit="return confirm('Datenbank wirklich löschen? Alle Daten gehen verloren.');">
            <?php csrf_field(); ?>
            <button type="submit" name="delete_db" value="1" class="btn-danger">Datenbank löschen</button>
        </form>
        <?php endif; ?>
    </section>

    <!-- Einstellungen -->
    <section class="admin-section">
        <h2>Einstellungen</h2>
        <form method="post" action="admin.php" class="admin-form-item">
            <?php csrf_field(); ?>
            <label for="navigation_title">Name der Einheit</label>
            <input type="text" id="navigation_title" name="navigation_title" value="<?php echo htmlspecialchars($config['navigation_title'] ?? ''); ?>" required>
            <button type="submit" name="update_unit" class="btn-action-inline">Speichern</button>
        </form>
        <form method="post" action="admin.php" class="admin-form-item">
            <?php csrf_field(); ?>
            <label for="admin_passwort">Admin-Passwort ändern</label>
            <input type="password" id="admin_passwort" name="admin_passwort" placeholder="Neues Passwort" required>
            <button type="submit" name="update_admin_password" class="btn-action-inline">Admin-Passwort speichern</button>
        </form>
        <form method="post" action="admin.php" class="admin-form-item">
            <?php csrf_field(); ?>
            <label for="passwort">Anmelde-Passwort ändern</label>
            <input type="password" id="passwort" name="passwort" placeholder="Neues Passwort" required>
            <button type="submit" name="update_password" class="btn-action-inline">Passwort speichern</button>
        </form>
    </section>

    <!-- Logo -->
    <section class="admin-section">
        <h2>Logo</h2>
        <?php if ($hasLogo): ?>
        <div class="current-logo">
            <p><strong>Aktuelles Logo:</strong></p>
            <div class="logo-preview">
                <img src="<?php echo htmlspecialchars($logoUrl); ?>" alt="Logo">
            </div>
        </div>
        <?php endif; ?>
        <form method="post" action="admin.php" enctype="multipart/form-data" class="admin-form-item">
            <?php csrf_field(); ?>
            <label class="file-input-label">
                <input type="file" name="logo" accept="image/jpeg,image/jpg,image/png,image/gif,image/svg+xml,image/webp" class="file-input">
                <span class="file-input-button">Logo wählen</span>
                <span class="file-input-text">Keine Datei</span>
            </label>
            <div class="logo-actions">
                <button type="submit" class="btn-action">Logo hochladen</button>
                <?php if ($hasLogo): ?>
                <button type="submit" name="delete_logo" value="1" class="btn-danger" onclick="return confirm('Logo wirklich löschen?');">Logo löschen</button>
                <?php endif; ?>
            </div>
        </form>
    </section>

    <!-- API Tokens -->
    <section class="admin-section">
        <h2>API-Tokens</h2>
        <p class="admin-description">Tokens ermöglichen den Zugriff auf die API ohne Anmeldung (z. B. für Einsatztagebuch-Integration). Den Token zeigen wir nur einmal nach dem Erstellen an – bitte sicher aufbewahren.</p>
        
        <div class="admin-form-item">
            <label for="token_name">Neuer Token</label>
            <div class="token-create-row">
                <input type="text" id="token_name" name="token_name" placeholder="Name (z. B. Einsatztagebuch)" maxlength="100">
                <input type="datetime-local" id="token_expires_at" name="expires_at" placeholder="Ablauf (optional)" title="Leer = kein Ablauf">
                <button type="button" id="create-token-btn" class="btn-action">Token erstellen</button>
            </div>
            <div id="token-create-message" class="token-message" style="display: none;"></div>
            <div id="token-create-result" class="token-result-box" style="display: none;">
                <p><strong>Token (nur einmal sichtbar – bitte kopieren und sicher aufbewahren):</strong></p>
                <div class="token-display-row">
                    <code id="new-token-value"></code>
                    <button type="button" id="copy-token-btn" class="btn-action-inline">Kopieren</button>
                </div>
            </div>
        </div>

        <h3>Vorhandene Tokens</h3>
        <div id="tokens-table-wrapper">
            <table class="admin-tokens-table" id="tokens-table">
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>Erstellt</th>
                        <th>Zuletzt genutzt</th>
                        <th>Läuft ab</th>
                        <th>Status</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody id="tokens-tbody">
                    <tr><td colspan="6" class="tokens-loading">Lade…</td></tr>
                </tbody>
            </table>
        </div>
    </section>

    <!-- Links -->
    <section class="admin-section">
        <h2>Weitere Verwaltung</h2>
        <div class="admin-actions">
            <a href="../updater/updater_page.php" class="btn-action">Update-Tool öffnen</a>
            <a href="changelog.php" class="btn-action">Changelog</a>
            <a href="about.php" class="btn-action">Über</a>
            <a href="migrations.php" class="btn-action">Datenbank-Update</a>
        </div>
    </section>
</div>

<p><a href="dashboard.php" class="back-link">← Zurück zum Dashboard</a></p>
</main>
<?php include __DIR__ . '/../includes/footer.php'; ?>

<script>window.basePath = '../';</script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    var inputs = document.querySelectorAll('.file-input');
    inputs.forEach(function(input) {
        var textSpan = input.parentElement.querySelector('.file-input-text');
        if (!textSpan) return;
        input.addEventListener('change', function() {
            if (this.files && this.files.length > 0) {
                textSpan.textContent = this.files[0].name;
                textSpan.style.color = '#0d9488';
            } else {
                textSpan.textContent = 'Keine Datei';
                textSpan.style.color = '';
            }
        });
    });

    // API Tokens
    var csrfToken = document.querySelector('meta[name="csrf-token"]') && document.querySelector('meta[name="csrf-token"]').getAttribute('content');
    var basePath = window.basePath || '';

    function showTokenMessage(el, text, isError) {
        el.style.display = 'block';
        el.textContent = text;
        el.className = 'token-message ' + (isError ? 'token-message-error' : 'token-message-success');
    }

    function loadTokens() {
        var tbody = document.getElementById('tokens-tbody');
        if (!tbody) return;
        tbody.innerHTML = '<tr><td colspan="6" class="tokens-loading">Lade…</td></tr>';
        fetch(basePath + 'api/admin.php?action=list_tokens', { credentials: 'same-origin' })
            .then(function(r) { return r.json(); })
            .then(function(data) {
                if (!data.success || !data.data || !data.data.tokens) {
                    tbody.innerHTML = '<tr><td colspan="6">Fehler beim Laden.</td></tr>';
                    return;
                }
                var tokens = data.data.tokens;
                if (tokens.length === 0) {
                    tbody.innerHTML = '<tr><td colspan="6">Keine Tokens vorhanden.</td></tr>';
                    return;
                }
                tbody.innerHTML = tokens.map(function(t) {
                    var created = t.created_at || '–';
                    var lastUsed = t.last_used_at || '–';
                    var expires = t.expires_at || '–';
                    var status = t.is_active ? 'Aktiv' : 'Widerrufen';
                    if (t.is_active && t.expires_at) {
                        var exp = new Date(t.expires_at.replace(' ', 'T'));
                        if (exp < new Date()) status = 'Abgelaufen';
                    }
                    var revokeBtn = t.is_active
                        ? '<button type="button" class="btn-danger btn-revoke" data-id="' + t.id + '">Widerrufen</button>'
                        : '–';
                    return '<tr><td>' + escapeHtml(t.name) + '</td><td>' + escapeHtml(created) + '</td><td>' + escapeHtml(lastUsed) + '</td><td>' + escapeHtml(expires) + '</td><td>' + escapeHtml(status) + '</td><td>' + revokeBtn + '</td></tr>';
                }).join('');
                tbody.querySelectorAll('.btn-revoke').forEach(function(btn) {
                    btn.addEventListener('click', function() {
                        if (!confirm('Token wirklich widerrufen? API-Zugriff mit diesem Token ist danach nicht mehr möglich.')) return;
                        var id = parseInt(btn.getAttribute('data-id'), 10);
                        var fd = new FormData();
                        fd.append('action', 'revoke_token');
                        fd.append('csrf_token', csrfToken);
                        fd.append('id', id);
                        fetch(basePath + 'api/admin.php', { method: 'POST', body: fd, credentials: 'same-origin' })
                            .then(function(r) { return r.json(); })
                            .then(function(data) {
                                if (data.success) loadTokens();
                                else showTokenMessage(document.getElementById('token-create-message'), data.error || 'Fehler', true);
                            });
                    });
                });
            })
            .catch(function() {
                tbody.innerHTML = '<tr><td colspan="6">Fehler beim Laden.</td></tr>';
            });
    }

    function escapeHtml(text) {
        if (text == null) return '';
        var div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

    if (document.getElementById('create-token-btn')) {
        document.getElementById('create-token-btn').addEventListener('click', function() {
            var nameEl = document.getElementById('token_name');
            var expiresEl = document.getElementById('token_expires_at');
            var msgEl = document.getElementById('token-create-message');
            var resultEl = document.getElementById('token-create-result');
            var tokenValEl = document.getElementById('new-token-value');
            var name = (nameEl && nameEl.value) ? nameEl.value.trim() : '';
            if (!name) {
                showTokenMessage(msgEl, 'Bitte einen Namen eingeben.', true);
                return;
            }
            resultEl.style.display = 'none';
            var fd = new FormData();
            fd.append('action', 'create_token');
            fd.append('csrf_token', csrfToken);
            fd.append('token_name', name);
            if (expiresEl && expiresEl.value) {
                fd.append('expires_at', expiresEl.value.replace('T', ' ') + ':00');
            }
            this.disabled = true;
            fetch(basePath + 'api/admin.php', { method: 'POST', body: fd, credentials: 'same-origin' })
                .then(function(r) { return r.json(); })
                .then(function(data) {
                    if (data.success && data.data && data.data.token) {
                        tokenValEl.textContent = data.data.token;
                        resultEl.style.display = 'block';
                        showTokenMessage(msgEl, 'Token erstellt. Bitte kopieren und sicher aufbewahren – er wird nicht erneut angezeigt.', false);
                        nameEl.value = '';
                        if (expiresEl) expiresEl.value = '';
                        loadTokens();
                    } else {
                        showTokenMessage(msgEl, data.error || 'Fehler beim Erstellen.', true);
                    }
                })
                .catch(function() {
                    showTokenMessage(msgEl, 'Netzwerkfehler.', true);
                })
                .then(function() {
                    document.getElementById('create-token-btn').disabled = false;
                });
        });
    }

    if (document.getElementById('copy-token-btn')) {
        document.getElementById('copy-token-btn').addEventListener('click', function() {
            var code = document.getElementById('new-token-value');
            if (!code || !code.textContent) return;
            if (navigator.clipboard && navigator.clipboard.writeText) {
                navigator.clipboard.writeText(code.textContent).then(function() {
                    var msg = document.getElementById('token-create-message');
                    showTokenMessage(msg, 'Token in Zwischenablage kopiert.', false);
                });
            } else {
                var sel = window.getSelection();
                var range = document.createRange();
                range.selectNodeContents(code);
                sel.removeAllRanges();
                sel.addRange(range);
                document.execCommand('copy');
                sel.removeAllRanges();
                var msg = document.getElementById('token-create-message');
                showTokenMessage(msg, 'Token kopiert.', false);
            }
        });
    }

    loadTokens();
});
</script>
</body>
</html>
