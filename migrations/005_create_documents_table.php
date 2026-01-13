<?php
/**
 * Migration: 005 - Create documents table
 * Date: 2026-01-XX
 * 
 * Description: Creates the documents table for storing PDF document metadata.
 * Documents are stored encrypted in the uploads directory.
 */

function up($db) {
    $db->exec('CREATE TABLE IF NOT EXISTS documents (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        filename TEXT NOT NULL,
        original_filename TEXT NOT NULL,
        file_path TEXT NOT NULL,
        file_size INTEGER NOT NULL,
        uploaded_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        uploaded_by INTEGER DEFAULT 1,
        description TEXT
    )');
    
    $db->exec('CREATE INDEX IF NOT EXISTS idx_documents_uploaded_at ON documents(uploaded_at DESC)');
    
    return true;
}

function down($db) {
    $db->exec('DROP INDEX IF EXISTS idx_documents_uploaded_at');
    $db->exec('DROP TABLE IF EXISTS documents');
    return true;
}
