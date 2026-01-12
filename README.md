# Drohnen-Flug-und-Dienstbuch

Eine Progressive Web App (PWA) zur Verwaltung von Drohnen-Flugprotokollen, Pilotinformationen, Batterieverfolgung und Flugstandorten. Entwickelt mit PHP und SQLite, konzipiert für einfache Bereitstellung und Nutzung für BOS und Drohnenbetreiber.

## Funktionen

- ✈️ **Flugprotokoll-Verwaltung**: Drohnenflüge mit detaillierten Informationen erfassen und verfolgen
- 👨‍✈️ **Pilot-Verwaltung**: Fluganforderungen verfolgen mit Lizenzverwaltung und Sperrfunktion
- 🔋 **Batterie-Verfolgung**: Batterienutzung überwachen
- 📍 **Standort-Verwaltung**: Flugstandorte speichern und verwalten mit verschlüsselten Datei-Uploads für Einsatzberichte
- 📊 **Dashboard**: Übersicht über Flugstatistiken und Pilotstatus mit Auto-Refresh (30 Sekunden)
- 🔐 **Sichere Authentifizierung**: Passwortgeschützt mit Admin-Funktionalität
- 📱 **PWA-Unterstützung**: Installierbar als mobile/Desktop-App
- 🌍 **Multi-Plattform**: Funktioniert auf Windows- und Linux-Servern
- 🔄 **API-basierte Architektur**: RESTful API für alle Datenoperationen
- 👥 **Multi-User-Support**: Konfliktfreie Nutzung durch mehrere Benutzer gleichzeitig
- 🔁 **Request-Deduplizierung**: Verhindert doppelte Operationen
- 📦 **Datenbank-Migrationen**: Versionsgesteuerte Schema-Updates
- 🚀 **Automatisches Update-System**: Ein-Klick-Updates direkt über die Weboberfläche

## Screenshots 

<p float="left">
   <img src="https://github.com/user-attachments/assets/07de6c74-dc8a-4746-9fbe-101998a8f5d9" width="150" />
   <img src="https://github.com/user-attachments/assets/625e8bb9-9485-442f-ad31-0f6a1f5d4b3d" width="150" />
   <img src="https://github.com/user-attachments/assets/2dd989d9-cc3d-4f84-a162-525ec71fa360" width="150" />
   <img src="https://github.com/user-attachments/assets/591f6b35-9737-4032-9757-8fe449710238" width="150" />
   <img src="https://github.com/user-attachments/assets/02bb5f7e-3e60-4668-8733-0bcd1ded68e7" width="150" />
   <img src="https://github.com/user-attachments/assets/00aa9c68-4618-4dbd-9d57-df6be941291a" width="150" />
   <img src="https://github.com/user-attachments/assets/45752509-dde8-47d8-8bda-91aa61d3257c" width="150" />
   <img src="https://github.com/user-attachments/assets/800f5b66-d8d3-4085-ab3a-52a588091afc" width="150" />
   <img src="https://github.com/user-attachments/assets/4f86f898-8aff-413e-9e54-1abdf46f0d52" width="150" />  
   <img src="https://github.com/user-attachments/assets/1f14e56f-400f-4da9-bb1a-5b722406eb8c" width="150" />
</p>

## Anforderungen

- PHP 7.4 oder höher
- SQLite3-Erweiterung
- Webserver (Apache, Nginx oder IIS)
- Schreibrechte für Datenbank- und Upload-Verzeichnisse

## Installation

1. **Repository klonen oder herunterladen**
   ```bash
   git clone https://github.com/denni95112/drohnen-flug-und-dienstbuch.git
   cd drohnen-flug-und-dienstbuch
   ```

2. **Webserver einrichten**
   - Zeigen Sie das Dokumentenverzeichnis Ihres Webservers auf das Projektverzeichnis
   - Stellen Sie sicher, dass PHP konfiguriert ist und die SQLite3-Erweiterung aktiviert ist

3. **Berechtigungen setzen** (Linux/Unix)
   ```bash
   chmod -R 755 .
   chmod -R 777 uploads/ logs/ config/
   ```

4. **Setup ausführen**
   - Navigieren Sie zu `http://ihre-domain/setup.php` in Ihrem Browser
   - Füllen Sie die erforderlichen Informationen aus:
     - WebApp-Name
     - Kurzname (für App-Icon)
     - Navigations-Titel
     - Anwendungs-Passwort
     - Admin-Passwort
     - Datenbank-Pfad (empfohlen: außerhalb des Web-Root-Verzeichnisses für Sicherheit)
   - Klicken Sie auf "Einrichten und loslegen"

