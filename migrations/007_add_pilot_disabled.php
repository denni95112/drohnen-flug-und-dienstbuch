<?php
/**
 * Migration: 007 - Add disabled flag to pilots
 *
 * Description: When set, pilot is hidden from dashboard and excluded from default pilot list API;
 * use pilots.php?action=list&include_disabled=1 on the manage page to see all pilots.
 */

function up($db) {
    $tableExists = $db->querySingle("SELECT name FROM sqlite_master WHERE type='table' AND name='pilots'");
    if (!$tableExists) {
        return true;
    }

    $result = $db->query('PRAGMA table_info(pilots)');
    $columns = [];
    while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
        $columns[] = $row['name'];
    }
    $result->finalize();

    if (in_array('disabled', $columns, true)) {
        return true;
    }

    $db->exec('PRAGMA busy_timeout = 10000');
    $db->exec('ALTER TABLE pilots ADD COLUMN disabled INTEGER NOT NULL DEFAULT 0');

    return true;
}

function down($db) {
    $tableExists = $db->querySingle("SELECT name FROM sqlite_master WHERE type='table' AND name='pilots'");
    if (!$tableExists) {
        return true;
    }

    $result = $db->query('PRAGMA table_info(pilots)');
    $columns = [];
    while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
        $columns[] = $row['name'];
    }
    $result->finalize();

    if (!in_array('disabled', $columns, true)) {
        return true;
    }

    $db->exec('PRAGMA busy_timeout = 10000');
    $db->exec('ALTER TABLE pilots DROP COLUMN disabled');

    return true;
}
