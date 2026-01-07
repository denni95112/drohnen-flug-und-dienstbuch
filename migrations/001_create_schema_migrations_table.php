<?php
/**
 * Migration: 001 - Create schema_migrations table
 * Date: 2024-01-XX
 * 
 * Description: Creates the table to track which migrations have been executed.
 * This is the first migration and must be run before any other migrations.
 */

function up($db) {
    $db->exec('CREATE TABLE IF NOT EXISTS schema_migrations (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        migration_name TEXT NOT NULL UNIQUE,
        executed_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        executed_by TEXT,
        execution_time_ms INTEGER
    )');
    
    $db->exec('CREATE INDEX IF NOT EXISTS idx_schema_migrations_name ON schema_migrations(migration_name)');
    
    return true;
}

function down($db) {
    $db->exec('DROP INDEX IF EXISTS idx_schema_migrations_name');
    $db->exec('DROP TABLE IF EXISTS schema_migrations');
    return true;
}

