<?php
/**
 * Migration: 002 - Create request_log table
 * Date: 2024-01-XX
 * 
 * Description: Creates the request_log table for tracking processed API requests
 * to prevent duplicate operations. Used for request deduplication.
 */

function up($db) {
    $db->exec('CREATE TABLE IF NOT EXISTS request_log (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        request_id TEXT NOT NULL UNIQUE,
        action TEXT NOT NULL,
        pilot_id INTEGER,
        flight_id INTEGER,
        processed_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        expires_at DATETIME NOT NULL,
        response_data TEXT
    )');
    
    $db->exec('CREATE INDEX IF NOT EXISTS idx_request_log_request_id ON request_log(request_id)');
    $db->exec('CREATE INDEX IF NOT EXISTS idx_request_log_expires ON request_log(expires_at)');
    
    return true;
}

function down($db) {
    $db->exec('DROP INDEX IF EXISTS idx_request_log_expires');
    $db->exec('DROP INDEX IF EXISTS idx_request_log_request_id');
    $db->exec('DROP TABLE IF EXISTS request_log');
    return true;
}