5. **Datenbank-Pfad konfigurieren** (Empfohlen für Sicherheit)
   - Wählen Sie einen Pfad außerhalb Ihres Web-Root-Verzeichnisses
   - Beispiele:
     - Windows: `C:/data/database.sqlite`
     - Linux: `/var/data/database.sqlite`
   - Der Setup-Assistent führt Sie durch diesen Prozess

## Konfiguration

Nach dem Setup wird die Konfiguration in `config/config.php` gespeichert. Sie können diese Datei manuell bearbeiten, um anzupassen:

- `debugMode`: Auf `true` setzen, um PHP-Fehler anzuzeigen (nützlich für Debugging)
- `timezone`: Zeitzone für Datums-/Zeitanzeige ändern
- `database_path`: Datenbankspeicherort aktualisieren
- `external_documentation_url`: Link zur externen Dokumentation

## API-Architektur

Die Anwendung verwendet eine RESTful API-Architektur. Alle Datenoperationen werden über API-Endpunkte abgewickelt:

### API-Endpunkte

- **`/api/flights.php`** - Flugoperationen
  - `GET ?action=dashboard` - Dashboard-Daten abrufen
  - `GET ?action=list` - Flugliste abrufen
  - `POST ?action=start` - Flug starten (vom Dashboard)
  - `POST ?action=end` - Flug beenden (vom Dashboard)
  - `POST ?action=create` - Flug mit Datum erstellen
  - `DELETE ?id=X` - Flug löschen

- **`/api/pilots.php`** - Pilot-Verwaltung
  - `GET ?action=list` - Alle Piloten abrufen
  - `POST ?action=create` - Neuen Piloten erstellen
  - `POST ?action=update&id=X` - Piloten bearbeiten (alle Felder)
  - `PUT ?id=X&action=minutes` - Benötigte Flugminuten aktualisieren
  - `DELETE ?id=X` - Piloten löschen

- **`/api/drones.php`** - Drohnen-Verwaltung
  - `GET ?action=list` - Alle Drohnen abrufen
  - `POST ?action=create` - Neue Drohne erstellen
  - `DELETE ?id=X` - Drohne löschen

- **`/api/locations.php`** - Standort-Verwaltung
  - `GET ?action=list` - Standorte abrufen (optional: `&date=YYYY-MM-DD` für Filter)
  - `POST ?action=create` - Neuen Standort erstellen
  - `POST ?action=upload` - Datei für Standort hochladen (multipart/form-data)
  - `DELETE ?id=X` - Standort löschen

- **`/api/events.php`** - Ereignis-Verwaltung
  - `GET ?action=list` - Ereignisse abrufen (optional: `&year=YYYY` für Filter)
  - `POST ?action=create` - Neues Ereignis erstellen
  - `DELETE ?id=X` - Ereignis löschen

- **`/api/migrations.php`** - Datenbank-Migrationen
  - `GET ?action=list` - Verfügbare Migrationen anzeigen
  - `GET ?action=status` - Status der Migrationen prüfen
  - `POST ?action=run` - Migration ausführen (nur Admin)

### API-Features

- **Request-Deduplizierung**: Verhindert doppelte Operationen durch eindeutige Request-IDs
- **Concurrency Control**: Optimistic Locking verhindert Konflikte bei gleichzeitiger Nutzung
- **CSRF-Schutz**: Alle POST/PUT/DELETE-Requests erfordern CSRF-Token
- **Authentifizierung**: Alle Endpunkte erfordern Authentifizierung
- **JSON-Format**: Einheitliches JSON-Request/Response-Format

### Beispiel-Request

```javascript
// Flug starten
fetch('api/flights.php?action=start', {
    method: 'POST',
    headers: {
        'Content-Type': 'application/json',
    },
    body: JSON.stringify({
        pilot_id: 1,
        drone_id: 2,
        location_id: 3,
        battery_number: 1,
        request_id: 'unique-request-id',
        csrf_token: 'csrf-token-from-session'
    })
});
```

## Datenbank-Migrationen

Die Anwendung verwendet ein Migrationssystem zur Verwaltung von Datenbank-Schema-Änderungen.

### Migrationen ausführen

1. Navigieren Sie zu `migrations.php` im Browser
2. Die Seite zeigt alle verfügbaren Migrationen (höchste Nummer zuerst)
3. Nur Administratoren können Migrationen ausführen
4. Klicken Sie auf "Ausführen" neben einer ausstehenden Migration

### Migrationen erstellen

