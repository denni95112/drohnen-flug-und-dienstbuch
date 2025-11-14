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
$dbPath = getDatabasePath();
$db = new SQLite3($dbPath);
$error_message = ''; // Initialize error message variable

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['pilot_id'], $_POST['action'])) {
    require_once __DIR__ . '/includes/csrf.php';
    verify_csrf();
    
    $pilot_id = intval($_POST['pilot_id']);
    $action = $_POST['action'];
    $flight_date = date('Y-m-d H:i:s'); // Current timestamp

    if ($action === 'start') {
        // Handle flight start
        if (isset($_POST['drone_id'], $_POST['location_id'], $_POST['battery_number'])) {
            $drone_id = intval($_POST['drone_id']);
            $flight_location_id = intval($_POST['location_id']);
            $battery_number = intval($_POST['battery_number']);

            // Validate inputs
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
        // Handle flight end
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

// Function to calculate the next flight due date based on total flight time
function getNextDueDate($db, $pilot_id) {
    // Fetch the required total flight minutes for this pilot
    $stmt = $db->prepare("SELECT minutes_of_flights_needed FROM pilots WHERE id = :pilot_id");
    $stmt->bindValue(':pilot_id', $pilot_id, SQLITE3_INTEGER);
    $required_minutes = $stmt->execute()->fetchArray(SQLITE3_NUM)[0] ?? null;

    if (!$required_minutes) {
        $required_minutes = 60; // Default: 60 minutes every 3 months
    }

    $stmt = $db->prepare("SELECT flight_date, flight_end_date FROM flights WHERE pilot_id = :pilot_id AND flight_end_date IS NOT NULL AND flight_date >= DATE('now', '-6 months') ORDER BY flight_end_date DESC");
    $stmt->bindValue(':pilot_id', $pilot_id, SQLITE3_INTEGER);
    $flights = $stmt->execute();

    
    $total_minutes = 0;
    $last_counted_flight_date = null;
    
    while ($row = $flights->fetchArray(SQLITE3_ASSOC)) {
        $start_time = strtotime($row['flight_date']);
        $end_time   = strtotime($row['flight_end_date']);
        $duration   = ($end_time - $start_time) / 60; // minutes

        // calculate cutoff (3 months ago)
        $cutoff = strtotime("-3 months");

        if ($start_time >= $cutoff) {
            $total_minutes += $duration;
            
        }
        
        if($duration  <=  $required_minutes){
            $last_counted_flight_date = $row['flight_date'];
        }
        
        // Stop once required minutes are reached
        if ($total_minutes >= $required_minutes) {
            break;
        }
    }
    
    // Use the date of the last counted flight as the base
    $next_due_date = date('Y-m-d', strtotime($last_counted_flight_date . ' + 3 months'));

    return $next_due_date;
}


function getPilotFlightTime($db, $pilot_id) {
    $stmt = $db->prepare("SELECT flight_date, flight_end_date FROM flights WHERE pilot_id = :pilot_id AND flight_end_date IS NOT NULL AND flight_date >= DATE('now', '-3 months')");
    $stmt->bindValue(':pilot_id', $pilot_id, SQLITE3_INTEGER);
    $flights = $stmt->execute();

    $total_minutes = 0;

    while ($row = $flights->fetchArray(SQLITE3_ASSOC)) {
        $start_time = strtotime($row['flight_date']);
        $end_time   = strtotime($row['flight_end_date']);
        $duration   = ($end_time - $start_time) / 60; // minutes

        $total_minutes += $duration;
    }

    return round($total_minutes); 

}

// Fetch pilot data with ongoing flight information
$pilot_data = [];
$stmt = $db->prepare('SELECT * FROM pilots ORDER BY name');
$pilots = $stmt->execute();

while ($row = $pilots->fetchArray(SQLITE3_ASSOC)) {
    $pilot_id = $row['id'];

    // Check for ongoing flight
    $stmt = $db->prepare("SELECT id, flight_date FROM flights WHERE pilot_id = :pilot_id AND flight_end_date IS NULL ORDER BY flight_date DESC LIMIT 1");
    $stmt->bindValue(':pilot_id', $pilot_id, SQLITE3_INTEGER);
    $ongoing_result = $stmt->execute();
    $ongoing_flight = $ongoing_result->fetchArray(SQLITE3_ASSOC);

    // Calculate flight count for the last 3 months
    $stmt = $db->prepare("SELECT COUNT(*) FROM flights WHERE pilot_id = :pilot_id AND flight_date >= DATE('now', '-3 months')");
    $stmt->bindValue(':pilot_id', $pilot_id, SQLITE3_INTEGER);
    $flight_count = $stmt->execute()->fetchArray(SQLITE3_NUM)[0] ?? 0;

    // Calculate next flight due date
    $next_flight_due = getNextDueDate($db, $pilot_id);
    $flightTime = getPilotFlightTime($db, $pilot_id);
    // Add pilot data
    $pilot_data[] = [
        'id' => $row['id'],
        'name' => $row['name'],
        'flight_count' => $flightTime, // Add flight count
        'has_enough_flights' => $flight_count >= 3, // Check if the pilot has enough flights
        'next_flight_due' => $next_flight_due, // Add next flight due date
        'ongoing_flight' => $ongoing_flight, // Contains flight ID and date if ongoing
        'required_minutes' => $row['minutes_of_flights_needed']
    ];
}

function convertToLocalTime($utcTime) {
    global $config;
    $timezone = $config['timezone'] ?? 'Europe/Berlin';
    $date = new DateTime($utcTime, new DateTimeZone('UTC'));
    $date->setTimezone(new DateTimeZone($timezone));
    return $date->format('Y-m-d H:i:s');
}

?>

<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard</title>
    <link rel="stylesheet" href="css/styles.css">
    <link rel="manifest" href="manifest.json">
    <script>
    if ('serviceWorker' in navigator) {
        console.log("index service")
        navigator.serviceWorker.register('/service-worker.js')
            .then((registration) => {
                console.log('ServiceWorker registered:', registration);
            })
            .catch((error) => {
                console.error('ServiceWorker registration failed:', error);
            });
    }
    </script>
</head>
    <body>
        <?php include 'includes/header.php'; ?>
        <main>
            <h1>Dashboard</h1>

            <!-- Show error message if validation fails -->
            <?php if ($error_message): ?>
                <div style="color: red;"><?= htmlspecialchars($error_message); ?></div>
            <?php endif; ?>

            <div>
                <?php if (empty($pilot_data)): ?>
                    <h3>Willkommen zum Drohnenflug-Management Dashboard!</h3>
                    <p>Es scheint, als ob du dein Dashboard gerade erst erstellt hast. Diese Dinge müssen noch erledigt werden.</p>
                    <p>Vorweg ein paar Tips:</p>
                    <ul>
                        <li>Lege vor jedem Flug einen <a href="manage_locations.php">Flugstandort</a> an. Es werden nur die vom aktuellen Tag zur Auswahl gestellt. </li>
                        <li>Nutzt du auch die Einsatzdoku? Diese übernimmt automatisch den zuletzt hinzugefügten Flugstandort. Danach übernimmt die Doku alles, du musst hier in der Zeit nichts mehr machen</li>
                        <li>Flüge im Dashboard sollten am besten nur von einem Gerät gleichzeit gestartet und beendet werden.</li>
                        <li>Das Dashboard ist eine WebApp, anstannt eine Verknüfung zu erstllen, kannst du sie aus dem Browser heraus installieren.</li>
                        <li>Mit deinem Admin Passwort kannst du mehr Dinge tun.</li>
                        <li>Diese WebApp ist nun Open Source. Sie entstand für die Anforderungen an eine Drohnegruppe durch dessen Leiter. Passt sie nicht ganz zu dir, steht es dir frei sie anzupassen.</li>
                        <li>Diese Anwendung legt mehr Fokus aus Funktionaliät anstatt auf Aussehen</li>
                        <li>Schau regelmäßg bei GIT Hub vorbei, für Anleitungen und Updates</li>
                        <li>Sobald du Piloten eingefügt hast, verschwindet dieser Text</li>
                    </ul>
                    <p>Füge deine Drohnen unter <a href="manage_drones.php">Drohnen verwalten</a> hinzu</p>
                    <p>Füge deine Drohnenpiloten unter <a href="manage_pilots.php">Piloten verwalten</a> hinzu.</p>
                    <br>
                <?php endif; ?>
                <?php foreach ($pilot_data as $pilot): ?>
                    <?php 
                    // Determine color based on flight minutes requirement
                    $has_enough_minutes = $pilot['flight_count'] >= $pilot['required_minutes'];
                    $color_class = $has_enough_minutes ? 'bg-green' : 'bg-red';
                    ?>
                    <div class="pilot-container <?= $color_class; ?>  <?= $pilot['ongoing_flight'] ? 'bg-orange' : ''; ?>">
                        <h3><?= htmlspecialchars($pilot['name']);?></h3>
                        <?php if (!$pilot['ongoing_flight']): ?>
                            <p>Flugminuten der letzten 3 Monate: <?= htmlspecialchars($pilot['flight_count']); ?></p>
                            <p>Benötigte Flugminuten: <?= htmlspecialchars($pilot['required_minutes']); ?></p>
                            <?php if ($has_enough_minutes && isset($pilot['next_flight_due']) && strtotime($pilot['next_flight_due']) >= time()): ?>
                                <p>Nächster Flug fällig: <?= htmlspecialchars($pilot['next_flight_due'] ?? 'Nicht genug Flüge'); ?></p>
                            <?php endif; ?>
                        <?php endif; ?>
                        <?php if ($pilot['ongoing_flight']): ?>
                            <p>Flug gestartet um: <?= htmlspecialchars(convertToLocalTime($pilot['ongoing_flight']['flight_date'])); ?></p>
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
                                        $stmt = $db->prepare("SELECT id, location_name FROM flight_locations WHERE DATE(created_at) = DATE('now')");
                                        $locations = $stmt->execute();
                                        while ($location = $locations->fetchArray(SQLITE3_ASSOC)): ?>
                                            <option value="<?= $location['id']; ?>"><?= htmlspecialchars($location['location_name']); ?></option>
                                        <?php endwhile; ?>
                                    </select>
                                </div>
                                <br>
                                <div>
                                    <label for="drone_id_<?= $pilot['id']; ?>">Drohne</label>
                                    <select name="drone_id" id="drone_id_<?= $pilot['id']; ?>" required>
                                        <option value="">Bitte wählen</option>
                                        <?php
                                        $stmt = $db->prepare("SELECT id, drone_name FROM drones ORDER BY id");
                                        $drones = $stmt->execute();
                                        while ($drone = $drones->fetchArray(SQLITE3_ASSOC)): ?>
                                            <option value="<?= $drone['id']; ?>"><?= htmlspecialchars($drone['drone_name']); ?></option>
                                        <?php endwhile; ?>
                                    </select>
                                </div>
                                <br>
                                <div>
                                    <label for="battery_number_<?= $pilot['id']; ?>">Batterienummer</label>
                                    <input type="number" id="battery_number_<?= $pilot['id']; ?>" name="battery_number" min="1" required>
                                </div>
                                <br>
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