<?php

declare(strict_types=1);

namespace Depa\SuluGoogleReviewsBundle\Tests\Unit\Twig;

use Depa\SuluGoogleReviewsBundle\Repository\GoogleReviewRepository;
use Depa\SuluGoogleReviewsBundle\Twig\GoogleReviewsTwigExtension;
use PHPUnit\Framework\TestCase;

class GoogleReviewsTwigExtensionTest extends TestCase
{
    private function extension(): GoogleReviewsTwigExtension
    {
        return new GoogleReviewsTwigExtension($this->createMock(GoogleReviewRepository::class));
    }

    public function testRelativeTimeReturnsEmptyForZero(): void
    {
        self::assertSame('', $this->extension()->relativeTime(0, 'de'));
    }

    public function testRelativeTimeGerman(): void
    {
        $result = $this->extension()->relativeTime(\time() - (210 * 86400), 'de');

        self::assertStringContainsString('Monat', $result);
        self::assertStringContainsString('vor', $result);
    }

    public function testRelativeTimeEnglish(): void
    {
        $result = $this->extension()->relativeTime(\time() - (150 * 86400), 'en');

        self::assertStringContainsString('month', $result);
        self::assertStringContainsString('ago', $result);
    }

    public function testRelativeTimeFrenchIsLocalizedNotEnglishFallback(): void
    {
        // Carbon localizes ~280 locales, so French is real French (no English fallback).
        $result = $this->extension()->relativeTime(\time() - (3 * 86400), 'fr');

        self::assertStringContainsString('jour', $result);
    }

    public function testRelativeTimeRegionLocaleFallsBackToBaseLanguage(): void
    {
        $result = $this->extension()->relativeTime(\time() - (2 * 365 * 86400), 'de_at');

        self::assertStringContainsString('Jahr', $result);
    }
}
