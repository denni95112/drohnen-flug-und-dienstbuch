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

// Get the selected year, default to the current year
$selected_year = isset($_GET['year']) ? intval($_GET['year']) : date('Y');

// Fetch events with their related data
$query = "
    SELECT 
        e.id,
        e.event_start_date,
        e.event_end_date,
        e.type_id,
        e.notes,
        GROUP_CONCAT(p.name, ', ') AS attendees
    FROM events e
    LEFT JOIN pilot_events ea ON e.id = ea.event_id
    LEFT JOIN pilots p ON ea.pilot_id = p.id
    WHERE strftime('%Y', e.event_start_date) = :selected_year
    GROUP BY e.id
    ORDER BY e.event_start_date DESC
";
$stmt = $db->prepare($query);
$stmt->bindValue(':selected_year', $selected_year, SQLITE3_TEXT);
$events = $stmt->execute();

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
    <link rel="stylesheet" href="css/view_events.css?v=<?php echo APP_VERSION; ?>">
    <link rel="stylesheet" href="css/styles.css?v=<?php echo APP_VERSION; ?>">
    <script src="js/view_events.js"></script>
</head>
<body>
    <?php include 'includes/header.php'; ?>
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
            <?php while ($event = $events->fetchArray(SQLITE3_ASSOC)): ?>
                <div class="event-card">
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
                </div>
            <?php endwhile; ?>
        </div>
    </main>
    <?php include 'includes/footer.php'; ?>
</body>
</html>
