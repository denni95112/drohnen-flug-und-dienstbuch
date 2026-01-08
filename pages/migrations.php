<?php
/**
 * Database Migrations Page
 * Visible to all users, but only admins can execute migrations
 */

require_once __DIR__ . '/../includes/error_reporting.php';
require_once __DIR__ . '/../includes/security_headers.php';
require __DIR__ . '/../includes/auth.php';
requireAuth();

require_once __DIR__ . '/../includes/utils.php';
require_once __DIR__ . '/../includes/migration_runner.php';
require_once __DIR__ . '/../includes/version.php';

$dbPath = getDatabasePath();
$db = new SQLite3($dbPath);
$db->exec('PRAGMA foreign_keys = ON');

$isAdmin = isAdmin();
$migrationFiles = getMigrationFiles();
$executedMigrations = getExecutedMigrations($db);
$pendingMigrations = getPendingMigrations($db);

?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Datenbank Update</title>
    <link rel="stylesheet" href="../css/styles.css?v=<?php echo APP_VERSION; ?>">
    <style>
        .migration-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }
        .migration-table th,
        .migration-table td {
            padding: 10px;
            text-align: left;
            border-bottom: 1px solid #ddd;
        }
        .migration-table th {
            background-color: #f2f2f2;
            font-weight: bold;
        }
        .migration-status {
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 0.9em;
        }
        .status-executed {
            background-color: #d4edda;
            color: #155724;
        }
        .status-pending {
            background-color: #fff3cd;
            color: #856404;
        }
        .execute-btn {
            padding: 6px 12px;
            background-color: #007bff;
            color: white;
            border: none;
            border-radius: 4px;
            cursor: pointer;
        }
        .execute-btn:hover {
            background-color: #0056b3;
        }
        .execute-btn:disabled {
            background-color: #6c757d;
            cursor: not-allowed;
        }
        .error-message {
            color: #dc3545;
            margin-top: 10px;
        }
        .success-message {
            color: #28a745;
            margin-top: 10px;
        }
        .info-box {
            background-color: #e7f3ff;
            border: 1px solid #b3d9ff;
            padding: 15px;
            border-radius: 4px;
            margin-bottom: 20px;
        }
        .explanation-box {
            background-color: #f8f9fa;
            border: 1px solid #dee2e6;
            border-left: 4px solid #007bff;
            padding: 20px;
            border-radius: 4px;
            margin-bottom: 20px;
        }
        .explanation-box h3 {
            margin-top: 0;
            color: #007bff;
        }
        .explanation-box ul {
            margin: 10px 0;
            padding-left: 20px;
        }
        .explanation-box li {
            margin: 8px 0;
        }
    </style>
    <script src="../js/migrations.js"></script>
