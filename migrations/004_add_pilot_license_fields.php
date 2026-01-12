<?php
/**
 * Migration: 004 - Add A1/A3 and A2 Fernpilotenschein fields and lock_on_invalid_license to pilots table
 * Date: 2025-01-12
 * 
 * Description: Adds optional license fields for A1/A3 and A2 Fernpilotenschein
 * including license ID and valid until date for each, plus lock_on_invalid_license field
 * to lock pilots when their license is invalid.
 */

function up($db) {
    // Check if pilots table exists
    $tableExists = $db->querySingle("SELECT name FROM sqlite_master WHERE type='table' AND name='pilots'");
    if (!$tableExists) {
        // Table doesn't exist, nothing to migrate
        return true;
    }
    
    // Check if columns already exist
    $result = $db->query("PRAGMA table_info(pilots)");
    $columns = [];
    while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
        $columns[] = $row['name'];
    }
    $result->finalize();
    
    $hasA1A3Id = in_array('a1_a3_license_id', $columns);
    $hasA1A3ValidUntil = in_array('a1_a3_license_valid_until', $columns);
    $hasA2Id = in_array('a2_license_id', $columns);
    $hasA2ValidUntil = in_array('a2_license_valid_until', $columns);
    $hasLockOnInvalid = in_array('lock_on_invalid_license', $columns);
    
    // If all columns exist, migration already done
    if ($hasA1A3Id && $hasA1A3ValidUntil && $hasA2Id && $hasA2ValidUntil && $hasLockOnInvalid) {
        return true;
    }
    
    // SQLite doesn't support adding multiple columns in one ALTER TABLE
    // We'll use ALTER TABLE ADD COLUMN for each field
    // Note: Transaction is already started by migration_runner.php
    // Foreign keys are already disabled by migration_runner.php before the transaction
    
    $db->exec('PRAGMA busy_timeout = 10000'); // 10 seconds for better lock handling
    
    // Add A1/A3 license fields
    if (!$hasA1A3Id) {
        $db->exec('ALTER TABLE pilots ADD COLUMN a1_a3_license_id TEXT');
    }
    if (!$hasA1A3ValidUntil) {
        $db->exec('ALTER TABLE pilots ADD COLUMN a1_a3_license_valid_until DATE');
    }
    
    // Add A2 license fields
    if (!$hasA2Id) {
        $db->exec('ALTER TABLE pilots ADD COLUMN a2_license_id TEXT');
    }
    if (!$hasA2ValidUntil) {
        $db->exec('ALTER TABLE pilots ADD COLUMN a2_license_valid_until DATE');
    }
    
    // Add lock_on_invalid_license field
    if (!$hasLockOnInvalid) {
        $db->exec('ALTER TABLE pilots ADD COLUMN lock_on_invalid_license INTEGER NOT NULL DEFAULT 0');
    }
    
    return true;
}

function down($db) {
    // Check if pilots table exists
    $tableExists = $db->querySingle("SELECT name FROM sqlite_master WHERE type='table' AND name='pilots'");
    if (!$tableExists) {
        return true;
    }
    
    // Check if columns exist
    $result = $db->query("PRAGMA table_info(pilots)");
    $columns = [];
    while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
        $columns[] = $row['name'];
    }
    $result->finalize();
    
    // SQLite doesn't support DROP COLUMN directly
    // We need to recreate the table without these columns
    // Note: Transaction is already started by migration_runner.php
    // Foreign keys are already disabled by migration_runner.php before the transaction
    
    $db->exec('PRAGMA busy_timeout = 10000');
    
    // Check if any of the columns exist
    $hasA1A3Id = in_array('a1_a3_license_id', $columns);
    $hasA1A3ValidUntil = in_array('a1_a3_license_valid_until', $columns);
    $hasA2Id = in_array('a2_license_id', $columns);
    $hasA2ValidUntil = in_array('a2_license_valid_until', $columns);
    $hasLockOnInvalid = in_array('lock_on_invalid_license', $columns);
    
    if (!$hasA1A3Id && !$hasA1A3ValidUntil && !$hasA2Id && !$hasA2ValidUntil && !$hasLockOnInvalid) {
        // Columns don't exist, nothing to rollback
        return true;
    }
    
    // Get all existing columns except the ones we want to remove
    $keepColumns = [];
    $excludeColumns = ['a1_a3_license_id', 'a1_a3_license_valid_until', 'a2_license_id', 'a2_license_valid_until', 'lock_on_invalid_license'];
    foreach ($columns as $col) {
        if (!in_array($col, $excludeColumns)) {
            $keepColumns[] = $col;
        }
    }
    
    // Check if pilots_new already exists
    $checkNew = $db->query("SELECT name FROM sqlite_master WHERE type='table' AND name='pilots_new'");
    $result = $checkNew->fetchArray();
    $checkNew->finalize();
    if ($result !== false) {
        $db->exec('DROP TABLE IF EXISTS pilots_new');
    }
    
    // Create new table without the license columns
    // We need to determine the schema from existing columns
    $db->exec('CREATE TABLE pilots_new (
        id INTEGER PRIMARY KEY,
        name TEXT NOT NULL,
        minutes_of_flights_needed INTEGER NOT NULL DEFAULT 45,
        last_flight DATE
    )');
    
    // Copy data (excluding the license columns)
    $db->exec('INSERT INTO pilots_new (id, name, minutes_of_flights_needed, last_flight)
               SELECT id, name, minutes_of_flights_needed, last_flight FROM pilots');
    
    // Verify data integrity
    $countOld = $db->querySingle('SELECT COUNT(*) FROM pilots');
    $countNew = $db->querySingle('SELECT COUNT(*) FROM pilots_new');
    
    if ($countNew != $countOld) {
        throw new Exception("Data loss detected: Old table had {$countOld} entries, new table has {$countNew} entries");
    }
    
    // Drop old table
    $db->exec('DROP TABLE IF EXISTS pilots');
    
    // Rename new table
    $db->exec('ALTER TABLE pilots_new RENAME TO pilots');
    
    // Recreate indexes
    $db->exec('CREATE INDEX IF NOT EXISTS idx_flights_pilot_date ON flights(pilot_id, flight_date)');
    
    // Note: Foreign keys will be re-enabled by migration_runner.php after commit
    
    return true;
}
