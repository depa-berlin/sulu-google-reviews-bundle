<?php

declare(strict_types=1);

namespace Depa\SuluGoogleReviewsBundle\Twig;

use Depa\SuluGoogleReviewsBundle\Repository\GoogleReviewRepository;
use Twig\Attribute\AsTwigFunction;

class GoogleReviewsTwigExtension
{
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
        return $this->repository->findTopReviews($limit, $sort);
    }
}
