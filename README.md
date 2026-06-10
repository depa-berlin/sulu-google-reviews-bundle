# SuluGoogleReviewsBundle

![php workflow](https://github.com/depa-berlin/sulu-google-reviews-bundle/actions/workflows/php.yml/badge.svg)
![symfony workflow](https://github.com/depa-berlin/sulu-google-reviews-bundle/actions/workflows/symfony.yml/badge.svg)
[![License: MIT](https://img.shields.io/badge/License-MIT-yellow.svg)](https://github.com/depa-berlin/sulu-google-reviews-bundle/blob/main/LICENSE)
![GitHub Tag](https://img.shields.io/github/v/tag/depa-berlin/sulu-google-reviews-bundle)
![Supports Sulu 3.0 or later](https://img.shields.io/badge/Sulu->=3.0-0088cc?color=00b2df)

Symfony-Bundle für Sulu CMS 3, das Google-Bewertungen über die Places API abruft, gefiltert (≥ 4 Sterne) in der eigenen Datenbank speichert und eine native Sulu-Admin-Oberfläche zur Moderation bereitstellt. Die Ausgabe im Frontend erfolgt über einen flexiblen Sulu-Block.

---

## Voraussetzungen

- PHP 8.2+
- Sulu CMS 3.0+
- Symfony 7.0+
- MySQL 8.0+

---

## Installation

### 1. Path-Repository in `composer.json` eintragen

Das Bundle liegt lokal unter `packages/sulu-google-reviews-bundle/` im Hauptprojekt:

```json
"repositories": [
    {
        "type": "path",
        "url": "./packages/sulu-google-reviews-bundle",
        "options": {
            "symlink": true
        }
    }
]
```

### 2. Bundle per Composer installieren

```bash
composer require depa/sulu-google-reviews-bundle:@dev
```

### 3. Bundle registrieren

In `config/bundles.php` hinzufügen:

```php
Depa\SuluGoogleReviewsBundle\DepaGoogleReviewsBundle::class => ['all' => true],
```

### 4. Admin-Routen importieren

In `config/routes/routes_admin.yaml` eintragen:

```yaml
DepaGoogleReviewsBundle:
    resource: "@DepaGoogleReviewsBundle/Resources/config/routes_admin.yaml"
    prefix: /admin/api
```

### 5. Umgebungsvariablen setzen

In `.env.local` (niemals committen):

```dotenv
GOOGLE_PLACES_API_KEY=AIzaSy...
GOOGLE_PLACE_ID=ChIJ...
```

Die `Place ID` einer Location findet man in der [Google Maps Platform](https://developers.google.com/maps/documentation/places/web-service/place-id).

### 6. Datenbank-Migration ausführen

```bash
bin/adminconsole doctrine:migrations:diff
bin/adminconsole doctrine:migrations:migrate
```

Dies legt die Tabelle `sulu_google_review` an.

### 7. Cache leeren

```bash
bin/adminconsole cache:clear
```

---

## Sulu-Block einbinden

### Block-Typ in einer Section-Vorlage registrieren

Den Block in `config/templates/blocks/block--section.xml` unter `<types>` eintragen:

```xml
<type ref="block--google-reviews"/>
```

Der Block „Google Bewertungen" steht danach im Sulu-Admin-Editor beim Hinzufügen von Sub-Blöcken innerhalb einer Section zur Verfügung.

### Block-Felder im Editor

| Feld | Beschreibung |
|---|---|
| **Titel** | Optionale Überschrift über den Bewertungen |
| **Anzahl** | Wie viele Bewertungen angezeigt werden (Standard: 3) |
| **Sortierung** | Sortierungsmodus (siehe unten) |

#### Sortierungsmodi

| Wert | Verhalten |
|---|---|
| `Nach Datum` | Neueste Bewertungen zuerst |
| `Nach Bewertung` | Höchste Sternebewertung zuerst |
| `Eigene Reihenfolge` | Sortiert nach dem „Reihenfolge"-Feld der einzelnen Bewertungen |

---

## Bewertungen importieren

### Manueller Import

```bash
bin/adminconsole sulu:google-reviews:fetch
```

### Automatisierter Import per Cronjob

```cron
0 3 * * * /path/to/project/bin/adminconsole sulu:google-reviews:fetch
```

Der Command importiert nur neue Bewertungen (≥ 4 Sterne). Bereits vorhandene Einträge werden übersprungen.

---

## Moderation im Sulu-Admin

Im Sulu-Backend erscheint nach der Installation der Menüpunkt **„Google Bewertungen"** (Icon: Kommentar).

### Listenansicht

Zeigt alle importierten Bewertungen mit Autor, Sternebewertung, Datum, Sperrstatus und Reihenfolge.

### Detailansicht

| Bereich | Felder | Bearbeitbar |
|---|---|---|
| **Bewertung** (von Google importiert) | Autor, Sterne, Datum, Zeitangabe, Text, Profilbild-URL | Nein |
| **Moderation & Darstellung** | Bewertung sperren, Reihenfolge | Ja |

#### Bewertung sperren

Gesperrte Bewertungen werden im Frontend nicht angezeigt und tauchen in `get_stored_google_reviews()` nicht auf.

#### Eigene Reihenfolge

Das Feld **Reihenfolge** wird verwendet, wenn im Block die Sortierung „Eigene Reihenfolge" gewählt ist.

- `0` = keine Priorität (wird bei Gleichstand nach Datum sortiert)
- `1`, `2`, `3`, … = aufsteigende Anzeigereihenfolge

Wird eine bereits vergebene Positions-Nummer eingetragen, rücken alle anderen Einträge an dieser Stelle automatisch um eine Position nach hinten.

---

## Twig-Funktion (direkte Nutzung)

Das Bundle stellt die Twig-Funktion `get_stored_google_reviews()` bereit, die unabhängig vom Sulu-Block überall in Templates genutzt werden kann:

```twig
{% set reviews = get_stored_google_reviews(limit, sort) %}
```

| Parameter | Typ | Standard | Mögliche Werte |
|---|---|---|---|
| `limit` | `int` | `5` | Beliebige positive Ganzzahl |
| `sort` | `string` | `'date'` | `'date'`, `'rating'`, `'custom'` |

**Beispiel:**

```twig
{% for review in get_stored_google_reviews(3, 'rating') %}
    <p>{{ review.authorName }}: {{ review.rating }} Sterne</p>
    <p>{{ review.text }}</p>
{% endfor %}
```

### Verfügbare Review-Eigenschaften

| Eigenschaft | Typ | Beschreibung |
|---|---|---|
| `authorName` | `string` | Name des Rezensenten |
| `profilePhotoUrl` | `string\|null` | URL des Google-Profilbilds |
| `rating` | `int` | Sternebewertung (4 oder 5) |
| `text` | `string` | Bewertungstext |
| `createdAtTimestamp` | `int` | Erstellungsdatum als Unix-Timestamp |
| `relativeTimeDescription` | `string` | Zeitangabe von Google (z. B. „vor 3 Monaten") |
| `blocked` | `bool` | Sperrstatus (bei direktem Repository-Zugriff) |
| `sortOrder` | `int` | Eigene Sortierungsposition |

---

## Frontend-Styling

Das Template verwendet **Bootstrap 5** für das Grid-Layout und folgt der **BEM-Nomenklatur**:

| BEM-Klasse | Element |
|---|---|
| `.google-reviews` | Wrapper `<section>` |
| `.google-reviews__title` | Optionale Überschrift |
| `.google-reviews__card` | Einzelne Bewertungskarte |
| `.google-reviews__author` | Autor-Bereich |
| `.google-reviews__avatar` | Profilbild |
| `.google-reviews__avatar--fallback` | Initial-Fallback ohne Bild |
| `.google-reviews__author-name` | Autorenname |
| `.google-reviews__rating` | Sterne-Container |
| `.google-reviews__star` | Einzelner Stern |
| `.google-reviews__star--filled` | Gefüllter Stern |
| `.google-reviews__text` | Bewertungstext |
| `.google-reviews__time` | Zeitangabe |
| `.google-reviews__empty` | Fallback-Nachricht ohne Bewertungen |

Das Template befindet sich unter:
`Resources/views/includes/blocks/block--google-reviews.html.twig`

### Template anpassen

Wenn das Bundle per Composer installiert ist, kannst du das Template im Hauptprojekt überschreiben:

1. **Erstelle die Datei:**
   ```
   templates/includes/blocks/block--google-reviews.html.twig
   ```

2. **Kopiere den Inhalt vom Bundle** und passe ihn an (CSS-Klassen, HTML-Struktur, etc.)

Twig sucht Templates zuerst im Hauptprojekt, dann in den Bundles — deine Version wird automatisch verwendet.

---

## Architektur-Übersicht

```
packages/sulu-google-reviews-bundle/
├── src/
│   ├── Admin/GoogleReviewsAdmin.php          # Sulu-Navigation & Views
│   ├── Command/FetchGoogleReviewsCommand.php  # Import-Command
│   ├── Controller/Admin/GoogleReviewController.php
│   ├── DependencyInjection/
│   │   ├── DepaGoogleReviewsExtension.php     # Auto-Konfiguration per prepend
│   │   └── Configuration.php
│   ├── Entity/GoogleReview.php
│   ├── Repository/GoogleReviewRepository.php
│   └── Twig/GoogleReviewsTwigExtension.php
└── Resources/
    ├── config/
    │   ├── forms/google_review_details.xml
    │   ├── lists/google_reviews.xml
    │   ├── routes_admin.yaml
    │   └── services.yaml
    └── views/
        └── includes/blocks/
            └── block--google-reviews.html.twig
```

Die Bundle-Extension registriert automatisch per `PrependExtensionInterface`:
- Sulu Admin: Listen- und Formular-Verzeichnisse, Resource-Routen
- Doctrine ORM: Entity-Mapping
- Twig: Views-Verzeichnis

Dadurch sind **keine manuellen Einträge** in `sulu_admin.yaml` oder `twig.yaml` erforderlich.
