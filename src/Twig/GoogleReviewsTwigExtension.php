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
}
