# Drohnen-Flug-und-Dienstbuch

Eine Progressive Web App (PWA) zur Verwaltung von Drohnen-Flugprotokollen, Pilotinformationen, Batterieverfolgung und Flugstandorten. Entwickelt mit PHP und SQLite, konzipiert für einfache Bereitstellung und Nutzung für BOS und Drohnenbetreiber.

## Funktionen

- ✈️ **Flugprotokoll-Verwaltung**: Drohnenflüge mit detaillierten Informationen erfassen und verfolgen
- 👨‍✈️ **Pilot-Verwaltung**: Fluganforderungen verfolgen
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
│   ├── flights.php        # Flugoperationen
│   ├── pilots.php         # Pilot-Verwaltung
│   ├── drones.php         # Drohnen-Verwaltung
│   ├── locations.php      # Standort-Verwaltung
│   ├── events.php         # Ereignis-Verwaltung
│   └── migrations.php     # Migrations-Verwaltung
├── config/
│   ├── config.example.php  # Beispielkonfiguration (sicher zu committen)
│   └── config.php          # Tatsächliche Konfiguration (NICHT COMMITTEN)
├── css/                    # Stylesheets
├── icons/                  # PWA-Icons
├── includes/               # PHP-Includes
│   ├── api_helpers.php    # API-Hilfsfunktionen
│   ├── csrf.php           # CSRF-Schutz
│   ├── error_reporting.php
│   ├── header.php         # Navigations-Header
│   ├── footer.php
│   ├── migration_runner.php  # Migrations-System
│   ├── rate_limit.php     # Rate Limiting
│   ├── security_headers.php
│   └── utils.php          # Hilfsfunktionen
├── js/                     # JavaScript-Dateien
│   ├── dashboard.js       # Dashboard mit API-Integration
│   ├── manage_pilots.js
│   ├── manage_drones.js
│   ├── manage_locations.js
│   ├── add_flight.js
│   ├── add_events.js
│   └── delete_flights.js
├── migrations/             # Datenbank-Migrationen
│   ├── 001_create_schema_migrations_table.php
│   ├── 002_create_request_log_table.php
│   └── ...                # Weitere Migrationen
├── logs/                   # Anwendungsprotokolle
├── uploads/                # Verschlüsselte Datei-Uploads
├── add_events.php
├── add_flight.php
├── auth.php
├── battery_overview.php
├── dashboard.php           # Dashboard (API-basiert)
├── delete_flights.php
├── index.php              # Login-Seite
├── logout.php
├── manage_drones.php       # Drohnen-Verwaltung (API-basiert)
├── manage_locations.php    # Standort-Verwaltung (API-basiert)
├── manage_pilots.php       # Pilot-Verwaltung (API-basiert)
├── migrations.php          # Migrations-Verwaltungsseite
├── setup.php              # Initialer Setup-Assistent
├── setup_database.php     # Datenbankinitialisierung
├── service-worker.js      # PWA Service Worker
├── view_events.php
└── view_flights.php
```

## Verwendung

1. **Login**: Verwenden Sie das während des Setups festgelegte Passwort
2. **Dashboard**: Flugstatistiken und Pilotstatus anzeigen
3. **Flug hinzufügen**: Neue Flugeinträge manuell erfassen
4. **Flüge anzeigen**: Alle erfassten Flüge durchsuchen und filtern
5. **Piloten verwalten**: Pilotinformationen und -anforderungen hinzufügen/bearbeiten
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
