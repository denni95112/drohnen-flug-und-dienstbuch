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

// Handle adding a drone
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['drone_name'])) {
    require_once __DIR__ . '/includes/csrf.php';
    verify_csrf();
    $drone_name = trim($_POST['drone_name']);
    
    if (!empty($drone_name)) {
        $stmt = $db->prepare('INSERT INTO drones (drone_name) VALUES (:drone_name)');
        $stmt->bindValue(':drone_name', $drone_name, SQLITE3_TEXT);
        $stmt->execute();
        $message = "Drohne erfolgreich hinzugefügt.";
    } else {
        $error = "Bitte geben Sie einen Namen für die Drohne ein.";
    }
}

// Handle deleting a drone
if (isset($_GET['delete_id'])) {
    $delete_id = intval($_GET['delete_id']);
    $stmt = $db->prepare('DELETE FROM drones WHERE id = :id');
    $stmt->bindValue(':id', $delete_id, SQLITE3_INTEGER);
    $stmt->execute();
    $message = "Drohne erfolgreich gelöscht.";
}

// Fetch all drones
$stmt = $db->prepare('SELECT * FROM drones ORDER BY drone_name ASC');
$drones = $stmt->execute();
?>

<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Drohnen verwalten</title>
    <link rel="stylesheet" href="css/styles.css?v=<?php echo APP_VERSION; ?>">
    <script src="js/manage_drones.js"></script>
</head>
<body>
    <?php include 'includes/header.php'; ?>
    <main>
        <h1>Drohnen verwalten</h1>

        <?php if (isset($message)): ?>
            <p class="message"><?= htmlspecialchars($message); ?></p>
        <?php endif; ?>
        <?php if (isset($error)): ?>
            <p class="error"><?= htmlspecialchars($error); ?></p>
        <?php endif; ?>

        <!-- Add Drone Form -->
        <form method="post" action="manage_drones.php">
            <?php require_once __DIR__ . '/includes/csrf.php'; csrf_field(); ?>
            <div>
                <label for="drone_name">Drohnenname</label>
                <input type="text" id="drone_name" name="drone_name" placeholder="Name der Drohne" required>
            </div>
            <br><br>
            <button type="submit" class="button-full">Drohne hinzufügen</button>
        </form>

        <!-- Drones Table -->
        <h2>Vorhandene Drohnen</h2>
        <table>
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Drohnenname</th>
                    <th>Aktionen</th>
                </tr>
            </thead>
            <tbody>
                <?php while ($drone = $drones->fetchArray(SQLITE3_ASSOC)): ?>
                    <tr>
                        <td><?= htmlspecialchars($drone['id']); ?></td>
                        <td><?= htmlspecialchars($drone['drone_name']); ?></td>
                        <td>
                            <a href="manage_drones.php?delete_id=<?= $drone['id']; ?>" class="delete-drone-link" data-drone-id="<?= $drone['id']; ?>">
                                <button type="button">Löschen</button>
                            </a>
                        </td>
                    </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
    </main>
    <?php include 'includes/footer.php'; ?>
</body>
</html>