<?php

declare(strict_types=1);

namespace Depa\SuluGoogleReviewsBundle\Twig;

use Carbon\CarbonImmutable;
use Depa\SuluGoogleReviewsBundle\Repository\GoogleReviewRepository;
use Twig\Attribute\AsTwigFunction;

class GoogleReviewsTwigExtension
{
    /**
     * Per-request memoization keyed by "limit|sort" — avoids re-querying when the
     * block is rendered more than once in a single request.
     *
     * @var array<string, array<int, \Depa\SuluGoogleReviewsBundle\Entity\GoogleReview>>
     */
    private array $cache = [];

    public function __construct(
        private readonly GoogleReviewRepository $repository,
    ) {
    }

    /**
     * @return array<int, \Depa\SuluGoogleReviewsBundle\Entity\GoogleReview>
     */
    #[AsTwigFunction(name: 'get_stored_google_reviews')]
    public function getStoredGoogleReviews(int $limit = 5, string $sort = GoogleReviewRepository::SORT_DATE): array
    {
        return $this->cache[$limit . '|' . $sort] ??= $this->repository->findTopReviews($limit, $sort);
    }

    /**
     * Locale-aware relative time computed from the stored timestamp (always current,
     * unlike Google's relativePublishTimeDescription, which is a point-in-time string).
     * Uses Carbon's diffForHumans(), which is localized for ~280 locales.
     */
    #[AsTwigFunction(name: 'google_review_relative_time')]
    public function relativeTime(int $timestamp, ?string $locale = null): string
    {
        if ($timestamp <= 0) {
            return '';
        }

        // Zeitzone explizit setzen: der Default von createFromTimestamp() unterscheidet sich
        // zwischen Carbon 2 (System-TZ) und 3 (UTC). Für diffForHumans() ist das Ergebnis ohnehin
        // zeitzonenunabhängig; die explizite Angabe hält das Verhalten versionsstabil.
        $date = CarbonImmutable::createFromTimestamp($timestamp, 'UTC');

        $normalized = $this->normalizeLocale($locale);
        if (null !== $normalized) {
            $date = $date->settings(['locale' => $normalized]);
        }

        return $date->diffForHumans();
    }

    /**
     * Normalizes a Sulu locale to Carbon's expected form, e.g. "de_at" -> "de_AT".
     */
    private function normalizeLocale(?string $locale): ?string
    {
        if (null === $locale || '' === $locale) {
            return null;
        }

        $parts = \preg_split('/[-_]/', $locale) ?: [];
        $language = \strtolower($parts[0] ?? '');

        if ('' === $language) {
            return null;
        }

        return isset($parts[1]) ? $language . '_' . \strtoupper($parts[1]) : $language;
    }
}
