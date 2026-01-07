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
$error_message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['pilot_id'], $_POST['action'])) {
    require_once __DIR__ . '/includes/csrf.php';
    verify_csrf();
    
    $pilot_id = intval($_POST['pilot_id']);
    $action = $_POST['action'];
    // Get current time in UTC
    $flight_date = getCurrentUTC();

    if ($action === 'start') {
        if (isset($_POST['drone_id'], $_POST['location_id'], $_POST['battery_number'])) {
            $drone_id = intval($_POST['drone_id']);
            $flight_location_id = intval($_POST['location_id']);
            $battery_number = intval($_POST['battery_number']);

            if ($battery_number <= 0) {
                $error_message = "Bitte geben Sie eine gültige Batterienummer ein.";
            } else {
                $stmt = $db->prepare("INSERT INTO flights (pilot_id, flight_date, flight_location_id, drone_id, battery_number) VALUES (:pilot_id, :flight_date, :flight_location_id, :drone_id, :battery_number)");
                $stmt->bindValue(':pilot_id', $pilot_id, SQLITE3_INTEGER);
                $stmt->bindValue(':flight_date', $flight_date, SQLITE3_TEXT);
                $stmt->bindValue(':flight_location_id', $flight_location_id, SQLITE3_INTEGER);
                $stmt->bindValue(':drone_id', $drone_id, SQLITE3_INTEGER);
                $stmt->bindValue(':battery_number', $battery_number, SQLITE3_INTEGER);
                $result = $stmt->execute();

                if (!$result) {
                    $error_message = "Fehler beim Starten des Flugs.";
                    error_log("Flight start error: " . $db->lastErrorMsg());
                }
            }
        } else {
            $error_message = "Alle Felder sind erforderlich, um den Flug zu starten.";
        }
    } elseif ($action === 'end') {
        if (isset($_POST['flight_id'])) {
            $flight_id = intval($_POST['flight_id']);

            $stmt = $db->prepare("UPDATE flights SET flight_end_date = :flight_end_date WHERE id = :flight_id");
            $stmt->bindValue(':flight_end_date', $flight_date, SQLITE3_TEXT);
            $stmt->bindValue(':flight_id', $flight_id, SQLITE3_INTEGER);
            $result = $stmt->execute();

            if (!$result) {
                $error_message = "Fehler beim Beenden des Flugs.";
                error_log("Flight end error: " . $db->lastErrorMsg());
            }
        } else {
            $error_message = "Flug-ID fehlt.";
        }
    }
}

/**
 * Calculate the next flight due date based on total flight time
 * @param SQLite3 $db Database connection
 * @param int $pilot_id Pilot ID
 * @return string Next flight due date in Y-m-d format
 */
function getNextDueDate($db, $pilot_id) {
    $stmt = $db->prepare("SELECT minutes_of_flights_needed FROM pilots WHERE id = :pilot_id");
    $stmt->bindValue(':pilot_id', $pilot_id, SQLITE3_INTEGER);
    $required_minutes = $stmt->execute()->fetchArray(SQLITE3_NUM)[0] ?? null;

    if (!$required_minutes) {
        $required_minutes = 60;
    }

    // Calculate cutoff date (3 months ago) in UTC
    $cutoffDate = new DateTime('now', new DateTimeZone('UTC'));
    $cutoffDate->modify('-6 months');
    $cutoffDateUTC = $cutoffDate->format('Y-m-d H:i:s');
    
    $stmt = $db->prepare("SELECT flight_date, flight_end_date FROM flights WHERE pilot_id = :pilot_id AND flight_end_date IS NOT NULL AND flight_date >= :cutoff_date ORDER BY flight_end_date DESC");
    $stmt->bindValue(':pilot_id', $pilot_id, SQLITE3_INTEGER);
    $stmt->bindValue(':cutoff_date', $cutoffDateUTC, SQLITE3_TEXT);
    $flights = $stmt->execute();

    $total_minutes = 0;
    $last_counted_flight_date = null;
    
    // Calculate 3 months cutoff in UTC
    $threeMonthsCutoff = new DateTime('now', new DateTimeZone('UTC'));
    $threeMonthsCutoff->modify('-3 months');
    $threeMonthsCutoffUTC = $threeMonthsCutoff->format('Y-m-d H:i:s');
    
    while ($row = $flights->fetchArray(SQLITE3_ASSOC)) {
        // Parse UTC dates
        $start_date = new DateTime($row['flight_date'], new DateTimeZone('UTC'));
        $end_date = new DateTime($row['flight_end_date'], new DateTimeZone('UTC'));
        $duration = ($end_date->getTimestamp() - $start_date->getTimestamp()) / 60;

        if ($start_date->format('Y-m-d H:i:s') >= $threeMonthsCutoffUTC) {
            $total_minutes += $duration;
        }
        
        if($duration <= $required_minutes){
            $last_counted_flight_date = $row['flight_date'];
        }
        
        if ($total_minutes >= $required_minutes) {
            break;
        }
    }
    
    if ($last_counted_flight_date) {
        // Parse UTC date, add 3 months, convert to local date
        $lastDate = new DateTime($last_counted_flight_date, new DateTimeZone('UTC'));
        $lastDate->modify('+3 months');
        $next_due_date = toLocalTime($lastDate->format('Y-m-d H:i:s'), 'Y-m-d');
    } else {
        $next_due_date = null;
    }

    return $next_due_date;
}

