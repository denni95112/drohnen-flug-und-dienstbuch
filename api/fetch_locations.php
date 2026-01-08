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
$dbPath = getDatabasePath();
$db = new SQLite3($dbPath);

// Get the selected flight date from the GET request
$flight_date = isset($_GET['flight_date']) ? $_GET['flight_date'] : null;

if ($flight_date) {
    // Validate date format - flight_date is in local time from datetime-local input
    $date_obj = DateTime::createFromFormat('Y-m-d\TH:i', $flight_date);
    if (!$date_obj) {
        $date_obj = DateTime::createFromFormat('Y-m-d', $flight_date);
    }
    
    if ($date_obj) {
        // Get the date part in local time
        $date_str = $date_obj->format('Y-m-d');
        
        // Convert local date to UTC range for database comparison
        // Start of day in local time
        $start_local = $date_str . ' 00:00:00';
        $start_utc = toUTC($start_local);
        
        // End of day in local time
        $end_local = $date_str . ' 23:59:59';
        $end_utc = toUTC($end_local);
        
        // Fetch locations that match the selected flight date (stored in UTC)
        $stmt = $db->prepare("SELECT * FROM flight_locations WHERE created_at >= :start_date AND created_at <= :end_date");
        $stmt->bindValue(':start_date', $start_utc, SQLITE3_TEXT);
        $stmt->bindValue(':end_date', $end_utc, SQLITE3_TEXT);
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
