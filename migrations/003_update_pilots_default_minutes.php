<?php
/**
 * Migration: 003 - Update pilots default minutes_of_flights_needed from 3 to 45
 * Date: 2025-01-XX
 * 
 * Description: Updates the default value for minutes_of_flights_needed in the pilots table
 * from 3 to 45. This only affects the schema default for new pilots - existing pilot data
 * is preserved as-is.
 */

function up($db) {
    // Check if pilots table exists
    $tableExists = $db->querySingle("SELECT name FROM sqlite_master WHERE type='table' AND name='pilots'");
    if (!$tableExists) {
        // Table doesn't exist, nothing to migrate
        return true;
    }
    
    // Check if column exists
    $result = $db->query("PRAGMA table_info(pilots)");
    $hasMinutesColumn = false;
    while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
        if ($row['name'] === 'minutes_of_flights_needed') {
            $hasMinutesColumn = true;
            break;
        }
    }
    $result->finalize(); // Close result set to release locks
    
    if (!$hasMinutesColumn) {
        // Column doesn't exist, nothing to migrate
        return true;
    }
    
    // SQLite doesn't support ALTER TABLE to change default values
    // We need to recreate the table with the new default
    // Note: Transaction is already started by migration_runner.php
    // Foreign keys are already disabled by migration_runner.php before the transaction
    
    $db->exec('PRAGMA busy_timeout = 10000'); // 10 seconds for better lock handling
    
    // Check if pilots_new already exists (from a failed migration)
    $checkNew = $db->query("SELECT name FROM sqlite_master WHERE type='table' AND name='pilots_new'");
    $result = $checkNew->fetchArray();
    $checkNew->finalize(); // Important: close the result set to release locks
    if ($result !== false) {
        $db->exec('DROP TABLE IF EXISTS pilots_new');
    }
    
    // Create new table with updated default value
    $db->exec('CREATE TABLE pilots_new (
        id INTEGER PRIMARY KEY,
        name TEXT NOT NULL,
        minutes_of_flights_needed INTEGER NOT NULL DEFAULT 45,
        last_flight DATE
    )');
    
    // Copy all data from old table to new table
    $db->exec('INSERT INTO pilots_new (id, name, minutes_of_flights_needed, last_flight)
               SELECT id, name, minutes_of_flights_needed, last_flight FROM pilots');
    
    // Verify data integrity
    $countOld = $db->querySingle('SELECT COUNT(*) FROM pilots');
    $countNew = $db->querySingle('SELECT COUNT(*) FROM pilots_new');
    
    if ($countNew != $countOld) {
        throw new Exception("Data loss detected: Old table had {$countOld} entries, new table has {$countNew} entries");
    }
    
    // Drop old table (foreign keys are disabled, so this should work)
    $db->exec('DROP TABLE IF EXISTS pilots');
    
    // Rename new table to original name
    $db->exec('ALTER TABLE pilots_new RENAME TO pilots');
    
    // Recreate indexes
    $db->exec('CREATE INDEX IF NOT EXISTS idx_flights_pilot_date ON flights(pilot_id, flight_date)');
    
    // Note: Foreign keys will be re-enabled by migration_runner.php after commit
    
    return true;
}

function down($db) {
    // Rollback: Change default back to 3
    $tableExists = $db->querySingle("SELECT name FROM sqlite_master WHERE type='table' AND name='pilots'");
    if (!$tableExists) {
        return true;
    }
    
    $result = $db->query("PRAGMA table_info(pilots)");
    $hasMinutesColumn = false;
    while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
        if ($row['name'] === 'minutes_of_flights_needed') {
            $hasMinutesColumn = true;
            break;
        }
    }
    $result->finalize(); // Close result set to release locks
    
    if (!$hasMinutesColumn) {
        return true;
    }
    
    // Note: Transaction is already started by migration_runner.php
    // Foreign keys are already disabled by migration_runner.php before the transaction
    
    $db->exec('PRAGMA busy_timeout = 10000'); // 10 seconds for better lock handling
    
    $checkNew = $db->query("SELECT name FROM sqlite_master WHERE type='table' AND name='pilots_new'");
    $result = $checkNew->fetchArray();
    $checkNew->finalize(); // Important: close the result set to release locks
    if ($result !== false) {
        $db->exec('DROP TABLE IF EXISTS pilots_new');
    }
    
    // Create new table with old default value (3)
    $db->exec('CREATE TABLE pilots_new (
        id INTEGER PRIMARY KEY,
        name TEXT NOT NULL,
        minutes_of_flights_needed INTEGER NOT NULL DEFAULT 3,
        last_flight DATE
    )');
    
    // Copy all data
    $db->exec('INSERT INTO pilots_new (id, name, minutes_of_flights_needed, last_flight)
               SELECT id, name, minutes_of_flights_needed, last_flight FROM pilots');
    
    // Verify data integrity
    $countOld = $db->querySingle('SELECT COUNT(*) FROM pilots');
    $countNew = $db->querySingle('SELECT COUNT(*) FROM pilots_new');
    
    if ($countNew != $countOld) {
        throw new Exception("Data loss detected: Old table had {$countOld} entries, new table has {$countNew} entries");
    }
    
    // Drop old table (foreign keys are disabled, so this should work)
    $db->exec('DROP TABLE IF EXISTS pilots');
    
    // Rename new table
    $db->exec('ALTER TABLE pilots_new RENAME TO pilots');
    
    // Recreate indexes
    $db->exec('CREATE INDEX IF NOT EXISTS idx_flights_pilot_date ON flights(pilot_id, flight_date)');
    
    // Note: Foreign keys will be re-enabled by migration_runner.php after commit
    
    return true;
}