Migrationen befinden sich im `migrations/` Verzeichnis und folgen dem Format:
- `001_beschreibung.php`
- `002_beschreibung.php`
- etc.

Jede Migration muss zwei Funktionen enthalten:
- `up($db)` - Führt die Migration aus
- `down($db)` - Rollback-Funktion (optional)

### Beispiel-Migration

```php
<?php
function up($db) {
    $db->exec('CREATE TABLE IF NOT EXISTS new_table (...)');
    return true;
}

function down($db) {
    $db->exec('DROP TABLE IF EXISTS new_table');
    return true;
}
```

### Migration-Benachrichtigung

Wenn ausstehende Migrationen vorhanden sind, wird ein Benachrichtigungssymbol in der Kopfzeile angezeigt, das zur Migrations-Seite führt.

## Automatisches Update-System

Die Anwendung verfügt über ein integriertes Update-System, das es Administratoren ermöglicht, die Anwendung direkt über die Weboberfläche zu aktualisieren.

### Update-Benachrichtigung

- Wenn eine neue Version verfügbar ist, wird ein Benachrichtigungssymbol in der Kopfzeile angezeigt
- **Für Administratoren**: Klicken auf die Benachrichtigung führt direkt zum Update-Tool
- **Für normale Benutzer**: Klicken auf die Benachrichtigung führt zur GitHub-Release-Seite

### Update-Tool verwenden

1. **Zugriff**: Navigieren Sie zu `Verwaltung > Update Tool` (nur für Administratoren)
2. **Update prüfen**: Klicken Sie auf "Auf Updates prüfen", um nach verfügbaren Updates zu suchen
3. **Update installieren**: Wenn ein Update verfügbar ist, klicken Sie auf "Jetzt aktualisieren"
4. **Fortschritt**: Der Update-Fortschritt wird in Echtzeit angezeigt

### Wie funktioniert das Update?

Das Update-System:
- **Lädt automatisch** die neueste Release-Version von GitHub herunter
- **Erstellt automatisch ein Backup** aller geschützten Dateien vor dem Update
- **Schützt wichtige Dateien** während des Updates:
  - `config/config.php` (Konfiguration)
  - `config/` Verzeichnis
  - `uploads/` Verzeichnis (hochgeladene Dateien)
  - `logs/` Verzeichnis
  - Datenbankdateien (`.sqlite`, `.sqlite3`, `.db`)
- **Kopiert neue/aktualisierte Dateien** aus dem Release
- **Entfernt veraltete Dateien**, die nicht mehr im Release enthalten sind
- **Stellt geschützte Dateien wieder her** nach dem Update
- **Führt automatisch ein Rollback durch**, falls ein Fehler auftritt

### Update-Anforderungen

- **Admin-Zugriff**: Nur Administratoren können Updates durchführen
- **Schreibrechte**: Der Webserver benötigt Schreibrechte auf das Projektverzeichnis
- **PHP-ZipArchive**: Die PHP-ZipArchive-Erweiterung muss installiert sein

### Update-Logs

Update-Protokolle werden in `logs/updater.log` gespeichert und enthalten:
- Update-Prüfungen
- Heruntergeladene Versionen
- Update-Fortschritt
- Erfolgreiche Updates
- Fehler und Warnungen

### Fehlerbehebung bei Updates

**Update schlägt fehl:**
- Überprüfen Sie die Update-Logs in `logs/updater.log`
- Stellen Sie sicher, dass der Webserver Schreibrechte hat
- Überprüfen Sie die Internetverbindung
- Aktivieren Sie `debugMode` in der Konfiguration für detailliertere Fehlermeldungen

**SSL-Fehler:**
- Wenn SSL-Verifizierungsfehler auftreten, können Sie `debugMode` in der Konfiguration aktivieren
- Dies deaktiviert die SSL-Verifizierung (weniger sicher, aber funktioniert in Entwicklungsumgebungen)

**Cache-Probleme:**
- Die Versionsprüfung verwendet einen Cache (1 Stunde)
- Bei Problemen können Sie die Cache-Datei `logs/github_version_cache.json` löschen

## Sicherheitsfunktionen

- ✅ SQL-Injection-Schutz (Prepared Statements)
- ✅ CSRF-Schutz für alle Formulare und API-Requests
- ✅ Sichere Passwort-Hashierung (bcrypt/argon2)
- ✅ Rate Limiting für Anmeldeversuche
- ✅ Sichere Session-Verwaltung
- ✅ Verschlüsselung von Datei-Uploads
- ✅ HTTP-Sicherheitsheader
- ✅ XSS-Schutz
- ✅ Request-Deduplizierung zur Verhinderung von Doppeloperationen
- ✅ Concurrency Control für Multi-User-Szenarien

