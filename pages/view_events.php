<?php
require_once __DIR__ . '/../includes/error_reporting.php';
require_once __DIR__ . '/../includes/security_headers.php';
require __DIR__ . '/../includes/auth.php';
requireAuth();

// Set timezone from config
$config = include __DIR__ . '/../config/config.php';
if (isset($config['timezone'])) {
    date_default_timezone_set($config['timezone']);
}

require_once __DIR__ . '/../includes/utils.php';
require_once __DIR__ . '/../includes/version.php';
$dbPath = getDatabasePath();
$db = new SQLite3($dbPath);

// Check if user has admin privileges
$is_admin = isAdmin();

// Get the selected year, default to the current year
$selected_year = isset($_GET['year']) ? intval($_GET['year']) : date('Y');

// Handle event deletion
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_event_id'])) {
    require_once __DIR__ . '/../includes/csrf.php';
    verify_csrf();
    
    if (!$is_admin) {
        http_response_code(403);
        die('Unauthorized');
    }
    
    $event_id = intval($_POST['delete_event_id']);
    
    // Delete pilot_events associations first
    $stmt = $db->prepare("DELETE FROM pilot_events WHERE event_id = :event_id");
    $stmt->bindValue(':event_id', $event_id, SQLITE3_INTEGER);
    $stmt->execute();
    
    // Delete the event
    $stmt = $db->prepare("DELETE FROM events WHERE id = :event_id");
    $stmt->bindValue(':event_id', $event_id, SQLITE3_INTEGER);
    $stmt->execute();
    
    header("Location: view_events.php?year=$selected_year");
    exit();
}

// Handle event update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_event_id'])) {
    require_once __DIR__ . '/../includes/csrf.php';
    verify_csrf();
    
    if (!$is_admin) {
        http_response_code(403);
        die('Unauthorized');
    }
    
    $event_id = intval($_POST['edit_event_id']);
    $event_start_date = $_POST['event_start_date'];
    $event_end_date = $_POST['event_end_date'];
    $type_id = intval($_POST['type_id']);
    $notes = trim($_POST['notes']);
    $pilot_ids = isset($_POST['pilot_ids']) ? $_POST['pilot_ids'] : [];
    
    if (empty($event_start_date) || empty($event_end_date) || empty($type_id) || empty($notes)) {
        $error_message = 'Bitte füllen Sie alle erforderlichen Felder aus.';
    } elseif (!in_array($type_id, [1, 2, 3])) {
        $error_message = 'Ungültiger Ereignistyp.';
    } elseif (strtotime($event_end_date) <= strtotime($event_start_date)) {
        $error_message = 'Das Enddatum muss nach dem Startdatum liegen.';
    } elseif (empty($pilot_ids)) {
        $error_message = 'Bitte mindestens einen Piloten auswählen.';
    } else {
        $event_start_date_utc = toUTC($event_start_date);
        $event_end_date_utc = toUTC($event_end_date);
        
        // Update the event
        $stmt = $db->prepare('UPDATE events SET event_start_date = :event_start_date, event_end_date = :event_end_date, type_id = :type_id, notes = :notes WHERE id = :event_id');
        $stmt->bindValue(':event_start_date', $event_start_date_utc, SQLITE3_TEXT);
        $stmt->bindValue(':event_end_date', $event_end_date_utc, SQLITE3_TEXT);
        $stmt->bindValue(':type_id', $type_id, SQLITE3_INTEGER);
        $stmt->bindValue(':notes', $notes, SQLITE3_TEXT);
        $stmt->bindValue(':event_id', $event_id, SQLITE3_INTEGER);
        $stmt->execute();
        
        // Delete existing pilot_events associations
        $stmt = $db->prepare('DELETE FROM pilot_events WHERE event_id = :event_id');
        $stmt->bindValue(':event_id', $event_id, SQLITE3_INTEGER);
        $stmt->execute();
        
        // Insert new pilot_events associations
        foreach ($pilot_ids as $pilot_id) {
            $stmt = $db->prepare('INSERT INTO pilot_events (event_id, pilot_id) VALUES (:event_id, :pilot_id)');
            $stmt->bindValue(':event_id', $event_id, SQLITE3_INTEGER);
            $stmt->bindValue(':pilot_id', intval($pilot_id), SQLITE3_INTEGER);
            $stmt->execute();
        }
        
        header("Location: view_events.php?year=$selected_year");
        exit();
    }
}

// Fetch events with their related data
$query = "
    SELECT 
        e.id,
        e.event_start_date,
        e.event_end_date,
        e.type_id,
        e.notes,
        GROUP_CONCAT(p.name, ', ') AS attendees,
        GROUP_CONCAT(p.id, ',') AS attendee_ids
    FROM events e
    LEFT JOIN pilot_events ea ON e.id = ea.event_id
    LEFT JOIN pilots p ON ea.pilot_id = p.id
    WHERE strftime('%Y', e.event_start_date) = :selected_year
    GROUP BY e.id
    ORDER BY e.event_start_date DESC
";
$stmt = $db->prepare($query);
$stmt->bindValue(':selected_year', $selected_year, SQLITE3_TEXT);
$events_result = $stmt->execute();

