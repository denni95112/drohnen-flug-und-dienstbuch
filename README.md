# Drohnen-Flug-und-Dienstbuch

Eine Progressive Web App (PWA) zur Verwaltung von Drohnen-Flugprotokollen, Pilotinformationen, Batterieverfolgung und Flugstandorten. Entwickelt mit PHP und SQLite, konzipiert für einfache Bereitstellung und Nutzung für BOS und Drohnenbetreiber.

📖 **Ausführliche Anleitung**: [Wiki](https://github.com/denni95112/drohnen-flug-und-dienstbuch/wiki)

---

## ✨ Funktionen

- ✈️ **Flugprotokoll-Verwaltung** – Flüge erfassen, starten und beenden
- 👨‍✈️ **Pilot-Verwaltung** – Lizenzverwaltung, Mindestflugzeiten
- 🔋 **Batterie-Verfolgung** – Nutzung pro Drohne überwachen
- 📍 **Standort-Verwaltung** – Flugstandorte mit GPS und optionalen Datei-Uploads
- 📄 **Dokumenten-Verwaltung** – PDF-Dokumente verschlüsselt hochladen und teilen
- 📊 **Dashboard** – Pilotstatus, Flugstart/-ende
- 📅 **Dienstbuch** – Dienste, Einsätze und Verwaltungstermine
- 🔐 **Authentifizierung** – Passwort + Admin-Rechte
- 📱 **PWA** – Installierbar als App ([Anleitung](https://github.com/denni95112/drohnen-flug-und-dienstbuch/wiki/PWA-installieren))

---

## 📸 Screenshots

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

---

## 🚀 Schnellstart

### Anforderungen

- PHP 7.4+
- SQLite3-Erweiterung
- Webserver (Apache, Nginx oder IIS)

### Installation

1. Repository klonen und ins Projektverzeichnis wechseln:
   ```bash
   git clone https://github.com/denni95112/drohnen-flug-und-dienstbuch.git
   cd drohnen-flug-und-dienstbuch
   ```

2. Webserver auf das Projektverzeichnis zeigen; PHP mit SQLite3 aktivieren.

3. Berechtigungen setzen (Linux/Unix):
   ```bash
   chmod -R 755 .
   chmod -R 777 uploads/ logs/ config/
   ```

4. Im Browser `http://ihre-domain/setup.php` aufrufen und die [Einrichtung](https://github.com/denni95112/drohnen-flug-und-dienstbuch/wiki/Einrichtung) durchführen.

---

## 📖 Verwendung & Dokumentation

Die ausführliche Bedienungsanleitung mit allen Funktionen und Screenshots findet sich im **[Wiki](https://github.com/denni95112/drohnen-flug-und-dienstbuch/wiki)**:

| Thema | Wiki-Seite |
|-------|------------|
| Anmeldung | [Anmeldung (Login)](https://github.com/denni95112/drohnen-flug-und-dienstbuch/wiki/Anmeldung-Login) |
| Dashboard & Flüge | [Dashboard](https://github.com/denni95112/drohnen-flug-und-dienstbuch/wiki/Dashboard), [Alle Flüge](https://github.com/denni95112/drohnen-flug-und-dienstbuch/wiki/Alle-Flüge-anzeigen), [Manueller Eintrag](https://github.com/denni95112/drohnen-flug-und-dienstbuch/wiki/Manueller-Flugeintrag) |
| Flugstandorte | [Flugstandorte](https://github.com/denni95112/drohnen-flug-und-dienstbuch/wiki/Flugstandorte) |
| Piloten & Drohnen | [Piloten verwalten](https://github.com/denni95112/drohnen-flug-und-dienstbuch/wiki/Piloten-verwalten), [Drohnen verwalten](https://github.com/denni95112/drohnen-flug-und-dienstbuch/wiki/Drohnen-verwalten) |
| Dienste | [Dienst hinzufügen](https://github.com/denni95112/drohnen-flug-und-dienstbuch/wiki/Dienst-hinzufügen), [Dienstbuch](https://github.com/denni95112/drohnen-flug-und-dienstbuch/wiki/Dienstbuch) |
| Dokumente | [Dokumente](https://github.com/denni95112/drohnen-flug-und-dienstbuch/wiki/Dokumente) |
| Admin & Benachrichtigungen | [Admin Login](https://github.com/denni95112/drohnen-flug-und-dienstbuch/wiki/Admin-Login), [Kopfzeilen-Benachrichtigungen](https://github.com/denni95112/drohnen-flug-und-dienstbuch/wiki/Kopfzeilen-Benachrichtigungen) |
| PWA installieren | [PWA – Als App installieren](https://github.com/denni95112/drohnen-flug-und-dienstbuch/wiki/PWA-installieren) |
| Datenbank-Update | [Datenbank Update](https://github.com/denni95112/drohnen-flug-und-dienstbuch/wiki/Datenbank-Update) |

---

## 🔒 Sicherheit

- SQL-Injection-Schutz (Prepared Statements)
- CSRF-Schutz für alle Formulare
- Sichere Passwort-Hashierung (bcrypt/argon2)
- Rate Limiting bei Anmeldung
- Verschlüsselung von Datei-Uploads
- HTTP-Sicherheitsheader

---

## 👨‍💻 Für Entwickler

### API-Endpunkte

| Endpunkt | Funktion |
|----------|----------|
| `/api/flights.php` | Flugoperationen (start, end, create, list) |
| `/api/pilots.php` | Pilot-Verwaltung |
| `/api/drones.php` | Drohnen-Verwaltung |
| `/api/locations.php` | Standort-Verwaltung |
| `/api/events.php` | Ereignis/Dienst-Verwaltung |
| `/api/documents.php` | Dokumenten-Verwaltung |
| `/api/migrations.php` | Datenbank-Migrationen |

Alle API-Requests erfordern Authentifizierung und CSRF-Token.

### Datenbank-Migrationen

Migrationen liegen in `migrations/` (Format: `001_beschreibung.php`). Ausführen über die [Datenbank-Update](https://github.com/denni95112/drohnen-flug-und-dienstbuch/wiki/Datenbank-Update)-Seite oder `pages/migrations.php`.

### Projektstruktur

```
├── api/          # REST-API-Endpunkte
├── config/       # Konfiguration
├── includes/     # Auth, CSRF, Utils, etc.
├── migrations/   # DB-Migrationen
├── pages/        # UI-Seiten
├── setup/        # Einrichtungs-Assistent
├── updater/      # Update-System
├── index.php     # Login
├── setup.php     # Ersteinrichtung
├── manifest.json # PWA
└── service-worker.js
```

---

## ℹ️ Weitere Informationen

- **Verwandtes Projekt**: [Drohnen-Einsatztagebuch](https://github.com/denni95112/drohnen-einsatztagebuch)
- **Lizenz**: MIT – siehe [LICENSE](LICENSE)
- **Autor**: [Dennis Bögner](https://github.com/denni95112) (@denni95112)