## Verwandte Projekte

Dieses Projekt kann zusammen mit dem **[Drohnen-Einsatztagebuch](https://github.com/denni95112/drohnen-einsatztagebuch)** verwendet werden. Das Einsatztagebuch bietet zusätzliche Funktionen zur Dokumentation von Drohnen-Einsätzen und ergänzt die Flugprotokoll-Verwaltung dieses Projekts.

## Projektstruktur

```
drohnen-flug-und-dienstbuch/
├── api/                    # API-Endpunkte
│   ├── admin_api.php      # Admin-API
│   ├── drones.php         # Drohnen-Verwaltung
│   ├── events.php         # Ereignis-Verwaltung
│   ├── flights.php        # Flugoperationen
│   ├── install_notification_api.php  # Installationsbenachrichtigung
│   ├── locations.php      # Standort-Verwaltung
│   ├── migrations.php     # Migrations-Verwaltung
│   ├── pilots.php         # Pilot-Verwaltung
│   └── fetch_locations.php # Legacy: Standort-Abruf
├── config/
│   ├── config.example.php  # Beispielkonfiguration (sicher zu committen)
│   └── config.php          # Tatsächliche Konfiguration (NICHT COMMITTEN)
├── css/                    # Stylesheets
├── dev/                    # Entwicklungs-/Debug-Dateien
│   └── debug_passwords.php # Passwort-Debug-Tool (nur für Entwicklung)
├── icons/                  # PWA-Icons
├── includes/               # PHP-Includes und System-Dateien
│   ├── api_helpers.php    # API-Hilfsfunktionen
│   ├── auth.php           # Authentifizierung
│   ├── csrf.php           # CSRF-Schutz
│   ├── dashboard_helpers.php
│   ├── error_reporting.php
│   ├── footer.php
│   ├── header.php         # Navigations-Header
│   ├── migration_runner.php  # Migrations-System
│   ├── rate_limit.php     # Rate Limiting
│   ├── security_headers.php
│   ├── utils.php          # Hilfsfunktionen
│   └── version.php        # Versionsinformationen
├── js/                     # JavaScript-Dateien
│   ├── add_events.js
│   ├── add_flight.js
│   ├── dashboard.js       # Dashboard mit API-Integration
│   ├── delete_flights.js
│   ├── header.js
│   ├── index.js
│   ├── install_notification.js
│   ├── manage_drones.js
│   ├── manage_locations.js
│   ├── manage_pilots.js
│   ├── setup.js
│   └── view_events.js
├── migrations/             # Datenbank-Migrationen
│   ├── 001_create_schema_migrations_table.php
│   ├── 002_create_request_log_table.php
│   └── ...                # Weitere Migrationen
├── pages/                  # Benutzeroberflächen-Seiten
│   ├── add_events.php     # Dienst anlegen
│   ├── add_flight.php     # Manueller Flugeintrag
│   ├── battery_overview.php  # Akku-Übersicht
│   ├── changelog.php      # Changelog
│   ├── dashboard.php      # Dashboard (API-basiert)
│   ├── delete_flights.php # Flüge löschen
│   ├── logout.php         # Logout
│   ├── manage_drones.php  # Drohnen-Verwaltung (API-basiert)
│   ├── manage_locations.php  # Standort-Verwaltung (API-basiert)
│   ├── manage_pilots.php  # Pilot-Verwaltung (API-basiert)
│   ├── migrations.php    # Migrations-Verwaltungsseite
│   ├── view_events.php    # Dienste ansehen
│   └── view_flights.php   # Alle Flüge anzeigen
├── setup/                  # Setup- und Migrations-Skripte
│   ├── migrate_database.php  # Datenbank-Migrationsskript
│   └── setup_database.php   # Datenbankinitialisierung
├── updater/                # Automatisches Update-System
│   ├── updater.php        # Updater-Klasse
│   ├── updater_page.php   # Update-Tool Benutzeroberfläche
│   ├── updater_api.php    # Update-API-Endpunkt
│   ├── updater.js         # Update-Tool JavaScript
│   └── updater.css        # Update-Tool Stylesheet
├── logs/                   # Anwendungsprotokolle
├── uploads/                # Verschlüsselte Datei-Uploads
├── index.php              # Login-Seite (Haupteingangspunkt)
├── setup.php              # Initialer Setup-Assistent
├── manifest.json          # PWA-Manifest (muss im Root sein)
└── service-worker.js      # PWA Service Worker (muss im Root sein)
```

## Pilot-Verwaltung

Die Pilot-Verwaltung bietet umfassende Funktionen zur Verwaltung von Piloten und deren Lizenzen.

### Funktionen

- **Pilot-Informationen**: Name und benötigte Flugminuten pro 3 Monate
- **Lizenz-Verwaltung**: 
  - A1/A3 Fernpilotenschein mit ID und Ablaufdatum
  - A2 Fernpilotenschein mit ID und Ablaufdatum
  - Beide Lizenzen sind optional
- **Sperrfunktion**: Option "Sperren wenn Fernpilotenschein ungültig"
  - Wenn aktiviert, muss mindestens eine Lizenz mit gültigem Ablaufdatum angegeben werden
  - Piloten mit ungültigen Lizenzen können keine neuen Flüge starten
  - Wird im Dashboard mit rotem Hintergrund und Warnung angezeigt
- **Sortierung**: 
  - Sortierung nach ID, Name (Standard), A1/A3 Ablaufdatum oder A2 Ablaufdatum
- **Bearbeitung**: 
  - Vollständige Bearbeitung aller Pilot-Informationen über ein Modal
  - Keine Admin-Rechte erforderlich für die Bearbeitung

### Verwendung

1. **Pilot hinzufügen**:
   - Name eingeben (Pflichtfeld)
   - Benötigte Flugminuten festlegen (Standard: 45)
   - Optional: A1/A3 und/oder A2 Lizenz-Informationen eingeben
   - Optional: "Sperren wenn Fernpilotenschein ungültig" aktivieren
   
2. **Pilot bearbeiten**:
   - Auf "Bearbeiten" klicken
   - Alle Felder im Modal anpassen
   - Änderungen speichern

3. **Sortierung**:
   - Dropdown-Menü "Sortieren nach" verwenden
   - Auswahl zwischen ID, Name, A1/A3 Ablaufdatum oder A2 Ablaufdatum

4. **Lizenz-Sperre**:
   - Wenn aktiviert und keine gültige Lizenz vorhanden:
     - Pilot wird im Dashboard rot angezeigt
     - Warnung: "⚠️ Fernpilotenschein ungültig - Flug kann nicht gestartet werden"
     - Flug-Start-Formular ist deaktiviert

## Verwendung

1. **Login**: Verwenden Sie das während des Setups festgelegte Passwort
2. **Dashboard**: Flugstatistiken und Pilotstatus anzeigen
3. **Flug hinzufügen**: Neue Flugeinträge manuell erfassen
4. **Flüge anzeigen**: Alle erfassten Flüge durchsuchen und filtern
5. **Piloten verwalten**: Pilotinformationen und -anforderungen hinzufügen/bearbeiten (siehe [Pilot-Verwaltung](#pilot-verwaltung))
6. **Drohnen verwalten**: Drohnenbestand verfolgen
7. **Standorte verwalten**: Flugstandorte mit optionalen Dateianhängen hinzufügen
8. **Batterie-Übersicht**: Batterienutzung über Flüge hinweg überwachen
9. **Admin-Funktionen**: Auf Admin-Funktionen mit Admin-Passwort zugreifen


## Fehlerbehebung

### Datenbankverbindungsfehler

- Überprüfen Sie die Dateiberechtigungen im Datenbankverzeichnis
- Überprüfen Sie den Datenbankpfad in `config/config.php`
- Stellen Sie sicher, dass die SQLite3-Erweiterung aktiviert ist: `php -m | grep sqlite`

### Berechtigungsfehler

- Stellen Sie sicher, dass der Webserver Lese-/Schreibzugriff auf folgende Verzeichnisse hat:
  - `db/` Verzeichnis
  - `uploads/` Verzeichnis
  - `logs/` Verzeichnis
  - `config/` Verzeichnis

### Setup funktioniert nicht

- Überprüfen Sie die PHP-Fehlerprotokolle
- Aktivieren Sie `debugMode` in der Konfiguration, um Fehler zu sehen
- Überprüfen Sie, ob alle erforderlichen PHP-Erweiterungen installiert sind

## Lizenz

Dieses Projekt ist unter der MIT-Lizenz lizenziert - siehe [LICENSE](LICENSE) Datei für Details.

## Autor

**Dennis Bögner (denni95112)**

- GitHub: [@denni95112](https://github.com/denni95112)
- Repository: [drohnen-flug-und-dienstbuch](https://github.com/denni95112/drohnen-flug-und-dienstbuch)
