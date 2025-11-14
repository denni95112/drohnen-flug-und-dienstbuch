# Drohnen-Flug-und-Dienstbuch

Eine Progressive Web App (PWA) zur Verwaltung von Drohnen-Flugprotokollen, Pilotinformationen, Batterieverfolgung und Flugstandorten. Entwickelt mit PHP und SQLite, konzipiert für einfache Bereitstellung und Nutzung für BOS und Drohnenbetreiber.

## Funktionen

- ✈️ **Flugprotokoll-Verwaltung**: Drohnenflüge mit detaillierten Informationen erfassen und verfolgen
- 👨‍✈️ **Pilot-Verwaltung**: Fluganforderungen verfolgen
- 🔋 **Batterie-Verfolgung**: Batterienutzung überwachen
- 📍 **Standort-Verwaltung**: Flugstandorte speichern und verwalten mit verschlüsselten Datei-Uploads für Einsatzberichte
- 📊 **Dashboard**: Übersicht über Flugstatistiken und Pilotstatus
- 🔐 **Sichere Authentifizierung**: Passwortgeschützt mit Admin-Funktionalität
- 📱 **PWA-Unterstützung**: Installierbar als mobile/Desktop-App
- 🌍 **Multi-Plattform**: Funktioniert auf Windows- und Linux-Servern

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

**Wichtig**: Committen Sie niemals `config/config.php` in die Versionskontrolle, da sie sensible Daten enthält (Passwort-Hashes, Verschlüsselungsschlüssel).

## Sicherheitsfunktionen

- ✅ SQL-Injection-Schutz (Prepared Statements)
- ✅ CSRF-Schutz für alle Formulare
- ✅ Sichere Passwort-Hashierung (bcrypt/argon2)
- ✅ Rate Limiting für Anmeldeversuche
- ✅ Sichere Session-Verwaltung
- ✅ Verschlüsselung von Datei-Uploads
- ✅ HTTP-Sicherheitsheader
- ✅ XSS-Schutz

## Verwandte Projekte

Dieses Projekt kann zusammen mit dem **[Drohnen-Einsatztagebuch](https://github.com/denni95112/drohnen-einsatztagebuch)** verwendet werden. Das Einsatztagebuch bietet zusätzliche Funktionen zur Dokumentation von Drohnen-Einsätzen und ergänzt die Flugprotokoll-Verwaltung dieses Projekts.

## Projektstruktur

```
drohnen-flug-und-dienstbuch/
├── config/
│   ├── config.example.php  # Beispielkonfiguration (sicher zu committen)
│   └── config.php          # Tatsächliche Konfiguration (NICHT COMMITTEN)
├── css/                    # Stylesheets
├── icons/                  # PWA-Icons
├── includes/               # PHP-Includes
│   ├── csrf.php           # CSRF-Schutz
│   ├── error_reporting.php
│   ├── header.php         # Navigations-Header
│   ├── footer.php
│   ├── rate_limit.php     # Rate Limiting
│   ├── security_headers.php
│   └── utils.php          # Hilfsfunktionen
├── logs/                   # Anwendungsprotokolle
├── uploads/                # Verschlüsselte Datei-Uploads
├── add_events.php
├── add_flight.php
├── auth.php
├── battery_overview.php
├── dashboard.php
├── delete_flights.php
├── fetch_locations.php
├── index.php              # Login-Seite
├── logout.php
├── manage_drones.php
├── manage_locations.php
├── manage_pilots.php
├── setup.php              # Initialer Setup-Assistent
│   setup_database.php     # Datenbankinitialisierung
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