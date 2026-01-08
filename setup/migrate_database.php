<?php
/**
 * Database Migration Script
 * 
 * This script migrates an old database structure to the new structure.
 * It adds missing tables, indexes, and enables foreign key constraints.
 * 
 * Usage: Run this file once to migrate your existing database.
 */

require_once __DIR__ . '/../includes/error_reporting.php';
require_once __DIR__ . '/../includes/utils.php';
require_once __DIR__ . '/../includes/version.php';

$dbPath = getDatabasePath();

if (!file_exists($dbPath)) {
    die("<pre>FEHLER: Die Datenbankdatei wurde nicht gefunden.\nPfad: " . htmlspecialchars($dbPath) . "\n\nBitte führen Sie zuerst setup_database.php aus.</pre>");
}

try {
    $db = new SQLite3($dbPath);
} catch (Exception $e) {
    die("<pre>FEHLER: Die Datenbankdatei konnte nicht geöffnet werden.\nPfad: " . htmlspecialchars($dbPath) . "\n\nOriginal-Fehlermeldung: " . htmlspecialchars($e->getMessage()) . "</pre>");
}

$migrations = [];
$errors = [];

try {
    $db->exec('PRAGMA foreign_keys = ON');
    $migrations[] = "✓ Foreign key constraints aktiviert";
} catch (Exception $e) {
    $errors[] = "✗ Fehler beim Aktivieren der Foreign Key Constraints: " . $e->getMessage();
}
try {
    $result = $db->query("SELECT name FROM sqlite_master WHERE type='table' AND name='auth_tokens'");
    if ($result->fetchArray() === false) {
        $db->exec('CREATE TABLE auth_tokens (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            token TEXT NOT NULL UNIQUE,
            user_id INTEGER DEFAULT 1,
            expires_at DATETIME NOT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        )');
        $migrations[] = "✓ Tabelle 'auth_tokens' erstellt";
    } else {
        $migrations[] = "→ Tabelle 'auth_tokens' existiert bereits";
    }
} catch (Exception $e) {
    $errors[] = "✗ Fehler beim Erstellen der Tabelle 'auth_tokens': " . $e->getMessage();
}

try {
    $result = $db->query("SELECT name FROM sqlite_master WHERE type='table' AND name='rate_limits'");
    if ($result->fetchArray() === false) {
        $db->exec('CREATE TABLE rate_limits (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            ip_address TEXT NOT NULL,
            action TEXT NOT NULL,
            attempts INTEGER DEFAULT 1,
            first_attempt DATETIME DEFAULT CURRENT_TIMESTAMP,
            last_attempt DATETIME DEFAULT CURRENT_TIMESTAMP,
            blocked_until DATETIME,
            UNIQUE(ip_address, action)
        )');
        $migrations[] = "✓ Tabelle 'rate_limits' erstellt";
    } else {
        $migrations[] = "→ Tabelle 'rate_limits' existiert bereits";
    }
} catch (Exception $e) {
    $errors[] = "✗ Fehler beim Erstellen der Tabelle 'rate_limits': " . $e->getMessage();
}

