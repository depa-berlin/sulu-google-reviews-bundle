<?php

declare(strict_types=1);

namespace Depa\SuluGoogleReviewsBundle\Twig;

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
     * Phrasing is provided for de/en; other locales fall back to English.
     */
    #[AsTwigFunction(name: 'google_review_relative_time')]
    public function relativeTime(int $timestamp, ?string $locale = null): string
    {
        if ($timestamp <= 0) {
            return '';
        }

        $diff = \max(0, \time() - $timestamp);
        $lang = \strtolower(\explode('_', \str_replace('-', '_', (string) $locale))[0]);

        $units = [
            'year'   => 31536000,
            'month'  => 2592000,
            'week'   => 604800,
            'day'    => 86400,
            'hour'   => 3600,
            'minute' => 60,
        ];

        foreach ($units as $unit => $seconds) {
            if ($diff >= $seconds) {
                return $this->phrase($lang, \intdiv($diff, $seconds), $unit);
            }
        }

        return $this->phrase($lang, 0, 'now');
    }

    private function phrase(string $lang, int $amount, string $unit): string
    {
        $now = ['de' => 'gerade eben', 'en' => 'just now'];

        /** @var array<string, array<string, array{0: string, 1: string}>> $forms */
        $forms = [
            'de' => [
                'minute' => ['vor %d Minute', 'vor %d Minuten'],
                'hour'   => ['vor %d Stunde', 'vor %d Stunden'],
                'day'    => ['vor %d Tag', 'vor %d Tagen'],
                'week'   => ['vor %d Woche', 'vor %d Wochen'],
                'month'  => ['vor %d Monat', 'vor %d Monaten'],
                'year'   => ['vor %d Jahr', 'vor %d Jahren'],
            ],
            'en' => [
                'minute' => ['%d minute ago', '%d minutes ago'],
                'hour'   => ['%d hour ago', '%d hours ago'],
                'day'    => ['%d day ago', '%d days ago'],
                'week'   => ['%d week ago', '%d weeks ago'],
                'month'  => ['%d month ago', '%d months ago'],
                'year'   => ['%d year ago', '%d years ago'],
            ],
        ];

        if ('now' === $unit) {
            return $now[$lang] ?? $now['en'];
        }

        $set = $forms[$lang] ?? $forms['en'];

        if (!isset($set[$unit])) {
            return '';
        }

        return \sprintf(1 === $amount ? $set[$unit][0] : $set[$unit][1], $amount);
    }
}
