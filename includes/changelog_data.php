<?php
/**
 * Changelog data
 * This file contains all version history and changelog entries
 */

$changelog = [
    [
        'version' => '1.1.1',
        'date' => '2026-01-XX',
        'changes' => [
            'Bearbeiten von Piloten geändert',
            'Aktive Flüge werden jetzt im Dashboard immer als erstes angezeigt',
        ],
        'bugfixes' => [
        ],
        'new_features' => [
            'A1/A3 und A2 Fernpilotenschein Felder hinzugefügt',
            'Pilotensuchen im Dashboard hinzugefügt',

        ]
    ],
    [
        'version' => '1.1.0',
        'date' => '2026-01-11',
        'changes' => [
            'Major Refactor: API-basierte Architektur implementiert',
            'Alle Seiten verwenden jetzt RESTful API-Endpunkte',
            'Dashboard mit Auto-Refresh (30 Sekunden)',
            'Multi-User-Support mit Concurrency Control',
            'Request-Deduplizierung verhindert doppelte Operationen',
            'Datenbank-Migrationssystem hinzugefügt',
            'Migration-Benachrichtigung in Header',
            'Verbesserte Fehlerbehandlung und Benutzer-Feedback',
            'Separation of Concerns: API und UI getrennt',
            'Seite Einträge löschen entfernt (nun als Admin in Alle Flüge möglich)'
        ],
        'bugfixes' => [
            'Multi-User-Konflikte beim Starten/Beenden von Flügen behoben',
            'Race Conditions bei gleichzeitigen Operationen behoben',
            'Doppelte Requests werden jetzt verhindert',
            'Default min Flugzeit für neue Piloten auf 45 Minuten gesetzt',
            'DB Update nach Setup nicht mehr notwendig',
            'Falscher Wert für Standort ob Einsatz oder Übung behoben',
            'UI Fehler behoben'
        ],
        'new_features' => [
            'Unterseiten haben nur /pages/ und /api/ als Basispfad',
            'API-Endpunkte für alle Datenoperationen',
            'Auto-Refresh im Dashboard',
            'Datenbank-Migrationssystem',
            'Migration-Verwaltungsseite (migrations.php)',
            'Request-Deduplizierung',
            'Optimistic Locking für Concurrency Control',
            'Verbesserte JavaScript-Integration für alle Seiten',
            'API-Dokumentation im README',
            'Bearbeiten von Flugstandorten als Admin',
            'Bearbeiten von Flügen als Admin',
            'Update Tool für Administratoren',
        ],
        'migration_notes' => [
            'Führen Sie die Migrationen aus migrations.php aus',
            'Migration 001 erstellt schema_migrations Tabelle',
            'Migration 002 erstellt request_log Tabelle für Request-Deduplizierung'
        ]
    ],
    [
        'version' => '1.0.2',
        'date' => '2026-01-07',
        'changes' => [

        ],
        'bugfixes' => [
            'Benachrichtigung für neue Version',
        ],
        'new_features' => [

        ]
    ],
    [
        'version' => '1.0.1',
        'date' => '2026-01-07',
        'changes' => [
            'Admin-Login Info Icon (Schloss in Header) hinzugefügt',
            'Wechsel zurück zum normalen Benutzer Button hinzugefügt'

        ],
        'bugfixes' => [
            'Zeitzonenproblem behoben',
            'Cache-Problem im Service Worker behoben'
        ],
        'new_features' => [
            'Buy Me a Coffee Button hinzugefügt',
            'Dialog für Frage ob Entwickler über Installation benachrichtigt werden darf',
            'Neue Installation Info-Dialog',
            'Löschen / Bearbeiten von Diensten als Admin'
        ]
    ],
    [
        'version' => '1.0.0',
        'date' => '2025-11-29',
        'changes' => [
            'Erste stabile Version'
        ],
        'bugfixes' => [
            // No bugfixes in initial version
        ],
        'new_features' => [
            'Flugprotokoll-Verwaltung',
            'Pilot-Verwaltung',
            'Batterie-Verfolgung',
            'Standort-Verwaltung',
            'Dashboard mit Flugstatistiken',
            'Sichere Authentifizierung',
            'PWA-Unterstützung',
            'Admin-Funktionalität'
        ]
    ]
];

