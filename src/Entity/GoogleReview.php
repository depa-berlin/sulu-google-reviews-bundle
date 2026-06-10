<?php

declare(strict_types=1);

namespace Depa\SuluGoogleReviewsBundle\Entity;

use Depa\SuluGoogleReviewsBundle\Repository\GoogleReviewRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: GoogleReviewRepository::class)]
#[ORM\Table(name: 'sulu_google_review')]
#[ORM\UniqueConstraint(name: 'uq_google_review_author_ts', columns: ['author_name', 'created_at_timestamp'])]
class GoogleReview
{
    public const RESOURCE_KEY = 'google_reviews';
    public const SECURITY_CONTEXT = 'sulu.google_reviews.reviews';

    #[ORM\Id]
    #[ORM\Column(type: 'integer')]
    #[ORM\GeneratedValue]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    private string $authorName = '';

    #[ORM\Column(length: 500, nullable: true)]
    private ?string $profilePhotoUrl = null;

    #[ORM\Column(type: 'integer')]
    private int $rating = 0;

    #[ORM\Column(type: 'text')]
    private string $text = '';

    #[ORM\Column(type: 'integer')]
    private int $createdAtTimestamp = 0;

    #[ORM\Column(length: 100)]
    private string $relativeTimeDescription = '';

    #[ORM\Column(type: 'boolean')]
    private bool $blocked = false;

    #[ORM\Column(type: 'integer')]
    private int $sortOrder = 0;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getAuthorName(): string
    {
        return $this->authorName;
    }

    public function setAuthorName(string $authorName): static
    {
        $this->authorName = $authorName;

        return $this;
    }

    public function getProfilePhotoUrl(): ?string
    {
        return $this->profilePhotoUrl;
    }

    public function setProfilePhotoUrl(?string $profilePhotoUrl): static
    {
        $this->profilePhotoUrl = $profilePhotoUrl;

        return $this;
    }

    public function getRating(): int
    {
        return $this->rating;
    }

    public function setRating(int $rating): static
    {
        $this->rating = $rating;

        return $this;
    }

    public function getText(): string
    {
        return $this->text;
    }

    public function setText(string $text): static
    {
        $this->text = $text;

        return $this;
    }

    public function getCreatedAtTimestamp(): int
    {
        return $this->createdAtTimestamp;
    }

    public function setCreatedAtTimestamp(int $createdAtTimestamp): static
    {
        $this->createdAtTimestamp = $createdAtTimestamp;

        return $this;
    }

    public function getRelativeTimeDescription(): string
    {
        return $this->relativeTimeDescription;
    }

    public function setRelativeTimeDescription(string $relativeTimeDescription): static
    {
        $this->relativeTimeDescription = $relativeTimeDescription;

        return $this;
    }

    public function isBlocked(): bool
    {
        return $this->blocked;
    }

    public function setBlocked(bool $blocked): static
    {
        $this->blocked = $blocked;

        return $this;
    }

    public function getSortOrder(): int
    {
        return $this->sortOrder;
    }

    public function setSortOrder(int $sortOrder): static
    {
        $this->sortOrder = $sortOrder;

        return $this;
    }

    /**
     * @return array{id: int|null, authorName: string, profilePhotoUrl: string|null, rating: int, text: string, createdAtTimestamp: int, relativeTimeDescription: string, blocked: bool, sortOrder: int}
     */
    public function mapToArray(): array
    {
        return [
            'id'                      => $this->id,
            'authorName'              => $this->authorName,
            'profilePhotoUrl'         => $this->profilePhotoUrl,
            'rating'                  => $this->rating,
            'text'                    => $this->text,
            'createdAtTimestamp'      => $this->createdAtTimestamp,
            'relativeTimeDescription' => $this->relativeTimeDescription,
            'blocked'                 => $this->blocked,
            'sortOrder'               => $this->sortOrder,
        ];
    }
}
