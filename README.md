# CF7 Abholtermin-Picker

Schlankes WordPress-Plugin für ein Bestellformular mit Contact Form 7.  
Berechnet automatisch den frühestmöglichen Abholtermin auf Basis von Bearbeitungszeiten und einer 24-Stunden-Vorlaufzeit.

---

## Voraussetzungen

| Komponente | Version |
|---|---|
| WordPress | ≥ 6.0 |
| Contact Form 7 | ≥ 5.8 |
| PHP | ≥ 8.0 |

Flatpickr (Datepicker-Library) wird automatisch aus einem CDN geladen — kein separates installieren nötig.

---

## Installation

1. Plugin-Ordner `cf7-abholtermin-picker` in `/wp-content/plugins/` kopieren (per FTP/SSH oder ZIP-Upload im WP-Backend).
2. Im WordPress-Backend unter **Plugins → Installierte Plugins** das Plugin aktivieren.
3. Sicherstellen, dass Contact Form 7 aktiv ist.

---

## Bestelllogik

Kunden können rund um die Uhr Bestellungen absenden. Der frühestmögliche Abholtermin wird automatisch berechnet:

### Bearbeitungszeiten (intern)

| Tag | Zeitfenster |
|---|---|
| Montag – Donnerstag | 08:00 – 16:00 Uhr |
| Freitag | 08:00 – 12:00 Uhr |
| Samstag / Sonntag | keine Bearbeitung |

Geht eine Bestellung außerhalb dieser Zeiten ein, gilt als Bearbeitungsbeginn der nächste verfügbare Öffnungszeitpunkt.

### Vorlaufzeit

Zwischen Bearbeitungsbeginn und Abholung müssen mindestens **24 Stunden** liegen.

### Abholzeiten

Abholungen sind möglich: **Montag – Samstag, 09:30 – 12:30 Uhr** (alle 30 Minuten).

### Beispiel

> Bestellung eingehend: **Montag, 18:00 Uhr**  
> → Bearbeitungsbeginn: Dienstag, 08:00 Uhr  
> → Ende Vorlaufzeit: Mittwoch, 08:00 Uhr  
> → Früheste Abholung: **Mittwoch, 09:30 Uhr**

---

## Integration in Contact Form 7

Im CF7-Formular den bisherigen Datumsbereich so anlegen:

```html
<div class="col-lg-6 col-md-6 col-sm-12">
    Datum: [text* abholdatum class:cf7-abholpicker]
</div>

<div class="col-lg-6 col-md-6 col-sm-12">
    Uhrzeit:
    <select name="abholzeit" class="cf7-abholzeit" required>
        <option value="">Uhrzeit wählen …</option>
    </select>
</div>
```

Im E-Mail-Template von CF7:

```
Abholdatum: [abholdatum]
Abholzeit:  [abholzeit]
```

Das Datum wird im E-Mail-Versand automatisch in deutsches Format (`dd.mm.yyyy`) umgewandelt.

---

## Admin-Bereich

Unter **Einstellungen → CF7 Abholtermin** können gepflegt werden:

- **Bundesweite Feiertage** — werden automatisch berechnet (read-only Anzeige für aktuelles und nächstes Jahr). Enthält: Neujahr, Karfreitag, Ostermontag, Tag der Arbeit, Christi Himmelfahrt, Pfingstmontag, Tag der Deutschen Einheit, 1. und 2. Weihnachtstag.
- **Zusätzliche Sperrtage** — regionale Feiertage, Brückentage o.ä. (manuell pflegbar).
- **Betriebsferien** — Zeiträume (von–bis), in denen keine Abholung möglich ist (manuell pflegbar).
- **Live-Vorschau** — zeigt den aktuell berechneten frühesten Abholtermin.

---

## Dateistruktur

```
cf7-abholtermin-picker/
├── cf7-abholtermin-picker.php   Haupt-Plugin-Datei (Bootstrap, Asset-Loading, E-Mail-Filter)
├── includes/
│   ├── pickup-logic.php         Abhollogik, Ostersonntag-Algorithmus, Feiertagsberechnung
│   └── admin.php                Admin-UI (Einstellungsseite, Sanitizer, Speichern/Laden)
└── assets/
    ├── datepicker.js            Flatpickr-Initialisierung, dynamische Zeitauswahl
    ├── datepicker.css           Frontend-Styling
    ├── admin.js                 Add/Remove-Logik für Sperrtage und Betriebsferien
    └── admin.css                Admin-UI-Styling
```

---

## Konfiguration anpassen

Öffnungszeiten, Vorlaufzeit und Buchungshorizont sind als Konstanten in `includes/pickup-logic.php` hinterlegt:

```php
// Vorlaufzeit in Stunden
const CF7AP_LEAD_HOURS = 24;

// Maximaler Vorausbuchungszeitraum
const CF7AP_MAX_DAYS_AHEAD = 30;

// Abholfenster
const CF7AP_PICKUP_START_H = 9;
const CF7AP_PICKUP_START_M = 30;
const CF7AP_PICKUP_END_H   = 12;
const CF7AP_PICKUP_END_M   = 30;
```

Bearbeitungszeiten werden in `cf7ap_processing_windows()` definiert.  
Zeitslots im Dropdown in `cf7ap_time_slots()`.

---

## Git-Workflow

```bash
# Änderungen committen
git add .
git commit -m "config: Betriebsferien Sommer 2026 eingetragen"
git push origin main

# Server aktualisieren (SSH)
cd /var/www/html/wp-content/plugins/cf7-abholtermin-picker/
git pull origin main
```

Empfohlene Commit-Prefixes: `feat:` (neue Funktion), `fix:` (Bugfix), `config:` (Konfigurationsänderung).