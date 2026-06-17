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

    #[ORM\Column(type: 'integer')]
    private int $createdAtTimestamp = 0;

    /**
     * Authentic original review text as written by the author (language-independent).
     */
    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $originalText = null;

    /**
     * BCP-47 language code of the original text, e.g. "de", "en".
     */
    #[ORM\Column(length: 10, nullable: true)]
    private ?string $originalLanguage = null;

    /**
     * Per-locale content keyed by the Sulu locale, e.g.
     * ['de' => ['text' => '…', 'relativeTime' => 'vor 1 Monat'], 'en' => [...]].
     * A new webspace locale simply adds a key — no schema change required.
     *
     * @var array<string, array{text: string, relativeTime: string}>
     */
    #[ORM\Column(type: 'json')]
    private array $translations = [];

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

    public function getCreatedAtTimestamp(): int
    {
        return $this->createdAtTimestamp;
    }

    public function setCreatedAtTimestamp(int $createdAtTimestamp): static
    {
        $this->createdAtTimestamp = $createdAtTimestamp;

        return $this;
    }

    public function getOriginalText(): ?string
    {
        return $this->originalText;
    }

    public function setOriginalText(?string $originalText): static
    {
        $this->originalText = $originalText;

        return $this;
    }

    public function getOriginalLanguage(): ?string
    {
        return $this->originalLanguage;
    }

    public function setOriginalLanguage(?string $originalLanguage): static
    {
        $this->originalLanguage = $originalLanguage;

        return $this;
    }

    /**
     * @return array<string, array{text: string, relativeTime: string}>
     */
    public function getTranslations(): array
    {
        return $this->translations;
    }

    /**
     * @param array<string, array{text: string, relativeTime: string}> $translations
     */
    public function setTranslations(array $translations): static
    {
        $this->translations = $translations;

        return $this;
    }

    public function setTranslation(string $locale, string $text, string $relativeTime): static
    {
        $this->translations[$locale] = ['text' => $text, 'relativeTime' => $relativeTime];

        return $this;
    }

    /**
     * Review text for the given locale, falling back to the original text.
     */
    public function getText(?string $locale = null): string
    {
        if (null !== $locale && isset($this->translations[$locale]['text'])) {
            return $this->translations[$locale]['text'];
        }

        return $this->originalText ?? '';
    }

    /**
     * Relative time description ("vor 1 Monat") for the given locale.
     *
     * Returns an empty string when no description is stored for that locale —
     * deliberately no cross-locale fallback, otherwise a French page could show
     * a German "vor 1 Monat".
     */
    public function getRelativeTime(?string $locale = null): string
    {
        if (null !== $locale && isset($this->translations[$locale]['relativeTime'])) {
            return $this->translations[$locale]['relativeTime'];
        }

        return '';
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
     * Structured read-only payload consumed by the admin display field type
     * (google_review_display).
     *
     * @return array{authorName: string, profilePhotoUrl: string|null, rating: int, date: string, originalLanguage: string|null, translations: array<string, array{text: string, relativeTime: string}>}
     */
    public function toDisplayArray(): array
    {
        return [
            'authorName'       => $this->authorName,
            'profilePhotoUrl'  => $this->profilePhotoUrl,
            'rating'           => $this->rating,
            'date'             => $this->createdAtTimestamp > 0 ? \date('d.m.Y', $this->createdAtTimestamp) : '',
            'originalLanguage' => $this->originalLanguage,
            'translations'     => $this->translations,
        ];
    }

    /**
     * @return array{id: int|null, authorName: string, profilePhotoUrl: string|null, rating: int, text: string, reviewDisplay: array{authorName: string, profilePhotoUrl: string|null, rating: int, date: string, originalLanguage: string|null, translations: array<string, array{text: string, relativeTime: string}>}, originalText: string|null, originalLanguage: string|null, createdAtTimestamp: int, relativeTimeDescription: string, blocked: bool, sortOrder: int, moderation: array{blocked: bool, sortOrder: int}}
     */
    public function mapToArray(): array
    {
        return [
            'id'                      => $this->id,
            'authorName'              => $this->authorName,
            'profilePhotoUrl'         => $this->profilePhotoUrl,
            'rating'                  => $this->rating,
            'text'                    => $this->getText(),
            'reviewDisplay'           => $this->toDisplayArray(),
            'originalText'            => $this->originalText,
            'originalLanguage'        => $this->originalLanguage,
            'createdAtTimestamp'      => $this->createdAtTimestamp,
            'relativeTimeDescription' => $this->getRelativeTime(),
            'blocked'                 => $this->blocked,
            'sortOrder'               => $this->sortOrder,
            // Editierbarer Moderationsbereich (eigener Admin-Feldtyp google_review_moderation)
            'moderation'              => [
                'blocked'   => $this->blocked,
                'sortOrder' => $this->sortOrder,
            ],
        ];
    }
}
