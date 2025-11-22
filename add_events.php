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

/**
 * Convert datetime to UTC for storage
 * @param string $datetime Local datetime string
 * @return string UTC datetime string
 */
function toUTC($datetime) {
    global $config;
    $timezone = $config['timezone'] ?? 'Europe/Berlin';
    $date = new DateTime($datetime, new DateTimeZone($timezone));
    $date->setTimezone(new DateTimeZone('UTC'));
    return $date->format('Y-m-d H:i:s');
}

/**
 * Convert datetime from UTC to local for display
 * @param string $datetime UTC datetime string
 * @return string Local datetime string
 */
function toLocalTime($datetime) {
    global $config;
    $timezone = $config['timezone'] ?? 'Europe/Berlin';
    $date = new DateTime($datetime, new DateTimeZone('UTC'));
    $date->setTimezone(new DateTimeZone($timezone));
    return $date->format('Y-m-d\TH:i');
}

$error_message = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_once __DIR__ . '/includes/csrf.php';
    verify_csrf();
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

        $stmt = $db->prepare('INSERT INTO events (event_start_date, event_end_date, type_id, notes) VALUES (:event_start_date, :event_end_date, :type_id, :notes)');
        $stmt->bindValue(':event_start_date', $event_start_date_utc, SQLITE3_TEXT);
        $stmt->bindValue(':event_end_date', $event_end_date_utc, SQLITE3_TEXT);
        $stmt->bindValue(':type_id', $type_id, SQLITE3_INTEGER);
        $stmt->bindValue(':notes', $notes, SQLITE3_TEXT);
        $stmt->execute();
        $event_id = $db->lastInsertRowID();

        foreach ($pilot_ids as $pilot_id) {
            $stmt = $db->prepare('INSERT INTO pilot_events (event_id, pilot_id) VALUES (:event_id, :pilot_id)');
            $stmt->bindValue(':event_id', $event_id, SQLITE3_INTEGER);
            $stmt->bindValue(':pilot_id', intval($pilot_id), SQLITE3_INTEGER);
            $stmt->execute();
        }

        $success_message = 'Das Ereignis wurde erfolgreich hinzugefügt.';
    }
}

$stmt = $db->prepare('SELECT * FROM pilots ORDER BY name');
$pilots = $stmt->execute();
?>

<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dienste hinzufügen</title>
    <link rel="stylesheet" href="css/add_events.css?v=<?php echo APP_VERSION; ?>">
    <link rel="stylesheet" href="css/styles.css?v=<?php echo APP_VERSION; ?>">
    <script src="js/add_events.js"></script>
</head>
<body>
    <?php include 'includes/header.php'; ?>
    <main>
        <h1>Dienst hinzufügen</h1>
        <?php if (!empty($error_message)): ?>
            <div class="error-message"><?= htmlspecialchars($error_message); ?></div>
        <?php elseif (!empty($success_message)): ?>
            <div class="success-message"><?= htmlspecialchars($success_message); ?></div>
        <?php endif; ?>

        <form method="post" action="add_events.php">
            <?php require_once __DIR__ . '/includes/csrf.php'; csrf_field(); ?>
            <div class="form-group">
                <label for="event_start_date">Startdatum und -zeit</label>
                <input type="datetime-local" id="event_start_date" name="event_start_date" required>
            </div>
            <div class="form-group">
                <label for="event_end_date">Enddatum und -zeit</label>
                <input type="datetime-local" id="event_end_date" name="event_end_date" required>
            </div>
            <div class="form-group">
                <label for="type_id">Typ</label>
                <select name="type_id" id="type_id" required>
                    <option value="">Bitte wählen</option>
                    <option value="1">Dienst</option>
                    <option value="2">Einsatz</option>
                    <option value="3">Verwaltung</option>
                </select>
            </div>
            <div class="form-group">
                <label for="notes">Notizen</label>
                <textarea id="notes" name="notes" rows="5" required></textarea>
            </div>
            <div class="form-group">
                <label class="section-label">Anwesenheitsliste</label>
                <div class="checkbox-container">
                    <?php while ($pilot = $pilots->fetchArray(SQLITE3_ASSOC)): ?>
                        <div class="checkbox-group">
                            <label for="pilot_<?= $pilot['id']; ?>"><?= htmlspecialchars($pilot['name']); ?></label>
                            <input type="checkbox" id="pilot_<?= $pilot['id']; ?>" name="pilot_ids[]" value="<?= $pilot['id']; ?>">
                        </div>
                    <?php endwhile; ?>
                </div>
            </div>
            <button type="submit" class="btn-submit">Dienst hinzufügen</button>
        </form>
    </main>
    <?php include 'includes/footer.php'; ?>
</body>
</html>
