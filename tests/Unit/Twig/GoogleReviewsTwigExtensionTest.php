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

    public function testRelativeTimeMonthsGerman(): void
    {
        $sevenMonths = \time() - (7 * 2592000) - 10;

        self::assertSame('vor 7 Monaten', $this->extension()->relativeTime($sevenMonths, 'de'));
    }

    public function testRelativeTimeMonthsEnglish(): void
    {
        $fiveMonths = \time() - (5 * 2592000) - 10;

        self::assertSame('5 months ago', $this->extension()->relativeTime($fiveMonths, 'en'));
    }

    public function testRelativeTimeSingularGerman(): void
    {
        $oneMonth = \time() - 2592000 - 10;

        self::assertSame('vor 1 Monat', $this->extension()->relativeTime($oneMonth, 'de'));
    }

    public function testRelativeTimeFallsBackToEnglishForUnknownLocale(): void
    {
        $threeDays = \time() - (3 * 86400) - 10;

        self::assertSame('3 days ago', $this->extension()->relativeTime($threeDays, 'fr'));
    }

    public function testRelativeTimeUsesRegionLocaleBaseLanguage(): void
    {
        $twoYears = \time() - (2 * 31536000) - 10;

        self::assertSame('vor 2 Jahren', $this->extension()->relativeTime($twoYears, 'de_at'));
    }
}
