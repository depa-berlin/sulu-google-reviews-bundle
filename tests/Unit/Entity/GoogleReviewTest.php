<?php

declare(strict_types=1);

namespace Depa\SuluGoogleReviewsBundle\Tests\Unit\Entity;

use Depa\SuluGoogleReviewsBundle\Entity\GoogleReview;
use PHPUnit\Framework\TestCase;

class GoogleReviewTest extends TestCase
{
    private GoogleReview $review;

    protected function setUp(): void
    {
        $this->review = new GoogleReview();
    }

    public function testDefaultValues(): void
    {
        self::assertNull($this->review->getId());
        self::assertSame('', $this->review->getAuthorName());
        self::assertNull($this->review->getProfilePhotoUrl());
        self::assertSame(0, $this->review->getRating());
        self::assertNull($this->review->getOriginalText());
        self::assertNull($this->review->getOriginalLanguage());
        self::assertSame([], $this->review->getTranslations());
        self::assertSame('', $this->review->getText());
        self::assertSame(0, $this->review->getCreatedAtTimestamp());
        self::assertFalse($this->review->isBlocked());
        self::assertSame(0, $this->review->getSortOrder());
    }

    public function testSettersReturnStatic(): void
    {
        self::assertSame($this->review, $this->review->setAuthorName('Test'));
        self::assertSame($this->review, $this->review->setProfilePhotoUrl('https://example.com/photo.jpg'));
        self::assertSame($this->review, $this->review->setRating(5));
        self::assertSame($this->review, $this->review->setOriginalText('Great service!'));
        self::assertSame($this->review, $this->review->setOriginalLanguage('en'));
        self::assertSame($this->review, $this->review->setTranslation('de', 'Toller Service!'));
        self::assertSame($this->review, $this->review->setCreatedAtTimestamp(1700000000));
        self::assertSame($this->review, $this->review->setBlocked(true));
        self::assertSame($this->review, $this->review->setSortOrder(1));
    }

    public function testGetTextReturnsLocaleSpecificTranslation(): void
    {
        $this->review
            ->setOriginalText('Super magasin')
            ->setOriginalLanguage('fr')
            ->setTranslation('de', 'Tolles Geschäft')
            ->setTranslation('en', 'Great shop');

        self::assertSame('Tolles Geschäft', $this->review->getText('de'));
        self::assertSame('Great shop', $this->review->getText('en'));
    }

    public function testGetTextFallsBackToOriginalForUnknownLocale(): void
    {
        $this->review
            ->setOriginalText('Super magasin')
            ->setOriginalLanguage('fr')
            ->setTranslation('de', 'Tolles Geschäft');

        // Keine italienische Übersetzung -> Fallback auf Originaltext
        self::assertSame('Super magasin', $this->review->getText('it'));
        // Ohne Locale ebenfalls Originaltext
        self::assertSame('Super magasin', $this->review->getText());
    }

    public function testToDisplayArrayContainsAllLanguages(): void
    {
        $this->review
            ->setAuthorName('Maria Schneider')
            ->setRating(5)
            ->setCreatedAtTimestamp(1700000000)
            ->setOriginalText('Super magasin')
            ->setOriginalLanguage('fr')
            ->setTranslation('de', 'Tolles Geschäft')
            ->setTranslation('en', 'Great shop');

        $display = $this->review->toDisplayArray();

        self::assertSame('Maria Schneider', $display['authorName']);
        self::assertSame(5, $display['rating']);
        self::assertSame('fr', $display['originalLanguage']);
        self::assertSame('Tolles Geschäft', $display['translations']['de']);
        self::assertSame('Great shop', $display['translations']['en']);
        self::assertSame(1700000000, $display['timestamp']);
        self::assertNotSame('', $display['date']);
    }

    public function testProfilePhotoUrlNullable(): void
    {
        $this->review->setProfilePhotoUrl('https://example.com/photo.jpg');
        $this->review->setProfilePhotoUrl(null);

        self::assertNull($this->review->getProfilePhotoUrl());
    }

    public function testMapToArrayContainsAllFields(): void
    {
        $this->review
            ->setAuthorName('Thomas Weber')
            ->setRating(4)
            ->setOriginalText('Very good.')
            ->setOriginalLanguage('en')
            ->setTranslation('de', 'Sehr gut.')
            ->setCreatedAtTimestamp(1700000000)
            ->setBlocked(false)
            ->setSortOrder(3);

        $array = $this->review->mapToArray();

        self::assertArrayHasKey('id', $array);
        self::assertArrayHasKey('authorName', $array);
        self::assertArrayHasKey('profilePhotoUrl', $array);
        self::assertArrayHasKey('rating', $array);
        self::assertArrayHasKey('text', $array);
        self::assertArrayHasKey('reviewDisplay', $array);
        self::assertArrayHasKey('originalText', $array);
        self::assertArrayHasKey('originalLanguage', $array);
        self::assertArrayHasKey('createdAtTimestamp', $array);
        self::assertArrayHasKey('moderation', $array);
        self::assertArrayHasKey('blocked', $array);
        self::assertArrayHasKey('sortOrder', $array);
    }

    public function testMapToArrayValues(): void
    {
        $this->review
            ->setAuthorName('Thomas Weber')
            ->setRating(4)
            ->setOriginalText('Very good.')
            ->setOriginalLanguage('en')
            ->setCreatedAtTimestamp(1700000000)
            ->setBlocked(false)
            ->setSortOrder(3);

        $array = $this->review->mapToArray();

        self::assertSame('Thomas Weber', $array['authorName']);
        self::assertSame(4, $array['rating']);
        self::assertSame('Very good.', $array['text']);
        self::assertSame('en', $array['originalLanguage']);
        self::assertSame(1700000000, $array['createdAtTimestamp']);
        self::assertFalse($array['blocked']);
        self::assertSame(3, $array['sortOrder']);
    }

    public function testResourceKeyConstant(): void
    {
        self::assertSame('google_reviews', GoogleReview::RESOURCE_KEY);
    }

    public function testSecurityContextConstant(): void
    {
        self::assertSame('sulu.google_reviews.reviews', GoogleReview::SECURITY_CONTEXT);
    }
}