/**
 * Get total flight time in minutes for a pilot in the last 3 months
 * @param SQLite3 $db Database connection
 * @param int $pilot_id Pilot ID
 * @return int Total flight minutes rounded
 */
function getPilotFlightTime($db, $pilot_id) {
    // Calculate cutoff date (3 months ago) in UTC
    $cutoffDate = new DateTime('now', new DateTimeZone('UTC'));
    $cutoffDate->modify('-3 months');
    $cutoffDateUTC = $cutoffDate->format('Y-m-d H:i:s');
    
    $stmt = $db->prepare("SELECT flight_date, flight_end_date FROM flights WHERE pilot_id = :pilot_id AND flight_end_date IS NOT NULL AND flight_date >= :cutoff_date");
    $stmt->bindValue(':pilot_id', $pilot_id, SQLITE3_INTEGER);
    $stmt->bindValue(':cutoff_date', $cutoffDateUTC, SQLITE3_TEXT);
    $flights = $stmt->execute();

    $total_minutes = 0;

    while ($row = $flights->fetchArray(SQLITE3_ASSOC)) {
        // Parse UTC dates
        $start_date = new DateTime($row['flight_date'], new DateTimeZone('UTC'));
        $end_date = new DateTime($row['flight_end_date'], new DateTimeZone('UTC'));
        $duration = ($end_date->getTimestamp() - $start_date->getTimestamp()) / 60;

        $total_minutes += $duration;
    }

    return round($total_minutes);
}

$pilot_data = [];
$stmt = $db->prepare('SELECT * FROM pilots ORDER BY name');
$pilots = $stmt->execute();

while ($row = $pilots->fetchArray(SQLITE3_ASSOC)) {
    $pilot_id = $row['id'];

    $stmt = $db->prepare("SELECT id, flight_date FROM flights WHERE pilot_id = :pilot_id AND flight_end_date IS NULL ORDER BY flight_date DESC LIMIT 1");
    $stmt->bindValue(':pilot_id', $pilot_id, SQLITE3_INTEGER);
    $ongoing_result = $stmt->execute();
    $ongoing_flight = $ongoing_result->fetchArray(SQLITE3_ASSOC);

    // Calculate cutoff date (3 months ago) in UTC
    $cutoffDate = new DateTime('now', new DateTimeZone('UTC'));
    $cutoffDate->modify('-3 months');
    $cutoffDateUTC = $cutoffDate->format('Y-m-d H:i:s');
    
    $stmt = $db->prepare("SELECT COUNT(*) FROM flights WHERE pilot_id = :pilot_id AND flight_date >= :cutoff_date");
    $stmt->bindValue(':pilot_id', $pilot_id, SQLITE3_INTEGER);
    $stmt->bindValue(':cutoff_date', $cutoffDateUTC, SQLITE3_TEXT);
    $flight_count = $stmt->execute()->fetchArray(SQLITE3_NUM)[0] ?? 0;

    $next_flight_due = getNextDueDate($db, $pilot_id);
    $flightTime = getPilotFlightTime($db, $pilot_id);
    $pilot_data[] = [
        'id' => $row['id'],
        'name' => $row['name'],
        'flight_count' => $flightTime,
        'has_enough_flights' => $flight_count >= 3,
        'next_flight_due' => $next_flight_due,
        'ongoing_flight' => $ongoing_flight,
        'required_minutes' => $row['minutes_of_flights_needed']
    ];
}

