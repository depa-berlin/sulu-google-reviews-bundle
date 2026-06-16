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
            // sortOrder 0 = "keine Priorität": diese Reviews ans Ende, sonst nach sortOrder aufsteigend
            self::SORT_CUSTOM => $qb
                ->addSelect('CASE WHEN r.sortOrder = 0 THEN 1 ELSE 0 END AS HIDDEN hasNoPriority')
                ->orderBy('hasNoPriority', 'ASC')
                ->addOrderBy('r.sortOrder', 'ASC')
                ->addOrderBy('r.createdAtTimestamp', 'DESC'),
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
     *
     * Die Reviews werden über die Unit of Work geladen und verändert (nicht per
     * Bulk-DQL-UPDATE), damit die Identity Map konsistent bleibt. Das Persistieren
     * übernimmt der abschließende flush() des Aufrufers (innerhalb der Transaktion).
     */
    public function shiftSortOrderFrom(int $fromPosition, int $excludeId): void
    {
        /** @var GoogleReview[] $reviews */
        $reviews = $this->createQueryBuilder('r')
            ->where('r.sortOrder >= :from')
            ->andWhere('r.id != :excludeId')
            ->setParameter('from', $fromPosition)
            ->setParameter('excludeId', $excludeId)
            ->getQuery()
            ->getResult();

        foreach ($reviews as $review) {
            $review->setSortOrder($review->getSortOrder() + 1);
        }
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
