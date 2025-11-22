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
$dbPath = getDatabasePath();
$db = new SQLite3($dbPath);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['flight_id'])) {
    require_once __DIR__ . '/includes/csrf.php';
    verify_csrf();
    
    $flight_id = intval($_POST['flight_id']);
    // Flug löschen using prepared statement
    $stmt = $db->prepare("DELETE FROM flights WHERE id = :flight_id");
    $stmt->bindValue(':flight_id', $flight_id, SQLITE3_INTEGER);
    $stmt->execute();

    // Weiterleitung zur Seite, um die Tabelle zu aktualisieren
    header('Location: delete_flights.php');
    exit();
}

// Alle Flüge der letzten drei Monate abrufen
$stmt = $db->prepare("SELECT flights.id as flight_id, pilots.name as pilot_name, flights.flight_date FROM flights JOIN pilots ON flights.pilot_id = pilots.id WHERE flights.flight_date >= DATE('now', '-3 months') ORDER BY flights.flight_date DESC");
$flights = $stmt->execute();
?>

<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>M30T Flüge löschen - Drohnenpiloten</title>
    <link rel="stylesheet" href="css/styles.css?v=<?php echo APP_VERSION; ?>">
</head>
<body>
    <?php include 'includes/header.php'; ?>
    <main>
        <h1>Flüge löschen</h1>
        <table>
            <thead>
                <tr>
                    <th>Pilot</th>
                    <th>Flugdatum</th>
                    <th>Aktion</th>
                </tr>
            </thead>
            <tbody>
                <?php while ($row = $flights->fetchArray(SQLITE3_ASSOC)): ?>
                    <tr>
                        <td><?= htmlspecialchars($row['pilot_name']); ?></td>
                        <td><?= htmlspecialchars($row['flight_date']); ?></td>
                        <td>
                            <form method="post" action="delete_flights.php">
                                <?php require_once __DIR__ . '/includes/csrf.php'; csrf_field(); ?>
                                <input type="hidden" name="flight_id" value="<?= $row['flight_id']; ?>">
                                <button type="submit" class="button-full">Löschen</button>
                            </form>
                        </td>
                    </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
    </main>
    <?php include 'includes/footer.php'; ?>
</body>
</html>
