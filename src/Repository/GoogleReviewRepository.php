<?php

declare(strict_types=1);

namespace Depa\SuluGoogleReviewsBundle\Repository;

use Depa\SuluGoogleReviewsBundle\Entity\GoogleReview;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<GoogleReview>
 */
class GoogleReviewRepository extends ServiceEntityRepository
{
    public const SORT_DATE   = 'date';
    public const SORT_RATING = 'rating';
    public const SORT_CUSTOM = 'custom';

    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, GoogleReview::class);
    }

    /**
     * @return GoogleReview[]
     */
    public function findTopReviews(int $limit, string $sort = self::SORT_DATE): array
    {
        $qb = $this->createQueryBuilder('r')
            ->where('r.blocked = false')
            ->setMaxResults($limit);

        match ($sort) {
            self::SORT_RATING => $qb->orderBy('r.rating', 'DESC')->addOrderBy('r.createdAtTimestamp', 'DESC'),
            self::SORT_CUSTOM => $qb->orderBy('r.sortOrder', 'ASC')->addOrderBy('r.createdAtTimestamp', 'DESC'),
            default           => $qb->orderBy('r.createdAtTimestamp', 'DESC'),
        };

        return $qb->getQuery()->getResult();
    }

    public function isSortOrderTaken(int $sortOrder, int $excludeId): bool
    {
        return (int) $this->createQueryBuilder('r')
            ->select('COUNT(r.id)')
            ->where('r.sortOrder = :sortOrder')
            ->andWhere('r.id != :excludeId')
            ->setParameter('sortOrder', $sortOrder)
            ->setParameter('excludeId', $excludeId)
            ->getQuery()
            ->getSingleScalarResult() > 0;
    }

    /**
     * Shifts all reviews (except $excludeId) with sortOrder >= $fromPosition up by one.
     * Call this before setting the new sortOrder on the target review.
     */
    public function shiftSortOrderFrom(int $fromPosition, int $excludeId): void
    {
        $this->getEntityManager()
            ->createQuery(
                'UPDATE Depa\SuluGoogleReviewsBundle\Entity\GoogleReview r
                 SET r.sortOrder = r.sortOrder + 1
                 WHERE r.sortOrder >= :from AND r.id != :excludeId'
            )
            ->setParameter('from', $fromPosition)
            ->setParameter('excludeId', $excludeId)
            ->execute();
    }

    public function wrapInTransaction(callable $func): mixed
    {
        return $this->getEntityManager()->wrapInTransaction($func);
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
