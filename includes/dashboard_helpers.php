<?php
/**
 * Dashboard Helper Functions
 * Functions used by dashboard and API
 */

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
            // Use the most recent flight that contributed to the total (we iterate newest first)
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

