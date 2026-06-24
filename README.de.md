# SuluGoogleReviewsBundle

![php workflow](https://github.com/depa-berlin/sulu-google-reviews-bundle/actions/workflows/php.yml/badge.svg)
![symfony workflow](https://github.com/depa-berlin/sulu-google-reviews-bundle/actions/workflows/symfony.yml/badge.svg)
[![License: MIT](https://img.shields.io/badge/License-MIT-yellow.svg)](https://github.com/depa-berlin/sulu-google-reviews-bundle/blob/main/LICENSE)
![GitHub Tag](https://img.shields.io/github/v/tag/depa-berlin/sulu-google-reviews-bundle)
![Supports Sulu 3.0 or later](https://img.shields.io/badge/Sulu->=3.0-0088cc?color=00b2df)

Symfony-Bundle für Sulu CMS 3, das Google-Bewertungen über die Places API abruft, gefiltert (≥ 4 Sterne) in der eigenen Datenbank speichert und eine native Sulu-Admin-Oberfläche zur Moderation bereitstellt. Die Ausgabe im Frontend erfolgt über einen flexiblen Sulu-Block.

**Verfügbare Sprachen:** [🇬🇧 English](README.md)

---

## Voraussetzungen

- PHP 8.2+
- Sulu CMS 3.0+
- Symfony 7.0+
- MySQL 8.0+

---

## Installation

### 1. Bundle per Composer installieren

```bash
composer require depa/sulu-google-reviews-bundle
```

### 2. Bundle registrieren

Mit Symfony Flex wird das Bundle bei `composer require` **automatisch** in `config/bundles.php` eingetragen. Falls Flex nicht verwendet wird, manuell ergänzen:

```php
Depa\SuluGoogleReviewsBundle\DepaSuluGoogleReviewsBundle::class => ['all' => true],
```

### 3. Admin-Routen importieren

In `config/routes/routes_admin.yaml` eintragen:

```yaml
DepaSuluGoogleReviewsBundle:
    resource: "@DepaSuluGoogleReviewsBundle/Resources/config/routes_admin.yaml"
    prefix: /admin/api
```

### 4. Umgebungsvariablen setzen

In `.env.local` (niemals committen):

```dotenv
GOOGLE_PLACES_API_KEY=AIzaSy...
GOOGLE_PLACE_ID=ChIJ...
```

Die `Place ID` einer Location findet man in der [Google Maps Platform](https://developers.google.com/maps/documentation/places/web-service/place-id).

### 5. Datenbank-Migration ausführen

```bash
bin/adminconsole doctrine:migrations:migrate
```

Dies legt die Tabelle `depa_googlereviews_reviews` an.

### 6. Cache leeren

```bash
bin/adminconsole cache:clear
```

### 7. Admin-Feldtyp einbinden (Frontend-Build)

Die Detailansicht im Admin nutzt einen read-only React-Feldtyp `google_review_display`, dessen Quelle im Bundle unter `Resources/js/` liegt. Da Sulu Admin-JS über den Webpack-Build des Projekts gebaut wird, muss das konsumierende Projekt diese Quelle einbinden:

1. Import in der Admin-Entry-Datei (`assets/admin/app.js`):

   ```js
   import '../../vendor/depa/sulu-google-reviews-bundle/Resources/js';
   ```

2. Da die Datei außerhalb von `assets/admin/` liegt, in `assets/admin/webpack.config.js` sicherstellen, dass Babel sie mit der Projekt-Config transpiliert und Bare-Imports aufgelöst werden:

   ```js
   const babelRule = config.module.rules.find(
       (rule) => rule.test && rule.test.toString() === /\.js$/.toString()
   );
   if (babelRule && babelRule.use) {
       babelRule.use.options = babelRule.use.options || {};
       babelRule.use.options.configFile = path.resolve(__dirname, 'babel.config.json');
   }
   config.resolve = config.resolve || {};
   config.resolve.modules = [path.resolve(__dirname, 'node_modules'), 'node_modules'];
   ```

3. Admin-Build ausführen:

   ```bash
   cd assets/admin && npm run build
   ```

> Hinweis: Sulu kann Admin-JS aus einem Bundle nicht selbst kompilieren — Registrierung und Build laufen immer über das Projekt. Dieser Schritt ist daher projektseitig und einmalig nötig.

> ⚠️ Achtung bei `bin/adminconsole sulu:admin:update-build`: Dieser Befehl gleicht die Dateien in `assets/admin/` mit dem `sulu/skeleton` ab und überschreibt sie auf Nachfrage. Für `webpack.config.js` (und `package.json`, `babel.config.json`) ist die Vorgabe **„überschreiben = ja"** — dabei gingen die oben gezeigten Anpassungen (`configFile`, `resolve.modules`) verloren und der Feldtyp ließe sich nicht mehr bauen. Beim Ausführen des Befehls die angepasste `webpack.config.js` daher **nicht** überschreiben und auch den Import in `app.js` behalten (dort ist die Vorgabe ohnehin „nein"). Der Befehl bindet den Feldtyp nicht selbst ein — die Einbindung erfolgt ausschließlich über die Schritte 1–3 oben.

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

Der Command importiert neue Bewertungen (≥ 4 Sterne) und aktualisiert bei bereits vorhandenen Einträgen Text, Rating, Profilbild und Zeitangabe. Manuelle Moderationsfelder (Gesperrt, Eigene Reihenfolge) bleiben dabei erhalten.

### Mehrsprachigkeit

Der Command ruft die Bewertungen **je Webspace-Locale** ab (ermittelt automatisch über den Sulu `WebspaceManager`). Für jede konfigurierte Sprache wird Googles übersetzte Fassung des Bewertungstextes sowie die lokalisierte Zeitangabe gespeichert — alles in **derselben** Datenbankzeile. Eine Bewertung bleibt damit unabhängig von der Sprachenanzahl **ein einziger Eintrag im Admin**.

- Zusätzlich werden der **Originaltext** und dessen Sprache gespeichert; das Frontend nutzt sie als Fallback, wenn für die aktuelle Locale keine Übersetzung vorliegt.
- Kommt später eine Sprache im Webspace hinzu, wird sie beim nächsten Import-Lauf automatisch ergänzt — **ohne** Datenbank-Migration (die Übersetzungen liegen in einer JSON-Spalte). Das gilt jedoch nur für Bewertungen, die Google aktuell zurückliefert (max. 5). Ältere Bewertungen außerhalb dieses Fensters werden über den Nachübersetzungs-Command abgedeckt (siehe unten).

### Fehlende Übersetzungen nachfüllen (optional)

Da Google nur die neuesten ~5 Bewertungen liefert, erhalten ältere Bewertungen bei einer **neu hinzugefügten** Sprache über den Import allein keine Übersetzung. Dafür gibt es einen separaten Command, der den gespeicherten Originaltext über einen Übersetzungsdienst in alle fehlenden Webspace-Sprachen übersetzt:

```bash
bin/adminconsole sulu:google-reviews:translate-missing
```

- Es werden **nur fehlende** Sprachfassungen ergänzt; vorhandene (z. B. von Google importierte) bleiben unangetastet.
- Der Übersetzungsdienst ist eine **optionale** Abhängigkeit. Ist [`robole/sulu-ai-translator-bundle`](https://github.com/robole-dev/sulu-ai-translator-bundle) (DeepL) installiert, verdrahtet das Bundle den passenden Adapter **automatisch** (per Compiler-Pass) — **keine** Konfiguration im Projekt nötig. Fehlt das Bundle, bricht der Command mit einem Hinweis ab.
- Ein eigener Übersetzungsdienst lässt sich einbinden, indem im Projekt `Depa\SuluGoogleReviewsBundle\Translation\ReviewTranslatorInterface` an eine eigene Implementierung gebunden wird; diese hat dann Vorrang vor der DeepL-Automatik.

### Hinweise zur Google-API

- **Places API (New):** Das Bundle nutzt die aktuelle Places API (`places.googleapis.com/v1/places/{placeId}`) mit API-Key im Header (`X-Goog-Api-Key`) und FieldMask (`X-Goog-FieldMask: reviews`). Die alte „Places API (Legacy)" wird **nicht** verwendet, da sie für neue Google-Cloud-Projekte nicht mehr aktivierbar ist. In der Google Cloud Console muss die **„Places API (New)"** aktiviert sein.
- **Maximal 5 Bewertungen pro Abruf:** Die Places API liefert höchstens 5 Bewertungen ohne Pagination und ohne Sortieroption. Erscheinen zwischen zwei Cron-Läufen mehr als 5 neue Bewertungen, kann es zu Lücken kommen.
- **API-Kosten:** Pro Webspace-Locale erfolgt **ein** API-Call (Place Details mit `reviews` ist der kostenpflichtige Enterprise-SKU). Bei z. B. drei Sprachen verdreifacht sich die Anzahl der Abrufe pro Import-Lauf.

---

## Moderation im Sulu-Admin

Im Sulu-Backend erscheint nach der Installation der Menüpunkt **„Google Bewertungen"** (Icon: Kommentar).

### Listenansicht

Zeigt alle importierten Bewertungen mit Autor, Sternebewertung, Datum, Sperrstatus und Reihenfolge.

### Detailansicht

| Bereich | Felder | Bearbeitbar |
|---|---|---|
| **Bewertung** (von Google importiert) | read-only Anzeige: Autor, Sterne, Datum, Originalsprache und der Bewertungstext je Webspace-Sprache | Nein |
| **Moderation & Darstellung** | Bewertung sperren, Reihenfolge | Ja |

Die Bewertung selbst wird über den read-only Admin-Feldtyp `google_review_display` dargestellt (siehe Installation, Schritt 7).

#### Bewertung sperren

Gesperrte Bewertungen werden im Frontend nicht angezeigt und tauchen in `get_stored_google_reviews()` nicht auf.

#### Eigene Reihenfolge

Das Feld **Reihenfolge** wird verwendet, wenn im Block die Sortierung „Eigene Reihenfolge" gewählt ist.

- `0` = keine Priorität (erscheint hinter den priorisierten Einträgen, untereinander nach Datum)
- `1`, `2`, `3`, … = aufsteigende Anzeigereihenfolge

Wird eine bereits vergebene Positions-Nummer eingetragen, rücken alle anderen Einträge an dieser Stelle automatisch um eine Position nach hinten.

---

## Twig-Funktionen (direkte Nutzung)

Das Bundle stellt zwei Twig-Funktionen bereit, die unabhängig vom Sulu-Block überall in Templates genutzt werden können.

### `get_stored_google_reviews(limit, sort)`

```twig
{% set reviews = get_stored_google_reviews(limit, sort) %}
```

| Parameter | Typ | Standard | Mögliche Werte |
|---|---|---|---|
| `limit` | `int` | `5` | Beliebige positive Ganzzahl |
| `sort` | `string` | `'date'` | `'date'`, `'rating'`, `'custom'` |

### `google_review_relative_time(timestamp, locale)`

Liefert eine **berechnete, immer aktuelle** relative Zeitangabe (z. B. „vor 3 Monaten") aus dem Timestamp — nicht Googles gespeicherte, veraltende Zeichenkette. Locale-korrekt für ~280 Sprachen über Carbon (`diffForHumans`).

| Parameter | Typ | Beschreibung |
|---|---|---|
| `timestamp` | `int` | `review.createdAtTimestamp` |
| `locale` | `string` | Ziel-Locale, z. B. `app.request.locale` |

**Beispiel:**

```twig
{% for review in get_stored_google_reviews(3, 'rating') %}
    <p>{{ review.authorName }}: {{ review.rating }} Sterne</p>
    <p>{{ review.getText(app.request.locale) }}</p>
    <p>{{ google_review_relative_time(review.createdAtTimestamp, app.request.locale) }}</p>
{% endfor %}
```

### Verfügbare Review-Eigenschaften

| Eigenschaft | Typ | Beschreibung |
|---|---|---|
| `authorName` | `string` | Name des Rezensenten |
| `profilePhotoUrl` | `string\|null` | URL des Google-Profilbilds |
| `rating` | `int` | Sternebewertung (4 oder 5) |
| `getText(locale)` | `string` | Bewertungstext für die Locale, Fallback auf den Originaltext |
| `originalText` | `string\|null` | Originaltext in der Originalsprache (Fallback) |
| `originalLanguage` | `string\|null` | Sprachcode des Originaltexts |
| `createdAtTimestamp` | `int` | Erstellungsdatum als Unix-Timestamp |
| `blocked` | `bool` | Sperrstatus (bei direktem Repository-Zugriff) |
| `sortOrder` | `int` | Eigene Sortierungsposition |

> Hinweis: Der Bewertungstext ist sprachabhängig — `review.getText(app.request.locale)` verwenden (`review.text` ohne Argument liefert den Originaltext als Fallback). Die relative Zeitangabe wird über `google_review_relative_time(...)` berechnet, statt aus einem gespeicherten Wert gelesen.

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
vendor/depa/sulu-google-reviews-bundle/
├── src/
│   ├── Admin/GoogleReviewsAdmin.php                   # Sulu-Navigation & Views
│   ├── Command/
│   │   ├── FetchGoogleReviewsCommand.php               # Import je Webspace-Locale
│   │   └── TranslateMissingReviewsCommand.php          # Nachübersetzen fehlender Sprachen
│   ├── Controller/Admin/GoogleReviewController.php
│   ├── DependencyInjection/
│   │   ├── Compiler/TranslatorIntegrationPass.php      # DeepL-Adapter optional verdrahten
│   │   ├── DepaGoogleReviewsExtension.php              # Auto-Konfiguration per prepend
│   │   └── Configuration.php
│   ├── Entity/GoogleReview.php
│   ├── Repository/GoogleReviewRepository.php
│   ├── Translation/
│   │   ├── ReviewTranslatorInterface.php               # optionaler Übersetzer-Contract
│   │   ├── DeeplReviewTranslator.php                   # DeepL-Adapter (duck-typed)
│   │   └── DeeplTranslatorClientInterface.php
│   ├── Twig/GoogleReviewsTwigExtension.php
│   └── DepaSuluGoogleReviewsBundle.php                 # registriert den Compiler-Pass
└── Resources/
    ├── config/
    │   ├── forms/google_review_details.xml
    │   ├── lists/google_reviews.xml
    │   ├── routes_admin.yaml
    │   └── services.yaml
    ├── js/
    │   ├── index.js                                    # Feldtyp-Registrierung
    │   └── GoogleReviewDisplay.js                      # read-only Admin-Feldtyp
    └── views/
        └── includes/blocks/
            └── block--google-reviews.html.twig
```

Die Bundle-Extension registriert automatisch per `PrependExtensionInterface`:
- Sulu Admin: Listen- und Formular-Verzeichnisse, Resource-Routen
- Doctrine ORM: Entity-Mapping
- Twig: Views-Verzeichnis

Dadurch sind **keine manuellen Einträge** in `sulu_admin.yaml` oder `twig.yaml` erforderlich. Lediglich der Admin-Feldtyp muss projektseitig in den Webpack-Build eingebunden werden (siehe Installation, Schritt 7).
