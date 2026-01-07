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

$is_admin = isset($_GET['admin']) && $_GET['admin'] === 'true';

// Note: POST handling has been moved to api/flights.php
// This page now only renders HTML. Data is fetched via API in add_flight.js
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
        <!-- Message containers -->
        <div id="error-container" class="error" style="display: none;"></div>
        <div id="success-container" class="success" style="display: none;"></div>
        
        <form id="add-flight-form">
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