try {
    $tableCheck = $db->query("SELECT name FROM sqlite_master WHERE type='table' AND name='pilots'");
    if ($tableCheck->fetchArray() === false) {
        $migrations[] = "→ Tabelle 'pilots' existiert nicht (wird von setup_database.php erstellt)";
    } else {
        $pilotsExists = $db->query("SELECT name FROM sqlite_master WHERE type='table' AND name='pilots'")->fetchArray() !== false;
        $pilotsNewExists = $db->query("SELECT name FROM sqlite_master WHERE type='table' AND name='pilots_new'")->fetchArray() !== false;
        
        if ($pilotsNewExists && !$pilotsExists) {
            try {
                $db->exec('PRAGMA foreign_keys = OFF');
                $db->exec('PRAGMA busy_timeout = 5000');
                $db->exec('BEGIN IMMEDIATE TRANSACTION');
                $db->exec('ALTER TABLE pilots_new RENAME TO pilots');
                $db->exec('COMMIT');
                $db->exec('PRAGMA foreign_keys = ON');
                $migrations[] = "✓ Wiederherstellung: 'pilots_new' wurde zu 'pilots' umbenannt (partielle Migration erkannt)";
            } catch (Exception $recoveryError) {
                try {
                    $db->exec('ROLLBACK');
                } catch (Exception $rollbackError) {
                }
                $db->exec('PRAGMA foreign_keys = ON');
                $errors[] = "✗ Fehler bei Wiederherstellung: " . $recoveryError->getMessage();
            }
        } elseif ($pilotsNewExists && $pilotsExists) {
            try {
                $db->exec('PRAGMA foreign_keys = OFF');
                $db->exec('PRAGMA busy_timeout = 5000');
                $db->exec('DROP TABLE IF EXISTS pilots_new');
                $db->exec('PRAGMA foreign_keys = ON');
                $migrations[] = "✓ Aufgeräumt: Alte 'pilots_new' Tabelle entfernt";
            } catch (Exception $cleanupError) {
                $errors[] = "⚠ Warnung: Konnte 'pilots_new' nicht entfernen: " . $cleanupError->getMessage();
            }
        }
        
        $result = $db->query("PRAGMA table_info(pilots)");
        $allColumns = [];
        $hasOldColumn = false;
        $hasNewColumn = false;
        
        while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
            $allColumns[] = $row['name'];
            if ($row['name'] === 'num_of_flights_needed') {
                $hasOldColumn = true;
            }
            if ($row['name'] === 'minutes_of_flights_needed') {
                $hasNewColumn = true;
            }
        }
        
        $migrations[] = "→ Gefundene Spalten in 'pilots': " . implode(', ', $allColumns);
        
        if ($hasOldColumn && !$hasNewColumn) {
            $sqliteVersion = SQLite3::version();
            $useRenameColumn = version_compare($sqliteVersion['versionString'], '3.25.0', '>=');
            
            if ($useRenameColumn) {
                try {
                    $db->exec('BEGIN IMMEDIATE TRANSACTION');
                    $db->exec('ALTER TABLE pilots RENAME COLUMN num_of_flights_needed TO minutes_of_flights_needed');
                    $db->exec('COMMIT');
                    $migrations[] = "✓ Spalte 'num_of_flights_needed' zu 'minutes_of_flights_needed' umbenannt (ALTER TABLE RENAME COLUMN)";
                } catch (Exception $e) {
                    try {
                        $db->exec('ROLLBACK');
                    } catch (Exception $rollbackError) {
                    }
                    $useRenameColumn = false;
                }
            }
            
            if (!$useRenameColumn) {
                $db->close();
                usleep(500000);
                $db = new SQLite3($dbPath);
                $db->exec('PRAGMA foreign_keys = OFF');
                $db->exec('PRAGMA busy_timeout = 5000');
                
                $checkNew = $db->query("SELECT name FROM sqlite_master WHERE type='table' AND name='pilots_new'");
                if ($checkNew->fetchArray() !== false) {
                    $db->exec('DROP TABLE IF EXISTS pilots_new');
                }
                
                $db->exec('BEGIN IMMEDIATE TRANSACTION');
                
                try {
                    $checkNew = $db->query("SELECT name FROM sqlite_master WHERE type='table' AND name='pilots_new'");
                    if ($checkNew->fetchArray() !== false) {
                        $db->exec('DROP TABLE pilots_new');
                    }
                    
                    $db->exec('CREATE TABLE pilots_new (
                        id INTEGER PRIMARY KEY,
                        name TEXT NOT NULL,
                        minutes_of_flights_needed INTEGER NOT NULL DEFAULT 3,
                        last_flight DATE
                    )');
                    
                    $db->exec('INSERT INTO pilots_new (id, name, minutes_of_flights_needed, last_flight)
                               SELECT id, name, num_of_flights_needed, last_flight FROM pilots');
                    
                    $countOld = $db->querySingle('SELECT COUNT(*) FROM pilots');
                    $countNew = $db->querySingle('SELECT COUNT(*) FROM pilots_new');
                    
                    if ($countNew != $countOld) {
                        throw new Exception("Datenverlust: Alte Tabelle hatte {$countOld} Einträge, neue Tabelle hat {$countNew} Einträge");
                    }
                    
                    $db->exec('DROP TABLE pilots');
                    $db->exec('ALTER TABLE pilots_new RENAME TO pilots');
                    $db->exec('COMMIT');
                    $db->exec('PRAGMA foreign_keys = ON');
                    
                    $migrations[] = "✓ Spalte 'num_of_flights_needed' zu 'minutes_of_flights_needed' umbenannt in Tabelle 'pilots' ({$countNew} Einträge migriert)";
                } catch (Exception $e) {
                    try {
                        $db->exec('ROLLBACK');
                    } catch (Exception $rollbackError) {
                    }
                    $db->exec('PRAGMA foreign_keys = ON');
                    throw $e;
                }
            }
        } elseif ($hasOldColumn && $hasNewColumn) {
            $db->close();
            usleep(500000);
            $db = new SQLite3($dbPath);
            $db->exec('PRAGMA foreign_keys = OFF');
            $db->exec('PRAGMA busy_timeout = 5000');
            
            $checkNew = $db->query("SELECT name FROM sqlite_master WHERE type='table' AND name='pilots_new'");
            if ($checkNew->fetchArray() !== false) {
                $db->exec('DROP TABLE IF EXISTS pilots_new');
            }
            
            $db->exec('BEGIN IMMEDIATE TRANSACTION');
            
            try {
                $db->exec('UPDATE pilots SET minutes_of_flights_needed = num_of_flights_needed WHERE minutes_of_flights_needed = 3 OR minutes_of_flights_needed IS NULL');
                
                $checkNew = $db->query("SELECT name FROM sqlite_master WHERE type='table' AND name='pilots_new'");
                if ($checkNew->fetchArray() !== false) {
                    $db->exec('DROP TABLE pilots_new');
                }
                
                $db->exec('CREATE TABLE pilots_new (
                    id INTEGER PRIMARY KEY,
                    name TEXT NOT NULL,
                    minutes_of_flights_needed INTEGER NOT NULL DEFAULT 3,
                    last_flight DATE
                )');
                
                $db->exec('INSERT INTO pilots_new (id, name, minutes_of_flights_needed, last_flight)
                           SELECT id, name, minutes_of_flights_needed, last_flight FROM pilots');
                
                $countOld = $db->querySingle('SELECT COUNT(*) FROM pilots');
                $countNew = $db->querySingle('SELECT COUNT(*) FROM pilots_new');
                
                if ($countNew != $countOld) {
                    throw new Exception("Datenverlust: Alte Tabelle hatte {$countOld} Einträge, neue Tabelle hat {$countNew} Einträge");
                }
                
                $db->exec('DROP TABLE pilots');
                $db->exec('ALTER TABLE pilots_new RENAME TO pilots');
                $db->exec('COMMIT');
                $db->exec('PRAGMA foreign_keys = ON');
                
                $migrations[] = "✓ Spalte 'num_of_flights_needed' entfernt und Daten nach 'minutes_of_flights_needed' migriert ({$countNew} Einträge)";
            } catch (Exception $e) {
                try {
                    $db->exec('ROLLBACK');
                } catch (Exception $rollbackError) {
                }
                $db->exec('PRAGMA foreign_keys = ON');
                throw $e;
            }
        } elseif ($hasNewColumn) {
            $migrations[] = "→ Spalte 'minutes_of_flights_needed' existiert bereits";
        } else {
            $errors[] = "⚠ Warnung: Tabelle 'pilots' hat weder 'num_of_flights_needed' noch 'minutes_of_flights_needed'. Gefundene Spalten: " . implode(', ', $allColumns);
        }
        
        $verifyResult = $db->query("PRAGMA table_info(pilots)");
        $hasMinutesColumn = false;
        while ($row = $verifyResult->fetchArray(SQLITE3_ASSOC)) {
            if ($row['name'] === 'minutes_of_flights_needed') {
                $hasMinutesColumn = true;
                break;
            }
        }
        
        if ($hasMinutesColumn) {
            $migrations[] = "✓ Verifizierung: Spalte 'minutes_of_flights_needed' existiert in Tabelle 'pilots'";
        } else {
            $errors[] = "✗ Verifizierung fehlgeschlagen: Spalte 'minutes_of_flights_needed' wurde nicht gefunden";
        }
    }
} catch (Exception $e) {
    $errorMsg = $e->getMessage();
    if (strpos($errorMsg, 'locked') !== false || strpos($errorMsg, 'database is locked') !== false) {
        $errors[] = "✗ Fehler beim Migrieren der Spalte: Datenbank ist gesperrt. Bitte stellen Sie sicher, dass:\n" .
                    "  1. Alle anderen Verbindungen zur Datenbank geschlossen sind\n" .
                    "  2. Keine anderen Skripte oder Prozesse auf die Datenbank zugreifen\n" .
                    "  3. Versuchen Sie es nach einigen Sekunden erneut\n" .
                    "   Original-Fehler: " . $errorMsg;
    } else {
        $errors[] = "✗ Fehler beim Migrieren der Spalte: " . $errorMsg;
    }
    try {
        $db->exec('PRAGMA foreign_keys = ON');
    } catch (Exception $fkError) {
    }
}
$indexes = [
    'idx_flights_pilot_date' => 'CREATE INDEX IF NOT EXISTS idx_flights_pilot_date ON flights(pilot_id, flight_date)',
    'idx_flights_drone' => 'CREATE INDEX IF NOT EXISTS idx_flights_drone ON flights(drone_id)',
    'idx_flights_location' => 'CREATE INDEX IF NOT EXISTS idx_flights_location ON flights(flight_location_id)',
    'idx_auth_tokens_expires' => 'CREATE INDEX IF NOT EXISTS idx_auth_tokens_expires ON auth_tokens(expires_at)',
    'idx_auth_tokens_token' => 'CREATE INDEX IF NOT EXISTS idx_auth_tokens_token ON auth_tokens(token)',
    'idx_flight_locations_created' => 'CREATE INDEX IF NOT EXISTS idx_flight_locations_created ON flight_locations(created_at)',
    'idx_events_start_date' => 'CREATE INDEX IF NOT EXISTS idx_events_start_date ON events(event_start_date)',
    'idx_pilot_events_event' => 'CREATE INDEX IF NOT EXISTS idx_pilot_events_event ON pilot_events(event_id)',
    'idx_pilot_events_pilot' => 'CREATE INDEX IF NOT EXISTS idx_pilot_events_pilot ON pilot_events(pilot_id)',
    'idx_rate_limits_ip_action' => 'CREATE INDEX IF NOT EXISTS idx_rate_limits_ip_action ON rate_limits(ip_address, action)'
];

foreach ($indexes as $indexName => $sql) {
    try {
        $result = $db->query("SELECT name FROM sqlite_master WHERE type='index' AND name='{$indexName}'");
        if ($result->fetchArray() === false) {
            $db->exec($sql);
            $migrations[] = "✓ Index '{$indexName}' erstellt";
        } else {
            $migrations[] = "→ Index '{$indexName}' existiert bereits";
        }
    } catch (Exception $e) {
        $errors[] = "✗ Fehler beim Erstellen des Index '{$indexName}': " . $e->getMessage();
    }
}

try {
    $result = $db->query('PRAGMA foreign_keys');
    $fkEnabled = $result->fetchArray(SQLITE3_NUM);
    if ($fkEnabled && $fkEnabled[0] == 1) {
        $migrations[] = "✓ Foreign Key Constraints sind aktiviert";
    } else {
        $db->exec('PRAGMA foreign_keys = ON');
        $result = $db->query('PRAGMA foreign_keys');
        $fkEnabled = $result->fetchArray(SQLITE3_NUM);
        if ($fkEnabled && $fkEnabled[0] == 1) {
            $migrations[] = "✓ Foreign Key Constraints aktiviert";
        } else {
            $errors[] = "⚠ Warnung: Foreign Key Constraints konnten nicht aktiviert werden";
        }
    }
} catch (Exception $e) {
    $errors[] = "✗ Fehler beim Überprüfen der Foreign Key Constraints: " . $e->getMessage();
}

try {
    $db->close();
} catch (Exception $e) {
}

?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Datenbank-Migration</title>
    <link rel="stylesheet" href="css/styles.css?v=<?php echo APP_VERSION; ?>">
    <style>
        .migration-container {
            max-width: 800px;
            margin: 2rem auto;
            padding: 2rem;
            background: white;
            border-radius: 16px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
        }
        .migration-header {
            text-align: center;
            margin-bottom: 2rem;
        }
        .migration-header h1 {
            color: #1e3c72;
            margin-bottom: 0.5rem;
        }
        .migration-list {
            list-style: none;
            padding: 0;
            margin: 1.5rem 0;
        }
        .migration-list li {
            padding: 0.75rem 1rem;
            margin-bottom: 0.5rem;
            border-radius: 8px;
            background: #f7fafc;
            border-left: 4px solid #667eea;
        }
        .error-list {
            list-style: none;
            padding: 0;
            margin: 1.5rem 0;
        }
        .error-list li {
            padding: 0.75rem 1rem;
            margin-bottom: 0.5rem;
            border-radius: 8px;
            background: #fef2f2;
            border-left: 4px solid #dc2626;
            color: #dc2626;
        }
        .success-message {
            background: linear-gradient(135deg, #10b981 0%, #059669 100%);
            color: white;
            padding: 1.5rem;
            border-radius: 12px;
            margin: 1.5rem 0;
            text-align: center;
            font-weight: 600;
        }
        .warning-message {
            background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%);
            color: white;
            padding: 1.5rem;
            border-radius: 12px;
            margin: 1.5rem 0;
            text-align: center;
            font-weight: 600;
        }
        .info-box {
            background: linear-gradient(135deg, #f7fafc 0%, #edf2f7 100%);
            padding: 1.5rem;
            border-radius: 12px;
            margin: 1.5rem 0;
            border-left: 4px solid #667eea;
        }
    </style>
</head>
<body>
    <div class="migration-container">
        <div class="migration-header">
            <h1>Datenbank-Migration</h1>
            <p>Migration von alter Datenbankstruktur zur neuen Struktur</p>
        </div>

        <?php if (!empty($migrations)): ?>
            <div class="info-box">
                <h3>Durchgeführte Migrationen:</h3>
                <ul class="migration-list">
                    <?php foreach ($migrations as $migration): ?>
                        <li><?= htmlspecialchars($migration); ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <?php if (!empty($errors)): ?>
            <div class="warning-message">
                <h3>⚠ Warnungen/Fehler:</h3>
                <ul class="error-list">
                    <?php foreach ($errors as $error): ?>
                        <li><?= htmlspecialchars($error); ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <?php if (empty($errors)): ?>
            <div class="success-message">
                ✓ Migration erfolgreich abgeschlossen!<br>
                Ihre Datenbank wurde auf die neueste Struktur aktualisiert.
            </div>
        <?php else: ?>
            <div class="warning-message">
                ⚠ Migration mit Warnungen abgeschlossen.<br>
                Bitte überprüfen Sie die Fehlermeldungen oben.
            </div>
        <?php endif; ?>

        <div style="text-align: center; margin-top: 2rem;">
            <a href="../pages/dashboard.php" style="display: inline-block; padding: 0.75rem 1.5rem; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; text-decoration: none; border-radius: 10px; font-weight: 600; box-shadow: 0 4px 12px rgba(102, 126, 234, 0.3);">
                Zum Dashboard
            </a>
        </div>

        <div class="info-box" style="margin-top: 2rem;">
            <strong>Hinweis:</strong> Diese Migration kann sicher mehrfach ausgeführt werden. 
            Bereits vorhandene Tabellen und Indizes werden nicht überschrieben.
        </div>
    </div>
</body>
</html>

