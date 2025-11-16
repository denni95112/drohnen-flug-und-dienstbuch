<?php
require_once __DIR__ . '/includes/error_reporting.php';
require_once __DIR__ . '/includes/utils.php';

$dbPath = getDatabasePath();

$dbDir = dirname($dbPath);
if (!is_dir($dbDir)) {
    if (!@mkdir($dbDir, 0755, true)) {
        $error = "FEHLER: Das Verzeichnis für die Datenbank konnte nicht erstellt werden.\n\n";
        $error .= "Pfad: " . htmlspecialchars($dbDir) . "\n\n";
        $error .= "LÖSUNG:\n";
        $error .= "1. Erstellen Sie das Verzeichnis manuell:\n";
        $error .= "   sudo mkdir -p " . htmlspecialchars($dbDir) . "\n\n";
        $error .= "2. Setzen Sie die Berechtigungen:\n";
        $error .= "   sudo chown -R www-data:www-data " . htmlspecialchars($dbDir) . "\n";
        $error .= "   sudo chmod -R 755 " . htmlspecialchars($dbDir) . "\n\n";
        $error .= "Hinweis: Ersetzen Sie 'www-data' durch den Benutzer Ihres Webservers\n";
        $error .= "(z.B. 'apache', 'nginx', 'httpd' oder 'www-data').\n";
        die("<pre>" . $error . "</pre>");
    }
}

// Check if directory is writable
if (!is_writable($dbDir)) {
    $error = "FEHLER: Das Verzeichnis für die Datenbank ist nicht beschreibbar.\n\n";
    $error .= "Pfad: " . htmlspecialchars($dbDir) . "\n\n";
    $error .= "LÖSUNG:\n";
    $error .= "Setzen Sie Schreibrechte für das Verzeichnis:\n";
    $error .= "   sudo chown -R www-data:www-data " . htmlspecialchars($dbDir) . "\n";
    $error .= "   sudo chmod -R 755 " . htmlspecialchars($dbDir) . "\n\n";
    $error .= "Hinweis: Ersetzen Sie 'www-data' durch den Benutzer Ihres Webservers.\n";
    die("<pre>" . $error . "</pre>");
}

try {
    $db = new SQLite3($dbPath);
} catch (Exception $e) {
    $error = "FEHLER: Die Datenbankdatei konnte nicht erstellt/geöffnet werden.\n\n";
    $error .= "Pfad: " . htmlspecialchars($dbPath) . "\n\n";
    $error .= "Mögliche Ursachen:\n";
    $error .= "1. Unzureichende Berechtigungen im Verzeichnis\n";
    $error .= "2. Das Verzeichnis existiert nicht\n";
    $error .= "3. Der Webserver-Benutzer hat keine Schreibrechte\n\n";
    $error .= "LÖSUNG:\n";
    $error .= "1. Stellen Sie sicher, dass das Verzeichnis existiert:\n";
    $error .= "   sudo mkdir -p " . htmlspecialchars($dbDir) . "\n\n";
    $error .= "2. Setzen Sie die Berechtigungen:\n";
    $error .= "   sudo chown -R www-data:www-data " . htmlspecialchars($dbDir) . "\n";
    $error .= "   sudo chmod -R 755 " . htmlspecialchars($dbDir) . "\n\n";
    $error .= "3. Falls die Datei bereits existiert, prüfen Sie deren Berechtigungen:\n";
    $error .= "   sudo chown www-data:www-data " . htmlspecialchars($dbPath) . "\n";
    $error .= "   sudo chmod 644 " . htmlspecialchars($dbPath) . "\n\n";
    $error .= "Hinweis: Ersetzen Sie 'www-data' durch den Benutzer Ihres Webservers\n";
    $error .= "(z.B. 'apache', 'nginx', 'httpd' oder 'www-data').\n\n";
    $error .= "Original-Fehlermeldung: " . htmlspecialchars($e->getMessage()) . "\n";
    die("<pre>" . $error . "</pre>");
}

try {
    $db->exec('PRAGMA foreign_keys = ON');
} catch (Exception $e) {
    $error = "FEHLER: Datenbankkonfiguration fehlgeschlagen.\n\n";
    $error .= "Original-Fehlermeldung: " . htmlspecialchars($e->getMessage()) . "\n";
    die("<pre>" . $error . "</pre>");
}

