<?php
/**
 * Migration: 006 - Create API tokens table
 * Date: 2026-02-06
 * 
 * Description: Creates the api_tokens table for API token authentication.
 * Tokens are stored as SHA-256 hashes for security.
 */

function up($db) {
    $db->exec('CREATE TABLE IF NOT EXISTS api_tokens (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        token TEXT NOT NULL UNIQUE,
        name TEXT NOT NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        last_used_at DATETIME,
        expires_at DATETIME,
        is_active INTEGER DEFAULT 1
    )');
    
    $db->exec('CREATE INDEX IF NOT EXISTS idx_api_tokens_token ON api_tokens(token)');
    $db->exec('CREATE INDEX IF NOT EXISTS idx_api_tokens_active ON api_tokens(is_active)');
    
    return true;
}

function down($db) {
    $db->exec('DROP INDEX IF EXISTS idx_api_tokens_active');
    $db->exec('DROP INDEX IF EXISTS idx_api_tokens_token');
    $db->exec('DROP TABLE IF EXISTS api_tokens');
    return true;
}
