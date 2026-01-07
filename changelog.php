<?php
require_once __DIR__ . '/includes/error_reporting.php';
require_once __DIR__ . '/includes/security_headers.php';
require 'auth.php';
requireAuth();

$config = include __DIR__ . '/config/config.php';
if (isset($config['timezone'])) {
    date_default_timezone_set($config['timezone']);
}

require_once __DIR__ . '/version.php';
require_once __DIR__ . '/includes/changelog_data.php';
?>

<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Changelog - <?php echo $config['navigation_title']; ?></title>
    <link rel="stylesheet" href="css/styles.css?v=<?php echo APP_VERSION; ?>">
    <link rel="stylesheet" href="css/changelog.css?v=<?php echo APP_VERSION; ?>">
    <link rel="manifest" href="manifest.json">
</head>
<body>
    <?php include 'includes/header.php'; ?>
    <main>
        <h1>Changelog</h1>
        <p class="changelog-intro">Hier finden Sie eine Übersicht aller Änderungen und Updates der Anwendung.</p>
        
        <div class="changelog-container">
            <?php foreach ($changelog as $entry): ?>
                <div class="changelog-entry">
                    <div class="changelog-header">
                        <h2 class="changelog-version">Version <?php echo htmlspecialchars($entry['version']); ?></h2>
                        <span class="changelog-date"><?php echo htmlspecialchars($entry['date']); ?></span>
                    </div>
                    
                    <?php if (!empty($entry['new_features'])): ?>
                        <div class="changelog-section changelog-new-features">
                            <h3 class="changelog-section-title">Neue Features</h3>
                            <ul class="changelog-list">
                                <?php foreach ($entry['new_features'] as $feature): ?>
                                    <li><?php echo htmlspecialchars($feature); ?></li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    <?php endif; ?>
                    
                    <?php if (!empty($entry['bugfixes'])): ?>
                        <div class="changelog-section changelog-bugfixes">
                            <h3 class="changelog-section-title">Bugfixes</h3>
                            <ul class="changelog-list">
                                <?php foreach ($entry['bugfixes'] as $bugfix): ?>
                                    <li><?php echo htmlspecialchars($bugfix); ?></li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    <?php endif; ?>
                    
                    <?php if (!empty($entry['changes'])): ?>
                        <div class="changelog-section changelog-changes">
                            <h3 class="changelog-section-title">Änderungen</h3>
                            <ul class="changelog-list">
                                <?php foreach ($entry['changes'] as $change): ?>
                                    <li><?php echo htmlspecialchars($change); ?></li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        </div>
    </main>
    <?php include 'includes/footer.php'; ?>
</body>
</html>

