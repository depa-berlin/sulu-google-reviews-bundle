# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

## [1.0.0-rc3] - 2026-07-02

### Added

- `--newest` option for `sulu:google-reviews:fetch` to fetch the newest reviews via the Legacy Places API (`reviews_sort=newest`) instead of Google's "most relevant" (#4)
- Ship the `block--google-reviews` block definition with the bundle (registered via `sulu_admin.templates.block.directories`), so `<type ref="block--google-reviews"/>` resolves out of the box (#6)
- Editable "empty state" text field (`emptyText`) on the reviews block (#6)

### Changed

- **BREAKING:** renamed the database table `sulu_google_review` → `depa_googlereviews_reviews` to avoid the reserved `sulu_` prefix. Existing installations must run `RENAME TABLE sulu_google_review TO depa_googlereviews_reviews;` (#3)
- Pass an explicit UTC timezone to `Carbon::createFromTimestamp()` for version-stable behaviour across Carbon 2/3 (#8)

### Fixed

- Legacy `--newest` fetch no longer stores a translated review text as the "original" with the wrong language; a later default fetch can fill in the true original (#5)
- Clamp `sortOrder` server-side to `>= 0` in the moderation endpoint (the admin field only enforced this client-side) (#7)
- Skip translating a review into its own original language in `sulu:google-reviews:translate-missing` (avoids a pointless source==target call) (#7)

### Removed

- **BREAKING:** removed the reviewer profile photo entirely (frontend, admin, fetch and storage) to avoid uncoerced third-party requests to Google's image CDN (GDPR) and the Places API content-caching restriction. Drop the now-unused column with `ALTER TABLE depa_googlereviews_reviews DROP COLUMN profile_photo_url;` The mandatory author-name attribution is unaffected (#9)

## [1.0.0] - 2025-06-10

### Added

- Initial release
- Fetch Google Places reviews via Places API (≥ 4 stars only)
- Automatic duplicate detection using unique constraint
- Admin interface for moderation (blocking reviews, custom sorting)
- Sulu block for frontend display with three sort modes (date, rating, custom)
- Twig extension for direct template access
- PHPUnit and PHPStan test suite
