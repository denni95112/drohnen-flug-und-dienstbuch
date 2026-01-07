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

// Check if user has admin privileges (from session)
$is_admin = isAdmin();

// Get the selected year, default to current year if not set (define before POST handler)
$selected_year = isset($_GET['year']) ? intval($_GET['year']) : date('Y');

// Handle flight deletion
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_flight_id'])) {
    require_once __DIR__ . '/includes/csrf.php';
    verify_csrf();
    
    if (!$is_admin) {
        http_response_code(403);
        die('Unauthorized');
    }
    
    $flight_id = intval($_POST['delete_flight_id']);
    $stmt = $db->prepare("DELETE FROM flights WHERE id = :flight_id");
    $stmt->bindValue(':flight_id', $flight_id, SQLITE3_INTEGER);
    $stmt->execute();
    header("Location: view_flights.php?year=$selected_year");
    exit();
}
if ($selected_year < 2024) {
    $selected_year = 2024; // Ensure the year is at least 2024
}

// Fetch all flights, including the drone name
$stmt = $db->prepare("
    SELECT 
        f.id AS flight_id,
        p.name AS pilot_name,
        f.flight_date,
        f.flight_end_date,
        l.location_name,
        l.latitude,
        l.longitude,
        l.description,
        d.drone_name AS drone_name
    FROM flights f
    JOIN pilots p ON f.pilot_id = p.id
    JOIN drones d ON f.drone_id = d.id
    LEFT JOIN flight_locations l ON f.flight_location_id = l.id
    WHERE strftime('%Y', f.flight_date) = :selected_year
    ORDER BY f.flight_date DESC
");
$stmt->bindValue(':selected_year', (string)$selected_year, SQLITE3_TEXT);
$flights = $stmt->execute();
if (!$flights) {
    error_log("Error in query: " . $db->lastErrorMsg());
    die("Fehler beim Laden der Flüge.");
}

// Use centralized toLocalTime function from utils.php
?>

<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Alle Flüge anzeigen</title>
    <link rel="stylesheet" href="css/styles.css?v=<?php echo APP_VERSION; ?>">
</head>
<body>
    <?php include 'includes/header.php'; ?>
    <main>
        <h1>Alle Flüge</h1>

        <!-- Year selection form -->
        <form method="get" action="view_flights.php" class="year-select">
            <label for="year">Jahr auswählen:</label>
            <select name="year" id="year">
                <?php
                // Generate options for years from 2024 to the current year
                for ($year = 2024; $year <= date('Y'); $year++) {
                    $selected = ($year == $selected_year) ? 'selected' : '';
                    echo "<option value='$year' $selected>$year</option>";
                }
                ?>
            </select>
            <button type="submit" class="button-full">Filter</button>
            <br>
        </form>

        <div class="table-responsive">
            <table>
                <thead>
                    <tr>
                        <th>Pilot</th>
                        <th>Flugdatum</th>
                        <th>Standortname</th>
                        <th>Breitengrad</th>
                        <th>Längengrad</th>
                        <th>Beschreibung</th>
                        <th>Drohne</th>
                        <th>Google Maps</th>
                        <?php if ($is_admin): ?>
                            <th>Aktion</th>
                        <?php endif; ?>
                    </tr>
                </thead>
                <tbody>
                    <?php while ($flight = $flights->fetchArray(SQLITE3_ASSOC)): ?>
                        <tr>
                            <td><?= htmlspecialchars($flight['pilot_name']); ?></td>
                            <td><?= htmlspecialchars(toLocalTime($flight['flight_date'])); ?> bis <?= htmlspecialchars(toLocalTime($flight['flight_end_date'])); ?></td>
                            <td><?= htmlspecialchars($flight['location_name'] ?? 'Nicht verfügbar'); ?></td>
                            <td><?= htmlspecialchars($flight['latitude'] ?? ''); ?></td>
                            <td><?= htmlspecialchars($flight['longitude'] ?? ''); ?></td>
                            <td><?= htmlspecialchars($flight['description'] ?? 'Keine Beschreibung'); ?></td>
                            <td><?= htmlspecialchars($flight['drone_name']); ?></td>
                            <td>
                                <?php if ($flight['latitude'] && $flight['longitude']): ?>
                                    <a href="https://www.google.com/maps?q=<?= $flight['latitude']; ?>,<?= $flight['longitude']; ?>" target="_blank">
                                        <button type="button">In Maps anzeigen</button>
                                    </a>
                                <?php else: ?>
                                    <button type="button" disabled>Kein Standort</button>
                                <?php endif; ?>
                            </td>
                            <?php if ($is_admin): ?>
                                <td>
                                    <form method="post" action="view_flights.php">
                                        <?php require_once __DIR__ . '/includes/csrf.php'; csrf_field(); ?>
                                        <input type="hidden" name="delete_flight_id" value="<?= $flight['flight_id']; ?>">
                                        <button type="submit" class="btn-delete">Löschen</button>
                                    </form>
                                </td>
                            <?php endif; ?>
                        </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>
    </main>
    <?php include 'includes/footer.php'; ?>
</body>
</html>