$db->exec('CREATE TABLE IF NOT EXISTS pilots (
    id INTEGER PRIMARY KEY,
    name TEXT NOT NULL,
    minutes_of_flights_needed INTEGER NOT NULL DEFAULT 3,
    last_flight DATE
)');


$db->exec('CREATE TABLE IF NOT EXISTS flight_locations (
    id INTEGER PRIMARY KEY,
    location_name TEXT NOT NULL,
    latitude REAL NOT NULL,
    longitude REAL NOT NULL,
    created_at DATE NOT NULL,
    training BOOLEAN NOT NULL DEFAULT 1,
    description TEXT,
    file_path TEXT
)');

$db->exec('CREATE TABLE IF NOT EXISTS drones (
    id INTEGER PRIMARY KEY,
    drone_name TEXT NOT NULL
)');

$db->exec('CREATE TABLE IF NOT EXISTS flights (
    id INTEGER PRIMARY KEY,
    pilot_id INTEGER NOT NULL,
    flight_date DATETIME NOT NULL,
    flight_end_date DATETIME,
    flight_location_id INTEGER,
    drone_id INTEGER NOT NULL,
    battery_number INTEGER NOT NULL,
    FOREIGN KEY (pilot_id) REFERENCES pilots(id),
    FOREIGN KEY (flight_location_id) REFERENCES flight_locations(id),
    FOREIGN KEY (drone_id) REFERENCES drones(id)
)');

$db->exec('CREATE TABLE IF NOT EXISTS events (
    id INTEGER PRIMARY KEY,
    event_start_date DATETIME NOT NULL,
    event_end_date DATETIME NOT NULL,
    type_id INTEGER NOT NULL,
    notes TEXT NOT NULL
)');

$db->exec('CREATE TABLE IF NOT EXISTS pilot_events (
    id INTEGER PRIMARY KEY,             
    event_id INTEGER NOT NULL,          
    pilot_id INTEGER NOT NULL,          
    FOREIGN KEY (event_id) REFERENCES events(id) ON DELETE CASCADE,
    FOREIGN KEY (pilot_id) REFERENCES pilots(id) ON DELETE CASCADE
)');

$db->exec('CREATE TABLE IF NOT EXISTS auth_tokens (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    token TEXT NOT NULL UNIQUE,
    user_id INTEGER DEFAULT 1,
    expires_at DATETIME NOT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
)');

$db->exec('CREATE TABLE IF NOT EXISTS rate_limits (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    ip_address TEXT NOT NULL,
    action TEXT NOT NULL,
    attempts INTEGER DEFAULT 1,
    first_attempt DATETIME DEFAULT CURRENT_TIMESTAMP,
    last_attempt DATETIME DEFAULT CURRENT_TIMESTAMP,
    blocked_until DATETIME,
    UNIQUE(ip_address, action)
)');

$db->exec('CREATE INDEX IF NOT EXISTS idx_flights_pilot_date ON flights(pilot_id, flight_date)');
$db->exec('CREATE INDEX IF NOT EXISTS idx_flights_drone ON flights(drone_id)');
$db->exec('CREATE INDEX IF NOT EXISTS idx_flights_location ON flights(flight_location_id)');
$db->exec('CREATE INDEX IF NOT EXISTS idx_auth_tokens_expires ON auth_tokens(expires_at)');
$db->exec('CREATE INDEX IF NOT EXISTS idx_auth_tokens_token ON auth_tokens(token)');
$db->exec('CREATE INDEX IF NOT EXISTS idx_flight_locations_created ON flight_locations(created_at)');
$db->exec('CREATE INDEX IF NOT EXISTS idx_events_start_date ON events(event_start_date)');
$db->exec('CREATE INDEX IF NOT EXISTS idx_pilot_events_event ON pilot_events(event_id)');
$db->exec('CREATE INDEX IF NOT EXISTS idx_pilot_events_pilot ON pilot_events(pilot_id)');
$db->exec('CREATE INDEX IF NOT EXISTS idx_rate_limits_ip_action ON rate_limits(ip_address, action)');

echo "Database setup completed successfully! Redirecting...";

header("Refresh: 2; url=index.php");
exit;
?>
