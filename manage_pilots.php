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

// Connect to the SQLite database
require_once __DIR__ . '/includes/utils.php';
require_once __DIR__ . '/version.php';
$dbPath = getDatabasePath();
$db = new SQLite3($dbPath);

// Handle adding a pilot
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    require_once __DIR__ . '/includes/csrf.php';
    verify_csrf();
    
    $action = $_POST['action'];
    if ($action === 'add') {
        $name = trim($_POST['name']);
        if (!empty($name)) {
            $stmt = $db->prepare('INSERT INTO pilots (name) VALUES (:name)');
            $stmt->bindValue(':name', $name, SQLITE3_TEXT);
            $stmt->execute();
            $message = "Pilot hinzugefügt: $name";
        } else {
            $error = "Der Name darf nicht leer sein.";
        }
    } elseif ($action === 'delete') {
        $pilot_id = (int)$_POST['pilot_id'];
        $stmt = $db->prepare('DELETE FROM pilots WHERE id = :id');
        $stmt->bindValue(':id', $pilot_id, SQLITE3_INTEGER);
        $stmt->execute();
        $message = "Pilot gelöscht.";
    } elseif ($action === 'update_num_flights') {
        $pilot_id = (int)$_POST['pilot_id'];
        $minutes_of_flights_needed = max(1, (int)$_POST['minutes_of_flights_needed']); // Minimum 1
        $stmt = $db->prepare('UPDATE pilots SET minutes_of_flights_needed = :minutes_of_flights_needed WHERE id = :id');
        $stmt->bindValue(':minutes_of_flights_needed', $minutes_of_flights_needed, SQLITE3_INTEGER);
        $stmt->bindValue(':id', $pilot_id, SQLITE3_INTEGER);
        $stmt->execute();
        $message = "Anzahl der benötigten Flüge für Pilot ID $pilot_id aktualisiert.";
    }
}

// Retrieve all pilots
$stmt = $db->prepare('SELECT * FROM pilots ORDER BY name');
$pilots = $stmt->execute();
?>

<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Piloten verwalten</title>
    <link rel="stylesheet" href="css/styles.css?v=<?php echo APP_VERSION; ?>">
    <link rel="stylesheet" href="css/manage_pilots.css?v=<?php echo APP_VERSION; ?>">
    <script src="js/manage_pilots.js"></script>
</head>
<body>
    <?php include 'includes/header.php'; ?>
    <main>
        <h1>Piloten verwalten</h1>

        <?php if (isset($message)): ?>
            <p class="message"><?= htmlspecialchars($message); ?></p>
        <?php endif; ?>
        <?php if (isset($error)): ?>
            <p class="error"><?= htmlspecialchars($error); ?></p>
        <?php endif; ?>

        <!-- Add Pilot Form -->
        <form method="post" action="manage_pilots.php">
            <?php require_once __DIR__ . '/includes/csrf.php'; csrf_field(); ?>
            <input type="hidden" name="action" value="add">
            <label for="name">Pilot hinzufügen:</label>
            <input type="text" id="name" name="name" placeholder="Name eingeben" required>
            <br><br>
            <button type="submit" class="button-full">Hinzufügen</button>
        </form>

        <!-- Pilots List -->
        <h2>Bestehende Piloten</h2>
        <table>
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Name</th>
                    <th>Benötigte Flüge</th>
                    <th>Aktionen</th>
                </tr>
            </thead>
            <tbody>
                <?php while ($pilot = $pilots->fetchArray(SQLITE3_ASSOC)): ?>
                    <tr>
                        <td><?= htmlspecialchars($pilot['id']); ?></td>
                        <td><?= htmlspecialchars($pilot['name']); ?></td>
                        <td>
                            <form method="post" action="manage_pilots.php">
                                <?php require_once __DIR__ . '/includes/csrf.php'; csrf_field(); ?>
                                <input type="hidden" name="action" value="update_num_flights">
                                <input type="hidden" name="pilot_id" value="<?= $pilot['id']; ?>">
                                <label for="minutes_of_flights_needed_<?= $pilot['id']; ?>">Flugminuten pro 3 Monate</label>
                                <input type="number" name="minutes_of_flights_needed" id="minutes_of_flights_needed_<?= $pilot['id']; ?>" value="<?= $pilot['minutes_of_flights_needed'] ?? 3; ?>" min="1" required>
                                <button type="submit">Aktualisieren</button>
                            </form>
                        </td>
                        <td>
                            <form method="post" action="manage_pilots.php">
                                <?php require_once __DIR__ . '/includes/csrf.php'; csrf_field(); ?>
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="pilot_id" value="<?= $pilot['id']; ?>">
                                <button type="submit" class="button-full delete-pilot-btn">Löschen</button>
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
