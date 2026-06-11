# SuluGoogleReviewsBundle

![php workflow](https://github.com/depa-berlin/sulu-google-reviews-bundle/actions/workflows/php.yml/badge.svg)
![symfony workflow](https://github.com/depa-berlin/sulu-google-reviews-bundle/actions/workflows/symfony.yml/badge.svg)
[![License: MIT](https://img.shields.io/badge/License-MIT-yellow.svg)](https://github.com/depa-berlin/sulu-google-reviews-bundle/blob/main/LICENSE)
![GitHub Tag](https://img.shields.io/github/v/tag/depa-berlin/sulu-google-reviews-bundle)
![Supports Sulu 3.0 or later](https://img.shields.io/badge/Sulu->=3.0-0088cc?color=00b2df)

Symfony bundle for Sulu CMS 3 that fetches Google Places reviews via API, filters by rating (≥ 4 stars), stores them in a dedicated database table with duplicate prevention, and provides a native Sulu admin interface for moderation. Frontend output via flexible Sulu block.

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
composer require depa-berlin/sulu-google-reviews-bundle
```

### 2. Register bundle

Add to `config/bundles.php`:

```php
Depa\SuluGoogleReviewsBundle\DepaGoogleReviewsBundle::class => ['all' => true],
```

### 4. Import admin routes

Add to `config/routes/routes_admin.yaml`:

```yaml
DepaGoogleReviewsBundle:
    resource: "@DepaGoogleReviewsBundle/Resources/config/routes_admin.yaml"
    prefix: /admin/api
```

### 5. Set environment variables

In `.env.local` (never commit):

```dotenv
GOOGLE_PLACES_API_KEY=AIzaSy...
GOOGLE_PLACE_ID=ChIJ...
```

Find your `Place ID` on [Google Maps Platform](https://developers.google.com/maps/documentation/places/web-service/place-id).

### 6. Run database migrations

```bash
bin/adminconsole doctrine:migrations:migrate
```

Creates the `sulu_google_review` table.

### 7. Clear cache

```bash
bin/adminconsole cache:clear
```

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
| **Title** | Optional heading above reviews |
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

The command imports only new reviews (≥ 4 stars). Existing entries are skipped.

---

## Moderation in Sulu Admin

After installation, the menu item **"Google Reviews"** appears in the Sulu backend (icon: comment).

### List view

Shows all imported reviews with author, star rating, date, block status, and sort order.

### Detail view

| Section | Fields | Editable |
|---|---|---|
| **Review** (imported from Google) | Author, stars, date, relative time, text, profile photo URL | No |
| **Moderation & Display** | Block review, sort order | Yes |

#### Block review

Blocked reviews won't appear on the frontend and are excluded from `get_stored_google_reviews()` results.

#### Custom sort order

The **Sort Order** field is used when the block's sort mode is set to "Custom Order".

- `0` = no priority (sorted by date on tie)
- `1`, `2`, `3`, … = ascending display order

When assigning an already-taken sort position, all other entries at that position and below are automatically incremented.

---

## Twig function (direct usage)

The bundle provides the `get_stored_google_reviews()` Twig function for use anywhere in templates:

```twig
{% set reviews = get_stored_google_reviews(limit, sort) %}
```

| Parameter | Type | Default | Possible Values |
|---|---|---|---|
| `limit` | `int` | `5` | Any positive integer |
| `sort` | `string` | `'date'` | `'date'`, `'rating'`, `'custom'` |

**Example:**

```twig
{% for review in get_stored_google_reviews(3, 'rating') %}
    <p>{{ review.authorName }}: {{ review.rating }} stars</p>
    <p>{{ review.text }}</p>
{% endfor %}
```

### Available review properties

| Property | Type | Description |
|---|---|---|
| `authorName` | `string` | Reviewer name |
| `profilePhotoUrl` | `string\|null` | URL to Google profile photo |
| `rating` | `int` | Star rating (4 or 5) |
| `text` | `string` | Review text |
| `createdAtTimestamp` | `int` | Created date as Unix timestamp |
| `relativeTimeDescription` | `string` | Relative time from Google (e.g., "3 months ago") |
| `blocked` | `bool` | Block status (when accessing repository directly) |
| `sortOrder` | `int` | Custom sort position |

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
packages/sulu-google-reviews-bundle/
├── src/
│   ├── Admin/GoogleReviewsAdmin.php          # Sulu navigation & views
│   ├── Command/FetchGoogleReviewsCommand.php  # Import command
│   ├── Controller/Admin/GoogleReviewController.php
│   ├── DependencyInjection/
│   │   ├── DepaGoogleReviewsExtension.php     # Auto-configuration via prepend
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

The bundle extension automatically registers via `PrependExtensionInterface`:
- Sulu Admin: list and form directories, resource routes
- Doctrine ORM: entity mapping
- Twig: views directory

**No manual entries** in `sulu_admin.yaml` or `twig.yaml` required.