// Use centralized toLocalTime function from utils.php

?>

<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard</title>
    <link rel="stylesheet" href="css/styles.css?v=<?php echo APP_VERSION; ?>">
    <link rel="stylesheet" href="css/dashboard.css?v=<?php echo APP_VERSION; ?>">
    <link rel="manifest" href="manifest.json">
    <script src="js/dashboard.js"></script>
</head>
    <body>
        <?php include 'includes/header.php'; ?>
        <main>
            <?php if (!empty($pilot_data)): ?>
            <h1>Dashboard</h1>
            <?php endif; ?>

            <!-- Show error message if validation fails -->
            <?php if ($error_message): ?>
                <div class="error-message"><?= htmlspecialchars($error_message); ?></div>
            <?php endif; ?>

            <div>
                <?php if (empty($pilot_data)): ?>
                    <div class="welcome-section">
                        <h3>Willkommen zum Drohnenflug-Management Dashboard!</h3>
                        <p>Es scheint, als ob du dein Dashboard gerade erst erstellt hast. Diese Dinge müssen noch erledigt werden.</p>
                        <p>Vorweg ein paar Tips:</p>
                        <ul class="welcome-tips">
                            <li>Lege vor jedem Flug einen <a href="manage_locations.php">Flugstandort</a> an. Es werden nur die vom aktuellen Tag zur Auswahl gestellt.</li>
                            <li>Nutzt du auch die Einsatzdoku? Diese übernimmt automatisch den zuletzt hinzugefügten Flugstandort. Danach übernimmt die Doku alles, du musst hier in der Zeit nichts mehr machen</li>
                            <li>Flüge im Dashboard sollten am besten nur von einem Gerät gleichzeit gestartet und beendet werden.</li>
                            <li>Das Dashboard ist eine WebApp, anstannt eine Verknüfung zu erstllen, kannst du sie aus dem Browser heraus installieren.</li>
                            <li>Mit deinem Admin Passwort kannst du mehr Dinge tun.</li>
                            <li>Diese WebApp ist nun Open Source. Sie entstand für die Anforderungen an eine Drohnegruppe durch dessen Leiter. Passt sie nicht ganz zu dir, steht es dir frei sie anzupassen.</li>
                            <li>Diese Anwendung legt mehr Fokus aus Funktionaliät anstatt auf Aussehen</li>
                            <li>Schau regelmäßg bei GIT Hub vorbei, für Anleitungen und Updates</li>
                            <li>Sobald du Piloten eingefügt hast, verschwindet dieser Text</li>
                        </ul>
                        <div class="welcome-actions">
                            <p>Füge deine Drohnen unter <a href="manage_drones.php">Drohnen verwalten</a> hinzu</p>
                            <p>Füge deine Drohnenpiloten unter <a href="manage_pilots.php">Piloten verwalten</a> hinzu.</p>
                        </div>
                    </div>
                <?php endif; ?>
                <?php foreach ($pilot_data as $pilot): ?>
                    <?php 
                    $has_enough_minutes = $pilot['flight_count'] >= $pilot['required_minutes'];
                    $color_class = $has_enough_minutes ? 'bg-green' : 'bg-red';
                    ?>
                    <div class="pilot-container <?= $color_class; ?>  <?= $pilot['ongoing_flight'] ? 'bg-orange' : ''; ?>">
                        <h3><?= htmlspecialchars($pilot['name']);?></h3>
                        <?php if (!$pilot['ongoing_flight']): ?>
                            <p>Flugminuten der letzten 3 Monate: <?= htmlspecialchars($pilot['flight_count']); ?></p>
                            <p>Benötigte Flugminuten: <?= htmlspecialchars($pilot['required_minutes']); ?></p>
                            <?php if ($has_enough_minutes && isset($pilot['next_flight_due']) && $pilot['next_flight_due']): ?>
                                <?php
                                // Convert local date to UTC for comparison
                                $nextDueUTC = toUTC($pilot['next_flight_due'] . ' 00:00:00');
                                $currentUTC = getCurrentUTC();
                                if ($nextDueUTC >= $currentUTC): ?>
                                    <p>Nächster Flug fällig: <?= htmlspecialchars($pilot['next_flight_due']); ?></p>
                                <?php endif; ?>
                            <?php endif; ?>
                        <?php endif; ?>
                        <?php if ($pilot['ongoing_flight']): ?>
                            <p>Flug gestartet um: <?= htmlspecialchars(toLocalTime($pilot['ongoing_flight']['flight_date'])); ?></p>
                            <br><br>
                        <?php endif; ?>

                        <form method="post" action="dashboard.php">
                            <?php require_once __DIR__ . '/includes/csrf.php'; csrf_field(); ?>
                            <input type="hidden" name="pilot_id" value="<?= $pilot['id']; ?>">

                            <?php if ($pilot['ongoing_flight']): ?>
                                <input type="hidden" name="flight_id" value="<?= $pilot['ongoing_flight']['id']; ?>">
                                <input type="hidden" name="action" value="end">
                                <button type="submit">Flug beenden</button>
                            <?php else: ?>
                                <input type="hidden" name="action" value="start">
                                <div>
                                    <label for="location_id_<?= $pilot['id']; ?>">Standort</label>
                                    <select name="location_id" id="location_id_<?= $pilot['id']; ?>" required>
                                        <option value="">Bitte wählen</option>
                                        <?php
                                        // Get current date in UTC for comparison
                                        $currentDateUTC = getCurrentUTC();
                                        $currentDateLocal = toLocalTime($currentDateUTC, 'Y-m-d');
                                        // Convert local date to UTC for database comparison
                                        $currentDateUTCStart = toUTC($currentDateLocal . ' 00:00:00');
                                        $currentDateUTCEnd = toUTC($currentDateLocal . ' 23:59:59');
                                        
                                        // Check if table exists and prepare query
                                        $tableExists = $db->querySingle("SELECT name FROM sqlite_master WHERE type='table' AND name='flight_locations'");
                                        $locations = false;
                                        if ($tableExists) {
                                            $stmt = $db->prepare("SELECT id, location_name FROM flight_locations WHERE created_at >= :start_date AND created_at <= :end_date");
                                            if ($stmt !== false) {
                                                $stmt->bindValue(':start_date', $currentDateUTCStart, SQLITE3_TEXT);
                                                $stmt->bindValue(':end_date', $currentDateUTCEnd, SQLITE3_TEXT);
                                                $locations = $stmt->execute();
                                            } else {
                                                error_log("Failed to prepare location query: " . $db->lastErrorMsg());
                                            }
                                        }
                                        
                                        if ($locations !== false):
                                            while ($location = $locations->fetchArray(SQLITE3_ASSOC)): ?>
                                                <option value="<?= $location['id']; ?>"><?= htmlspecialchars($location['location_name']); ?></option>
                                            <?php endwhile;
                                        endif; ?>
                                    </select>
                                </div>
                                <div>
                                    <label for="drone_id_<?= $pilot['id']; ?>">Drohne</label>
                                    <select name="drone_id" id="drone_id_<?= $pilot['id']; ?>" required>
                                        <option value="">Bitte wählen</option>
                                        <?php
                                        // Check if table exists and prepare query
                                        $dronesTableExists = $db->querySingle("SELECT name FROM sqlite_master WHERE type='table' AND name='drones'");
                                        $drones = false;
                                        if ($dronesTableExists) {
                                            $stmt = $db->prepare("SELECT id, drone_name FROM drones ORDER BY id");
                                            if ($stmt !== false) {
                                                $drones = $stmt->execute();
                                            } else {
                                                error_log("Failed to prepare drones query: " . $db->lastErrorMsg());
                                            }
                                        }
                                        
                                        if ($drones !== false):
                                            while ($drone = $drones->fetchArray(SQLITE3_ASSOC)): ?>
                                                <option value="<?= $drone['id']; ?>"><?= htmlspecialchars($drone['drone_name']); ?></option>
                                            <?php endwhile;
                                        endif; ?>
                                    </select>
                                </div>
                                <div>
                                    <label for="battery_number_<?= $pilot['id']; ?>">Batterienummer</label>
                                    <input type="number" id="battery_number_<?= $pilot['id']; ?>" name="battery_number" min="1" required>
                                </div>
                                <button type="submit">Flug beginnen</button>
                            <?php endif; ?>
                        </form>
                    </div>
                <?php endforeach; ?>
            </div>

        </main>
        <?php include 'includes/footer.php'; ?>
    </body>
</html>