# SuluGoogleReviewsBundle

![php workflow](https://github.com/depa-berlin/sulu-google-reviews-bundle/actions/workflows/php.yml/badge.svg)
![symfony workflow](https://github.com/depa-berlin/sulu-google-reviews-bundle/actions/workflows/symfony.yml/badge.svg)
[![License: MIT](https://img.shields.io/badge/License-MIT-yellow.svg)](https://github.com/depa-berlin/sulu-google-reviews-bundle/blob/main/LICENSE)
![GitHub Tag](https://img.shields.io/github/v/tag/depa-berlin/sulu-google-reviews-bundle)
![Supports Sulu 3.0 or later](https://img.shields.io/badge/Sulu->=3.0-0088cc?color=00b2df)

Symfony bundle for Sulu CMS 3 that fetches Google Places reviews via the Places API, stores them filtered (≥ 4 stars) in a dedicated database table, and provides a native Sulu admin interface for moderation. Frontend output is rendered through a flexible Sulu block.

**Available Languages:** [🇩🇪 Deutsch](README.de.md)

---

## Requirements

- PHP 8.2+
- Sulu CMS 3.0+
- Symfony 7.0+
- MySQL 8.0+

---

## Installation

### 1. Install via Composer

```bash
composer require depa/sulu-google-reviews-bundle
```

### 2. Register bundle

Add to `config/bundles.php`:

```php
Depa\SuluGoogleReviewsBundle\DepaGoogleReviewsBundle::class => ['all' => true],
```

### 3. Import admin routes

Add to `config/routes/routes_admin.yaml`:

```yaml
DepaGoogleReviewsBundle:
    resource: "@DepaGoogleReviewsBundle/Resources/config/routes_admin.yaml"
    prefix: /admin/api
```

### 4. Set environment variables

In `.env.local` (never commit):

```dotenv
GOOGLE_PLACES_API_KEY=AIzaSy...
GOOGLE_PLACE_ID=ChIJ...
```

Find your `Place ID` on [Google Maps Platform](https://developers.google.com/maps/documentation/places/web-service/place-id).

### 5. Run database migrations

```bash
bin/adminconsole doctrine:migrations:migrate
```

Creates the `sulu_google_review` table.

### 6. Clear cache

```bash
bin/adminconsole cache:clear
```

### 7. Wire the admin field type (frontend build)

The admin detail view uses a read-only React field type `google_review_display` whose source lives in the bundle under `Resources/js/`. Because Sulu admin JS is built through the project's webpack build, the consuming project must include this source:

1. Import it in the admin entry file (`assets/admin/app.js`):

   ```js
   import '../../vendor/depa/sulu-google-reviews-bundle/Resources/js';
   ```

2. Since the file lives outside `assets/admin/`, make sure in `assets/admin/webpack.config.js` that Babel transpiles it with the project config and that bare imports are resolved:

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

3. Build the admin assets:

   ```bash
   cd assets/admin && npm run build
   ```

> Note: Sulu cannot compile a bundle's admin JS on its own — registration and build always run through the project. This step is therefore required once on the project side.

> ⚠️ Caution with `bin/adminconsole sulu:admin:update-build`: this command syncs the files in `assets/admin/` against `sulu/skeleton` and overwrites them on confirmation. For `webpack.config.js` (and `package.json`, `babel.config.json`) the default is **"overwrite = yes"** — which would discard the customizations shown above (`configFile`, `resolve.modules`) and break the field type build. When running the command, do **not** overwrite the customized `webpack.config.js`, and keep the import in `app.js` (its default there is already "no"). The command does not wire the field type itself — wiring happens exclusively via steps 1–3 above.

---

## Integrating the Sulu Block

### Register block type in section template

Add to `config/templates/blocks/block--section.xml` under `<types>`:

```xml
<type ref="block--google-reviews"/>
```

The "Google Reviews" block will now appear in the Sulu admin editor when adding sub-blocks within a section.

### Block fields in editor

| Field | Description |
|---|---|
| **Title** | Optional heading above the reviews |
| **Limit** | Number of reviews to display (default: 3) |
| **Sort** | Sort mode (see below) |

#### Sort modes

| Value | Behavior |
|---|---|
| `By Date` | Newest reviews first |
| `By Rating` | Highest star rating first |
| `Custom Order` | Sorted by the "Sort Order" field of individual reviews |

---

## Fetching reviews

### Manual fetch

```bash
bin/adminconsole sulu:google-reviews:fetch
```

### Automated fetch via cronjob

```cron
0 3 * * * /path/to/project/bin/adminconsole sulu:google-reviews:fetch
```

The command imports new reviews (≥ 4 stars) and, for existing entries, updates text, rating, profile photo and relative time. Manual moderation fields (blocked, custom sort order) are preserved.

### Multilingual support

The command fetches reviews **per webspace locale** (resolved automatically via Sulu's `WebspaceManager`). For each configured language, Google's translated version of the review text and the localized relative time are stored — all in the **same** database row. A review therefore stays **a single entry in the admin** regardless of the number of languages.

- In addition, the **original text** and its language are stored; the frontend uses them as a fallback when no translation exists for the current locale.
- When a language is added to the webspace later, it is filled in automatically on the next fetch run — **without** a database migration (translations are stored in a JSON column). This only applies to reviews Google currently returns (max. 5); older reviews outside that window are covered by the backfill command (see below).

### Backfilling missing translations (optional)

Because Google only returns the latest ~5 reviews, older reviews do not get a translation from the fetch alone when a **newly added** language appears. A separate command translates the stored original text into all missing webspace languages via a translation service:

```bash
bin/adminconsole sulu:google-reviews:translate-missing
```

- Only **missing** language versions are added; existing ones (e.g. imported from Google) are left untouched.
- The translation service is an **optional** dependency. If [`robole/sulu-ai-translator-bundle`](https://github.com/robole-dev/sulu-ai-translator-bundle) (DeepL) is installed, the bundle wires the matching adapter **automatically** (via a compiler pass) — **no** project configuration required. If the bundle is missing, the command aborts with a hint.
- A custom translation service can be plugged in by binding `Depa\SuluGoogleReviewsBundle\Translation\ReviewTranslatorInterface` to your own implementation in the project; it then takes precedence over the DeepL auto-wiring.

### Google API notes

- **Places API (New):** the bundle uses the current Places API (`places.googleapis.com/v1/places/{placeId}`) with the API key in the header (`X-Goog-Api-Key`) and a FieldMask (`X-Goog-FieldMask: reviews`). The old "Places API (Legacy)" is **not** used, as it can no longer be enabled for new Google Cloud projects. The **"Places API (New)"** must be enabled in the Google Cloud Console.
- **Maximum 5 reviews per request:** the Places API returns at most 5 reviews without pagination and without a sort option. If more than 5 new reviews appear between two cron runs, gaps can occur.
- **API cost:** one API call is made **per webspace locale** (Place Details with `reviews` is the billable Enterprise SKU). With e.g. three languages the number of requests per fetch run triples.

---

## Moderation in Sulu Admin

After installation, the menu item **"Google Reviews"** appears in the Sulu backend (icon: comment).

### List view

Shows all imported reviews with author, star rating, date, block status, and sort order.

### Detail view

| Section | Fields | Editable |
|---|---|---|
| **Review** (imported from Google) | read-only display: author, stars, date, original language and the review text per webspace language | No |
| **Moderation & Display** | Block review, sort order | Yes |

The review itself is rendered through the read-only admin field type `google_review_display` (see Installation, step 7).

#### Block review

Blocked reviews won't appear on the frontend and are excluded from `get_stored_google_reviews()` results.

#### Custom sort order

The **Sort Order** field is used when the block's sort mode is set to "Custom Order".

- `0` = no priority (placed after the prioritized entries, sorted by date among themselves)
- `1`, `2`, `3`, … = ascending display order

When assigning an already-taken sort position, all other entries at that position and above are automatically shifted up by one.

---

## Twig functions (direct usage)

The bundle provides two Twig functions for use anywhere in templates, independent of the Sulu block.

### `get_stored_google_reviews(limit, sort)`

```twig
{% set reviews = get_stored_google_reviews(limit, sort) %}
```

| Parameter | Type | Default | Possible Values |
|---|---|---|---|
| `limit` | `int` | `5` | Any positive integer |
| `sort` | `string` | `'date'` | `'date'`, `'rating'`, `'custom'` |

### `google_review_relative_time(timestamp, locale)`

Returns a **computed, always-current** relative time (e.g. "3 months ago") from the timestamp — not Google's stored, ageing string. Localized for ~280 locales via Carbon (`diffForHumans`).

| Parameter | Type | Description |
|---|---|---|
| `timestamp` | `int` | `review.createdAtTimestamp` |
| `locale` | `string` | Target locale, e.g. `app.request.locale` |

**Example:**

```twig
{% for review in get_stored_google_reviews(3, 'rating') %}
    <p>{{ review.authorName }}: {{ review.rating }} stars</p>
    <p>{{ review.getText(app.request.locale) }}</p>
    <p>{{ google_review_relative_time(review.createdAtTimestamp, app.request.locale) }}</p>
{% endfor %}
```

### Available review properties

| Property | Type | Description |
|---|---|---|
| `authorName` | `string` | Reviewer name |
| `profilePhotoUrl` | `string\|null` | URL to Google profile photo |
| `rating` | `int` | Star rating (4 or 5) |
| `getText(locale)` | `string` | Review text for the locale, falling back to the original text |
| `originalText` | `string\|null` | Original text in its source language (fallback) |
| `originalLanguage` | `string\|null` | Language code of the original text |
| `createdAtTimestamp` | `int` | Created date as Unix timestamp |
| `blocked` | `bool` | Block status (when accessing the repository directly) |
| `sortOrder` | `int` | Custom sort position |

> Note: the review text is locale-specific — use `review.getText(app.request.locale)` (`review.text` without an argument returns the original text as a fallback). The relative time is computed via `google_review_relative_time(...)` instead of being read from a stored value.

---

## Frontend styling

The template uses **Bootstrap 5** for grid layout and follows **BEM naming conventions**:

| BEM Class | Element |
|---|---|
| `.google-reviews` | Wrapper `<section>` |
| `.google-reviews__title` | Optional heading |
| `.google-reviews__card` | Individual review card |
| `.google-reviews__author` | Author area |
| `.google-reviews__avatar` | Profile photo |
| `.google-reviews__avatar--fallback` | Initial fallback without photo |
| `.google-reviews__author-name` | Author name |
| `.google-reviews__rating` | Stars container |
| `.google-reviews__star` | Single star |
| `.google-reviews__star--filled` | Filled star |
| `.google-reviews__text` | Review text |
| `.google-reviews__time` | Relative time |
| `.google-reviews__empty` | Fallback message when no reviews |

Template location:
`Resources/views/includes/blocks/block--google-reviews.html.twig`

### Customizing the template

Once the bundle is installed via Composer, override the template in your main project:

1. **Create the file:**
   ```
   templates/includes/blocks/block--google-reviews.html.twig
   ```

2. **Copy the content from the bundle** and customize (CSS classes, HTML structure, etc.)

Twig searches for templates first in the main project, then in bundles — your version will be used automatically.

---

## Architecture overview

```
vendor/depa/sulu-google-reviews-bundle/
├── src/
│   ├── Admin/GoogleReviewsAdmin.php                   # Sulu navigation & views
│   ├── Command/
│   │   ├── FetchGoogleReviewsCommand.php               # Import per webspace locale
│   │   └── TranslateMissingReviewsCommand.php          # Backfill missing languages
│   ├── Controller/Admin/GoogleReviewController.php
│   ├── DependencyInjection/
│   │   ├── Compiler/TranslatorIntegrationPass.php      # Optionally wire the DeepL adapter
│   │   ├── DepaGoogleReviewsExtension.php              # Auto-configuration via prepend
│   │   └── Configuration.php
│   ├── Entity/GoogleReview.php
│   ├── Repository/GoogleReviewRepository.php
│   ├── Translation/
│   │   ├── ReviewTranslatorInterface.php               # Optional translator contract
│   │   ├── DeeplReviewTranslator.php                   # DeepL adapter (duck-typed)
│   │   └── DeeplTranslatorClientInterface.php
│   ├── Twig/GoogleReviewsTwigExtension.php
│   └── DepaGoogleReviewsBundle.php                     # Registers the compiler pass
└── Resources/
    ├── config/
    │   ├── forms/google_review_details.xml
    │   ├── lists/google_reviews.xml
    │   ├── routes_admin.yaml
    │   └── services.yaml
    ├── js/
    │   ├── index.js                                    # Field type registration
    │   └── GoogleReviewDisplay.js                      # read-only admin field type
    └── views/
        └── includes/blocks/
            └── block--google-reviews.html.twig
```

The bundle extension automatically registers via `PrependExtensionInterface`:
- Sulu Admin: list and form directories, resource routes
- Doctrine ORM: entity mapping
- Twig: views directory

So **no manual entries** in `sulu_admin.yaml` or `twig.yaml` are required. Only the admin field type must be wired into the webpack build on the project side (see Installation, step 7).
