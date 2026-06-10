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
        self::assertSame('', $this->review->getText());
        self::assertSame(0, $this->review->getCreatedAtTimestamp());
        self::assertSame('', $this->review->getRelativeTimeDescription());
        self::assertFalse($this->review->isBlocked());
        self::assertSame(0, $this->review->getSortOrder());
    }

    public function testSettersReturnStatic(): void
    {
        self::assertSame($this->review, $this->review->setAuthorName('Test'));
        self::assertSame($this->review, $this->review->setProfilePhotoUrl('https://example.com/photo.jpg'));
        self::assertSame($this->review, $this->review->setRating(5));
        self::assertSame($this->review, $this->review->setText('Great service!'));
        self::assertSame($this->review, $this->review->setCreatedAtTimestamp(1700000000));
        self::assertSame($this->review, $this->review->setRelativeTimeDescription('vor 3 Monaten'));
        self::assertSame($this->review, $this->review->setBlocked(true));
        self::assertSame($this->review, $this->review->setSortOrder(1));
    }

    public function testGettersReturnSetValues(): void
    {
        $this->review
            ->setAuthorName('Maria Schneider')
            ->setProfilePhotoUrl('https://example.com/photo.jpg')
            ->setRating(5)
            ->setText('Excellent!')
            ->setCreatedAtTimestamp(1700000000)
            ->setRelativeTimeDescription('vor 3 Monaten')
            ->setBlocked(true)
            ->setSortOrder(2);

        self::assertSame('Maria Schneider', $this->review->getAuthorName());
        self::assertSame('https://example.com/photo.jpg', $this->review->getProfilePhotoUrl());
        self::assertSame(5, $this->review->getRating());
        self::assertSame('Excellent!', $this->review->getText());
        self::assertSame(1700000000, $this->review->getCreatedAtTimestamp());
        self::assertSame('vor 3 Monaten', $this->review->getRelativeTimeDescription());
        self::assertTrue($this->review->isBlocked());
        self::assertSame(2, $this->review->getSortOrder());
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
            ->setText('Very good.')
            ->setCreatedAtTimestamp(1700000000)
            ->setRelativeTimeDescription('vor 5 Monaten')
            ->setBlocked(false)
            ->setSortOrder(3);

        $array = $this->review->mapToArray();

        self::assertArrayHasKey('id', $array);
        self::assertArrayHasKey('authorName', $array);
        self::assertArrayHasKey('profilePhotoUrl', $array);
        self::assertArrayHasKey('rating', $array);
        self::assertArrayHasKey('text', $array);
        self::assertArrayHasKey('createdAtTimestamp', $array);
        self::assertArrayHasKey('relativeTimeDescription', $array);
        self::assertArrayHasKey('blocked', $array);
        self::assertArrayHasKey('sortOrder', $array);
    }

    public function testMapToArrayValues(): void
    {
        $this->review
            ->setAuthorName('Thomas Weber')
            ->setRating(4)
            ->setText('Very good.')
            ->setCreatedAtTimestamp(1700000000)
            ->setRelativeTimeDescription('vor 5 Monaten')
            ->setBlocked(false)
            ->setSortOrder(3);

        $array = $this->review->mapToArray();

        self::assertSame('Thomas Weber', $array['authorName']);
        self::assertSame(4, $array['rating']);
        self::assertSame('Very good.', $array['text']);
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
