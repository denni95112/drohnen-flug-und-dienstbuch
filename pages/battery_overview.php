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
$dbPath = getDatabasePath();
$db = new SQLite3($dbPath);

$stmt = $db->prepare("SELECT id, drone_name FROM drones ORDER BY id ASC");
$drone_result = $stmt->execute();

$drones = [];
while ($row = $drone_result->fetchArray(SQLITE3_ASSOC)) {
    $drones[$row['id']] = [
        'id' => $row['id'],
        'name' => $row['drone_name'],
        'batteries' => [],
        'total_flight_time' => 0
    ];
}

$stmt = $db->prepare("
    SELECT 
        f.drone_id,
        f.battery_number,
        COUNT(f.id) AS usage_count
    FROM flights f
    WHERE f.battery_number IS NOT NULL
    GROUP BY f.drone_id, f.battery_number
");
$battery_result = $stmt->execute();

while ($row = $battery_result->fetchArray(SQLITE3_ASSOC)) {
    if (isset($drones[$row['drone_id']])) {
        $drones[$row['drone_id']]['batteries'][] = [
            'battery_number' => $row['battery_number'],
            'usage_count' => $row['usage_count']
        ];
    }
}

$stmt = $db->prepare("
    SELECT 
        f.drone_id,
        SUM(
            CAST((julianday(f.flight_end_date) - julianday(f.flight_date)) * 24 * 60 * 60 AS INTEGER)
        ) AS total_seconds
    FROM flights f
    WHERE f.flight_end_date IS NOT NULL
    GROUP BY f.drone_id
");
$time_result = $stmt->execute();

while ($row = $time_result->fetchArray(SQLITE3_ASSOC)) {
    if (isset($drones[$row['drone_id']])) {
        $drones[$row['drone_id']]['total_flight_time'] = (int)$row['total_seconds'];
    }
}

/**
 * Format seconds into hours and minutes
 * @param int $seconds Total seconds
 * @return string Formatted duration string (e.g., "02h 30m")
 */
function formatDuration($seconds) {
    $hours = floor($seconds / 3600);
    $minutes = floor(($seconds % 3600) / 60);
    $secs = $seconds % 60;
    return sprintf("%02dh %02dm", $hours, $minutes);
}
?>

<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Batterienutzung Übersicht</title>
    <link rel="stylesheet" href="../css/battery_overview.css?v=<?php echo APP_VERSION; ?>">
    <link rel="stylesheet" href="../css/styles.css?v=<?php echo APP_VERSION; ?>">
</head>
<body>
    <?php include __DIR__ . '/../includes/header.php'; ?>
    <main>
        <h1>Akku Übersicht</h1>
        <div class="overview-container">
            <?php if (empty($drones)): ?>
                <div class="empty-state">
                    <p>Keine Drohnen verfügbar.</p>
                </div>
            <?php else: ?>
                <?php foreach ($drones as $drone): ?>
                    <div class="drone-section">
                        <div class="drone-header">
                            <h2><?= htmlspecialchars($drone['name']); ?></h2>
                            <div class="flight-time-badge">
                                <?= $drone['total_flight_time'] > 0 
                                    ? formatDuration($drone['total_flight_time']) 
                                    : 'Keine Flüge'; ?>
                            </div>
                        </div>
                        <div class="flight-time-info">
                            <strong>Gesamte Flugzeit:</strong> 
                            <?= $drone['total_flight_time'] > 0 
                                ? formatDuration($drone['total_flight_time']) 
                                : 'Keine Flüge aufgezeichnet'; ?>
                        </div>
                        <?php if (empty($drone['batteries'])): ?>
                            <div class="no-batteries">
                                <p>Keine Batterienutzung aufgezeichnet.</p>
                            </div>
                        <?php else: ?>
                            <div class="batteries-list">
                                <h3>Batterienutzung</h3>
                                <ul>
                                    <?php foreach ($drone['batteries'] as $battery): ?>
                                        <li class="battery-item">
                                            <span class="battery-number">
                                                Batterie <?= htmlspecialchars($battery['battery_number']); ?>
                                            </span>
                                            <span class="usage-count">
                                                <?= htmlspecialchars($battery['usage_count']); ?> Nutzungen
                                            </span>
                                        </li>
                                    <?php endforeach; ?>
                                </ul>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </main>
    <?php include __DIR__ . '/../includes/footer.php'; ?>
</body>
</html>
