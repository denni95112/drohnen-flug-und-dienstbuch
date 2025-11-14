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

// Get the selected flight date from the GET request
$flight_date = isset($_GET['flight_date']) ? $_GET['flight_date'] : null;

if ($flight_date) {
    // Validate date format
    $date_obj = DateTime::createFromFormat('Y-m-d\TH:i', $flight_date);
    if (!$date_obj) {
        $date_obj = DateTime::createFromFormat('Y-m-d', $flight_date);
    }
    
    if ($date_obj) {
        $date_str = $date_obj->format('Y-m-d');
        // Fetch locations that match the selected flight date using prepared statement
        $stmt = $db->prepare("SELECT * FROM flight_locations WHERE DATE(created_at) = DATE(:flight_date)");
        $stmt->bindValue(':flight_date', $date_str, SQLITE3_TEXT);
        $locations = $stmt->execute();
    } else {
        $locations = false;
    }

    // Generate options for locations
    $location_options = "";
    while ($row = $locations->fetchArray(SQLITE3_ASSOC)) {
        $location_options .= "<option value='" . $row['id'] . "'>" . htmlspecialchars($row['location_name']) . "</option>";
    }

    // Output the location options
    echo $location_options;
}
?>