// Store events in array so we can iterate multiple times
$events_array = [];
while ($event = $events_result->fetchArray(SQLITE3_ASSOC)) {
    $events_array[] = $event;
}

// Fetch all pilots for the edit form
$pilots_stmt = $db->prepare('SELECT * FROM pilots ORDER BY name');
$all_pilots = $pilots_stmt->execute();
$pilots_array = [];
while ($pilot = $all_pilots->fetchArray(SQLITE3_ASSOC)) {
    $pilots_array[] = $pilot;
}

// Fetch event counts for overview
$overview_query = "
    SELECT 
        type_id,
        COUNT(*) AS event_count
    FROM events
    WHERE strftime('%Y', event_start_date) = :selected_year
    GROUP BY type_id
";
$overview_stmt = $db->prepare($overview_query);
$overview_stmt->bindValue(':selected_year', $selected_year, SQLITE3_TEXT);
$overview_result = $overview_stmt->execute();

// Fetch total event hours and manpower hours by type_id
$time_query = "
    SELECT 
        e.type_id,
        SUM((JULIANDAY(e.event_end_date) - JULIANDAY(e.event_start_date)) * 24) AS total_event_hours,
        SUM(
            (JULIANDAY(e.event_end_date) - JULIANDAY(e.event_start_date)) * 24 * 
            (SELECT COUNT(*) FROM pilot_events pe WHERE pe.event_id = e.id)
        ) AS total_manpower_hours
    FROM events e
    WHERE strftime('%Y', e.event_start_date) = :selected_year
    GROUP BY e.type_id
";
$time_stmt = $db->prepare($time_query);
$time_stmt->bindValue(':selected_year', $selected_year, SQLITE3_TEXT);
$time_result = $time_stmt->execute();

// Build arrays for overview and time data
$overview_data = [];
while ($row = $overview_result->fetchArray(SQLITE3_ASSOC)) {
    $overview_data[$row['type_id']] = $row['event_count'];
}

$time_data = [];
while ($row = $time_result->fetchArray(SQLITE3_ASSOC)) {
    $time_data[$row['type_id']] = [
        'total_event_hours' => round($row['total_event_hours'], 2),
        'total_manpower_hours' => round($row['total_manpower_hours'], 2)
    ];
}

// Map type_id to readable text
function getEventType($type_id) {
    $types = [
        1 => 'Dienst',
        2 => 'Einsatz',
        3 => 'Verwaltung'
    ];
    return $types[$type_id] ?? 'Unbekannt';
}

// Calculate duration between two UTC datetime strings
function calculateDuration($start_date_utc, $end_date_utc) {
    $start = new DateTime($start_date_utc, new DateTimeZone('UTC'));
    $end = new DateTime($end_date_utc, new DateTimeZone('UTC'));
    $interval = $start->diff($end);

    $hours = $interval->h + ($interval->days * 24);
    $minutes = $interval->i;

    return sprintf("%d Std %d Min", $hours, $minutes);
}
?>

<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Events anzeigen</title>
    <link rel="stylesheet" href="../css/view_events.css?v=<?php echo APP_VERSION; ?>">
    <link rel="stylesheet" href="../css/styles.css?v=<?php echo APP_VERSION; ?>">
    <script src="../js/view_events.js"></script>
