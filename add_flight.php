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
require_once __DIR__ . '/version.php';
$dbPath = getDatabasePath();
$db = new SQLite3($dbPath);

$is_admin = isset($_GET['admin']) && $_GET['admin'] === 'true';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['pilot_id'], $_POST['flight_date'], $_POST['flight_end_date'], $_POST['drone_id'], $_POST['battery_number'])) {
    require_once __DIR__ . '/includes/csrf.php';
    verify_csrf();
    
    $pilot_id = intval($_POST['pilot_id']);
    $flight_date = $_POST['flight_date'];
    $flight_end_date = $_POST['flight_end_date'];
    $drone_id = intval($_POST['drone_id']);
    $battery_number = intval($_POST['battery_number']);

    $location_id = isset($_POST['location_id']) ? intval($_POST['location_id']) : NULL;

    if ($battery_number <= 0) {
        $error_message = 'Bitte geben Sie eine gültige Batterienummer ein.';
    } elseif (strtotime($flight_end_date) <= strtotime($flight_date)) {
        $error_message = 'Das Enddatum muss nach dem Startdatum liegen.';
    } else {
        // Convert local datetime to UTC for storage
        $flight_date_db = toUTC($flight_date);
        $flight_end_date_db = toUTC($flight_end_date);
        
        $stmt = $db->prepare("INSERT INTO flights (pilot_id, flight_date, flight_end_date, flight_location_id, drone_id, battery_number) VALUES (:pilot_id, :flight_date, :flight_end_date, :location_id, :drone_id, :battery_number)");
        $stmt->bindValue(':pilot_id', $pilot_id, SQLITE3_INTEGER);
        $stmt->bindValue(':flight_date', $flight_date_db, SQLITE3_TEXT);
        $stmt->bindValue(':flight_end_date', $flight_end_date_db, SQLITE3_TEXT);
        $stmt->bindValue(':location_id', $location_id, $location_id ? SQLITE3_INTEGER : SQLITE3_NULL);
        $stmt->bindValue(':drone_id', $drone_id, SQLITE3_INTEGER);
        $stmt->bindValue(':battery_number', $battery_number, SQLITE3_INTEGER);
        $result = $stmt->execute();
        
        if (!$result) {
            $error_message = 'Fehler beim Eintragen des Flugs.';
            error_log("Flight insert error: " . $db->lastErrorMsg());
        } else {
            $stmt = $db->prepare("UPDATE pilots SET last_flight = :flight_date WHERE id = :pilot_id");
            $stmt->bindValue(':flight_date', $flight_date_db, SQLITE3_TEXT);
            $stmt->bindValue(':pilot_id', $pilot_id, SQLITE3_INTEGER);
            $stmt->execute();
        }
    }
}

$stmt = $db->prepare('SELECT * FROM pilots ORDER BY name');
$pilots = $stmt->execute();

$stmt = $db->prepare('SELECT * FROM drones ORDER BY id');
$drones = $stmt->execute();
?>


<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Flug hinzufügen - Drohnenpiloten</title>
    <link rel="stylesheet" href="css/add_flight.css?v=<?php echo APP_VERSION; ?>">
    <link rel="stylesheet" href="css/styles.css?v=<?php echo APP_VERSION; ?>">
    <script src="js/add_flight.js"></script>
</head>
<body>
    <?php include 'includes/header.php'; ?>
    <main>
        <h1>Flug hinzufügen</h1>
        <?php if (isset($error_message)): ?>
            <div class="error"><?= htmlspecialchars($error_message); ?></div>
        <?php endif; ?>
        <form method="post" action="add_flight.php">
            <?php require_once __DIR__ . '/includes/csrf.php'; csrf_field(); ?>
            <div>
                <label for="pilot_id">Pilot</label>
                <select name="pilot_id" id="pilot_id" required>
                    <?php while ($row = $pilots->fetchArray(SQLITE3_ASSOC)): ?>
                        <option value="<?= $row['id']; ?>"><?= htmlspecialchars($row['name']); ?></option>
                    <?php endwhile; ?>
                </select>
            </div>
            <br>
            <div>
                <label for="flight_date">Startdatum und -zeit</label>
                <input type="datetime-local" id="flight_date" name="flight_date" required>
            </div>
            <br>
            <div>
                <label for="flight_end_date">Enddatum und -zeit</label>
                <input type="datetime-local" id="flight_end_date" name="flight_end_date" required>
            </div>
            <br>
            <div>
                <label for="drone_id">Drohne</label>
                <select name="drone_id" id="drone_id" required>
                    <option value="">Bitte wählen</option>
                    <?php while ($drone = $drones->fetchArray(SQLITE3_ASSOC)): ?>
                        <option value="<?= $drone['id']; ?>"><?= htmlspecialchars($drone['drone_name']); ?></option>
                    <?php endwhile; ?>
                </select>
            </div>
            <br>
            <div>
                <label for="battery_number">Batterienummer</label>
                <input type="number" id="battery_number" name="battery_number" min="1" required>
            </div>
            <br>
            <div>
                <label for="location_id">Standort (optional)</label>
                <select name="location_id" id="location_id">
                    <option value="">Kein Standort</option>
                </select>
            </div>
            <br>
            <button type="submit">Flug eintragen</button>
        </form>
    </main>
    <?php include 'includes/footer.php'; ?>

</body>
</html>