</head>
<body>
    <?php include __DIR__ . '/../includes/header.php'; ?>
    <main>
        <h1>Datenbank Update</h1>
        
        <div class="explanation-box">
            <h3>Was ist ein Datenbank-Update?</h3>
            <p>Ein Datenbank-Update ist eine Aktualisierung der internen Struktur, in der alle Ihre Daten gespeichert werden. Stellen Sie sich das wie eine Renovierung eines Hauses vor - die Möbel (Ihre Daten) bleiben erhalten, aber die Struktur wird verbessert.</p>
            
            <h3>Warum werden Updates benötigt?</h3>
            <ul>
                <li><strong>Neue Funktionen:</strong> Wenn neue Features zur Anwendung hinzugefügt werden, muss die Datenbank angepasst werden, um diese zu unterstützen</li>
                <li><strong>Verbesserungen:</strong> Updates können die Geschwindigkeit und Zuverlässigkeit der Anwendung verbessern</li>
                <li><strong>Sicherheit:</strong> Manchmal werden Updates benötigt, um die Sicherheit zu erhöhen</li>
                <li><strong>Fehlerbehebungen:</strong> Updates können Probleme beheben, die in früheren Versionen aufgetreten sind</li>
            </ul>
            
            <h3>Was passiert bei einem Update?</h3>
            <p>Bei einem Update werden automatisch Änderungen an der Datenbankstruktur vorgenommen. <strong>Ihre Daten bleiben dabei vollständig erhalten</strong> - es werden keine Flüge, Piloten oder andere Informationen gelöscht. Der Vorgang dauert normalerweise nur wenige Sekunden.</p>
            
            <p><strong>Wichtig:</strong> Updates sollten nur von einem Administrator ausgeführt werden. Wenn Sie kein Administrator sind, wenden Sie sich bitte an Ihren Administrator.</p>
        </div>
        
        <div class="info-box">
            <p><strong>Hinweis:</strong> Diese Seite ist für alle Benutzer sichtbar, aber nur Administratoren können Updates ausführen.</p>
            <?php if (count($pendingMigrations) > 0): ?>
                <p><strong>Es steht(en) <?= count($pendingMigrations); ?> ausstehende(s) Update(s) zur Verfügung.</strong></p>
                <p>Bitte führen Sie die Updates aus, um die neuesten Funktionen und Verbesserungen zu erhalten.</p>
            <?php else: ?>
                <p>Alle Updates wurden ausgeführt. Die Datenbank ist auf dem neuesten Stand.</p>
            <?php endif; ?>
        </div>
        
        <div id="message-container"></div>
        
        <table class="migration-table">
            <thead>
                <tr>
                    <th>Nummer</th>
                    <th>Update-Name</th>
                    <th>Status</th>
                    <th>Ausgeführt am</th>
                    <th>Aktionen</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($migrationFiles as $file): ?>
                    <?php 
                    $isExecuted = in_array($file['name'], $executedMigrations);
                    $executionInfo = null;
                    if ($isExecuted) {
                        $stmt = $db->prepare('SELECT executed_at, executed_by, execution_time_ms FROM schema_migrations WHERE migration_name = :name');
                        $stmt->bindValue(':name', $file['name'], SQLITE3_TEXT);
                        $result = $stmt->execute();
                        $executionInfo = $result->fetchArray(SQLITE3_ASSOC);
                    }
                    ?>
                    <tr>
                        <td><?= htmlspecialchars(str_pad($file['number'], 3, '0', STR_PAD_LEFT)); ?></td>
                        <td><?= htmlspecialchars($file['name']); ?></td>
                        <td>
                            <span class="migration-status <?= $isExecuted ? 'status-executed' : 'status-pending'; ?>">
                                <?= $isExecuted ? 'Ausgeführt' : 'Ausstehend'; ?>
                            </span>
                        </td>
                        <td>
                            <?php if ($executionInfo): ?>
                                <?= htmlspecialchars(toLocalTime($executionInfo['executed_at'])); ?>
                                <?php if ($executionInfo['executed_by']): ?>
                                    <br><small>von <?= htmlspecialchars($executionInfo['executed_by']); ?></small>
                                <?php endif; ?>
                                <?php if ($executionInfo['execution_time_ms']): ?>
                                    <br><small>(<?= htmlspecialchars($executionInfo['execution_time_ms']); ?>ms)</small>
                                <?php endif; ?>
                            <?php else: ?>
                                -
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if (!$isExecuted && $isAdmin): ?>
                                <button class="execute-btn" onclick="runMigration('<?= htmlspecialchars($file['name']); ?>')">
                                    Update ausführen
                                </button>
                            <?php elseif (!$isExecuted && !$isAdmin): ?>
                                <span style="color: #6c757d;">Admin erforderlich</span>
                            <?php else: ?>
                                <span style="color: #28a745;">✓ Bereits ausgeführt</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </main>
    <?php include __DIR__ . '/../includes/footer.php'; ?>
    
    <script>
        // Get CSRF token
        const csrfToken = '<?= htmlspecialchars($_SESSION['csrf_token'] ?? ''); ?>';
        // Get base path for API calls
        const basePath = window.basePath || '';
        
        function runMigration(migrationName) {
            if (!confirm('Möchten Sie dieses Datenbank-Update wirklich ausführen?\n\nHinweis: Ihre Daten bleiben dabei vollständig erhalten.')) {
                return;
            }
            
            const btn = event.target;
            btn.disabled = true;
            btn.textContent = 'Wird aktualisiert...';
            
            fetch(`${basePath}api/migrations.php?action=run`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    migration_name: migrationName,
                    csrf_token: csrfToken
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showMessage('Datenbank-Update erfolgreich ausgeführt!', 'success');
                    setTimeout(() => location.reload(), 1500);
                } else {
                    showMessage('Fehler: ' + (data.error || 'Unbekannter Fehler'), 'error');
                    btn.disabled = false;
                    btn.textContent = 'Update ausführen';
                }
            })
            .catch(error => {
                showMessage('Fehler: ' + error.message, 'error');
                btn.disabled = false;
                btn.textContent = 'Update ausführen';
            });
        }
        
        function showMessage(message, type) {
            const container = document.getElementById('message-container');
            container.innerHTML = '<div class="' + (type === 'success' ? 'success-message' : 'error-message') + '">' + message + '</div>';
            setTimeout(() => {
                container.innerHTML = '';
            }, 5000);
        }
    </script>
</body>
</html>