</head>
<body>
    <?php include __DIR__ . '/../includes/header.php'; ?>
    <main>
        <h1>Dienstbuch</h1>

        <!-- Year Filter -->
        <form method="get" action="view_events.php" class="year-filter">
            <label for="year">Jahr auswählen:</label>
            <select name="year" id="year">
                <?php
                // Use prepared statement for consistency
                $stmt = $db->prepare("SELECT MIN(strftime('%Y', event_start_date)) AS min_year FROM events");
                $result = $stmt->execute();
                $row = $result->fetchArray(SQLITE3_ASSOC);
                $min_year = $row['min_year'] ?? date('Y');
                for ($year = $min_year; $year <= date('Y'); $year++) {
                    $selected = ($year == $selected_year) ? 'selected' : '';
                    echo "<option value='$year' $selected>$year</option>";
                }
                ?>
            </select>
        </form>

        <!-- Overview Section -->
        <div class="overview">
            <h2>Übersicht</h2>
            <?php
            $types = [
                1 => 'Dienst',
                2 => 'Einsatz',
                3 => 'Verwaltung'
            ];

            $total_event_sum = 0;
            $total_manpower_sum = 0;

            foreach ($types as $id => $label):
                $event_count = $overview_data[$id] ?? 0;
                $event_hours = $time_data[$id]['total_event_hours'] ?? 0;
                $manpower_hours = $time_data[$id]['total_manpower_hours'] ?? 0;

                $total_event_sum += $event_hours;
                $total_manpower_sum += $manpower_hours;
            ?>
                <div class="overview-item">
                    <p><strong><?= $label ?>:</strong> <?= $event_count ?> Ereignisse</p>
                    <p>Gesamtstunden: <?= number_format($event_hours, 2, ',', '.') ?> Std</p>
                    <p>Personenstunden: <?= number_format($manpower_hours, 2, ',', '.') ?> Std</p>
                </div>
            <?php endforeach; ?>

            <hr>
            <p><strong>Gesamtsumme aller Typen:</strong></p>
            <p>Gesamtstunden: <?= number_format($total_event_sum, 2, ',', '.') ?> Std</p>
            <p>Personenstunden: <?= number_format($total_manpower_sum, 2, ',', '.') ?> Std</p>
        </div>

        <!-- Events Section -->
        <div class="events-container">
            <?php 
            foreach ($events_array as $event):
                // Convert UTC datetime to local datetime-local format for editing
                $start_local = toLocalTimeForInput($event['event_start_date']);
                $end_local = toLocalTimeForInput($event['event_end_date']);
                $attendee_ids = $event['attendee_ids'] ? array_map('intval', explode(',', $event['attendee_ids'])) : [];
            ?>
                <div class="event-card" data-event-id="<?= $event['id']; ?>">
                    <p><strong>Start:</strong> <?= htmlspecialchars(toLocalTime($event['event_start_date'])); ?></p>
                    <p><strong>Ende:</strong> <?= htmlspecialchars(toLocalTime($event['event_end_date'])); ?></p>
                    <p><strong>Dauer:</strong> <?= calculateDuration($event['event_start_date'], $event['event_end_date']); ?></p>
                    <p><strong>Typ:</strong> <?= htmlspecialchars(getEventType($event['type_id'])); ?></p>
                    <p><strong>Notizen:</strong> <?= htmlspecialchars($event['notes']); ?></p>

                    <!-- Accordion for attendees -->
                    <div class="accordion">
                        <div class="accordion-header">Anwesenheitsliste anzeigen</div>
                        <div class="accordion-body">
                            <p><?= htmlspecialchars($event['attendees'] ?: 'Keine Teilnehmer'); ?></p>
                        </div>
                    </div>

                    <?php if ($is_admin): ?>
                        <div class="event-actions">
                            <button type="button" class="btn-edit" onclick="openEditModal(<?= htmlspecialchars(json_encode([
                                'id' => $event['id'],
                                'event_start_date' => $start_local,
                                'event_end_date' => $end_local,
                                'type_id' => $event['type_id'],
                                'notes' => $event['notes'],
                                'pilot_ids' => $attendee_ids
                            ])); ?>)">Bearbeiten</button>
                            <form method="post" action="view_events.php" class="delete-form" onsubmit="return confirm('Möchten Sie dieses Ereignis wirklich löschen?');">
                                <?php require_once __DIR__ . '/../includes/csrf.php'; csrf_field(); ?>
                                <input type="hidden" name="delete_event_id" value="<?= $event['id']; ?>">
                                <input type="hidden" name="year" value="<?= $selected_year; ?>">
                                <button type="submit" class="btn-delete">Löschen</button>
                            </form>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        </div>

        <!-- Edit Event Modal -->
        <?php if ($is_admin): ?>
        <div id="editEventModal" class="modal">
            <div class="modal-content">
                <span class="modal-close">&times;</span>
                <h2>Ereignis bearbeiten</h2>
                <form id="editEventForm" method="post" action="view_events.php">
                    <?php require_once __DIR__ . '/../includes/csrf.php'; csrf_field(); ?>
                    <input type="hidden" name="edit_event_id" id="edit_event_id">
                    <input type="hidden" name="year" value="<?= $selected_year; ?>">
                    
                    <div class="form-group">
                        <label for="edit_event_start_date">Startdatum und -zeit</label>
                        <input type="datetime-local" id="edit_event_start_date" name="event_start_date" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="edit_event_end_date">Enddatum und -zeit</label>
                        <input type="datetime-local" id="edit_event_end_date" name="event_end_date" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="edit_type_id">Typ</label>
                        <select name="type_id" id="edit_type_id" required>
                            <option value="">Bitte wählen</option>
                            <option value="1">Dienst</option>
                            <option value="2">Einsatz</option>
                            <option value="3">Verwaltung</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="edit_notes">Notizen</label>
                        <textarea id="edit_notes" name="notes" rows="5" required></textarea>
                    </div>
                    
                    <div class="form-group">
                        <label class="section-label">Anwesenheitsliste</label>
                        <div class="checkbox-container">
                            <?php foreach ($pilots_array as $pilot): ?>
                                <div class="checkbox-group">
                                    <label for="edit_pilot_<?= $pilot['id']; ?>"><?= htmlspecialchars($pilot['name']); ?></label>
                                    <input type="checkbox" id="edit_pilot_<?= $pilot['id']; ?>" name="pilot_ids[]" value="<?= $pilot['id']; ?>">
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    
                    <div class="form-actions">
                        <button type="submit" class="btn-submit">Speichern</button>
                        <button type="button" class="btn-cancel" onclick="closeEditModal()">Abbrechen</button>
                    </div>
                </form>
            </div>
        </div>
        <?php endif; ?>
    </main>
    <?php include __DIR__ . '/../includes/footer.php'; ?>
</body>
</html>
