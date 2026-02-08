<?php
/**
 * Changelog data
 * This file contains all version history and changelog entries
 */

$changelog = [
    [
        'version' => '1.1.5 - Hotfix',
        'date' => '2026-02-08',
        'changes' => [

        ],
        'bugfixes' => [
            'Nächster Flug fällig Meldung wurde in einigen Fällen nicht angezeigt'
        ],
        'new_features' => [
            'Switch im Dashboard um nur taugliche Pilot:innen zu sehen',
        ]
    ],
    [
        'version' => '1.1.4',
        'date' => '2026-02-08',
        'changes' => [

        ],
        'bugfixes' => [
        ],
        'new_features' => [
            'API für Einsatztagebuch Integration hinzugefügt',
            'Drohnen Mission Mapper-Beta jetzt verfügbar',
        ]
    ],
    [
        'version' => '1.1.3',
        'date' => '2026-02-04',
        'changes' => [
            'Dialog für neue Installation entfernt',
            'GIT Hub Referenz gegen open-drone-tools.de getauscht'
        ],
        'bugfixes' => [
            'Dashboard aktualisiert nicht mehr, wenn Daten im Formular sind, aber der Flug noch nicht gestartet wurde',
            'Falsche Version im Login'
        ],
        'new_features' => [
            'Anonymes senden der Installation an open-drone-tools.de hinzugefügt (Version & Repo Name sonst nichts)',
            'Kontakt E-Mail in ÜBER-Seite hinzugefügt'
        ]
    ],
    [
        'version' => '1.1.2',
        'date' => '2026-01-13',
        'changes' => [

        ],
        'bugfixes' => [
            'Fehler beim Entschlüsseln von Dateien behoben',
        ],
        'new_features' => [
        ]
    ],
    [
        'version' => '1.1.1',
        'date' => '2026-01-13',
        'changes' => [

        ],
        'bugfixes' => [
        ],
        'new_features' => [
            'Dokumenten-Management-Funktion hinzugefügt (Verwaltung -> Dokumente). PDF Upload nur für Administratoren möglich.',
        ]
    ],
    [
        'version' => '1.1.0',
        'date' => '2026-01-12',
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
            'Bearbeiten von Piloten geändert',
            'Seite Einträge löschen entfernt (nun als Admin in Alle Flüge möglich)',
            'Aktive Flüge werden jetzt im Dashboard immer als erstes angezeigt',
        ],
        'bugfixes' => [
            'Multi-User-Konflikte beim Starten/Beenden von Flügen behoben',
            'Race Conditions bei gleichzeitigen Operationen behoben',
            'Doppelte Requests werden jetzt verhindert',
            'Default min Flugzeit für neue Piloten auf 45 Minuten gesetzt',
            'DB Update nach Setup nicht mehr notwendig',
            'Falscher Wert für Standort ob Einsatz oder Übung behoben',
            'UI Fehler behoben',
            
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
            'A1/A3 und A2 Fernpilotenschein Felder hinzugefügt',
            'Pilotensuchen im Dashboard hinzugefügt',
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

