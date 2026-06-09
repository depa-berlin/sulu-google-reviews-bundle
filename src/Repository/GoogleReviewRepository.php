<?php

declare(strict_types=1);

namespace Depa\SuluGoogleReviewsBundle\Repository;

use Depa\SuluGoogleReviewsBundle\Entity\GoogleReview;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<GoogleReview>
 */
class GoogleReviewRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, GoogleReview::class);
    }

    /**
     * @return GoogleReview[]
     */
    public function findTopReviews(int $limit): array
    {
        return $this->createQueryBuilder('r')
            ->where('r.blocked = false')
            ->orderBy('r.createdAtTimestamp', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    public function save(GoogleReview $review, bool $flush = true): void
    {
        $em = $this->getEntityManager();
        $em->persist($review);

        if ($flush) {
            $em->flush();
        }
    }
}
