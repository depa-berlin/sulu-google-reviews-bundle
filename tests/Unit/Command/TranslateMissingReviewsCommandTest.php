<?php

declare(strict_types=1);

namespace Depa\SuluGoogleReviewsBundle\Tests\Unit\Command;

use Depa\SuluGoogleReviewsBundle\Command\TranslateMissingReviewsCommand;
use Depa\SuluGoogleReviewsBundle\Entity\GoogleReview;
use Depa\SuluGoogleReviewsBundle\Repository\GoogleReviewRepository;
use Depa\SuluGoogleReviewsBundle\Translation\ReviewTranslatorInterface;
use PHPUnit\Framework\TestCase;
use Sulu\Component\Webspace\Manager\WebspaceManagerInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

class TranslateMissingReviewsCommandTest extends TestCase
{
    /**
     * @param list<string> $locales
     */
    private function webspaceManager(array $locales): WebspaceManagerInterface
    {
        $manager = $this->createMock(WebspaceManagerInterface::class);
        $manager->method('getAllLocales')->willReturn($locales);

        return $manager;
    }

    private function translator(): ReviewTranslatorInterface
    {
        return new class() implements ReviewTranslatorInterface {
            public function translate(string $text, string $targetLocale, ?string $sourceLocale = null): string
            {
                return $targetLocale . ':' . $text;
            }
        };
    }

    public function testFailsWithoutTranslator(): void
    {
        $command = new TranslateMissingReviewsCommand(
            $this->createMock(GoogleReviewRepository::class),
            $this->webspaceManager(['de']),
            null,
        );

        $tester = new CommandTester($command);
        $result = $tester->execute([]);

        self::assertSame(Command::FAILURE, $result);
        self::assertStringContainsString('Kein Übersetzungsdienst', $tester->getDisplay());
    }

    public function testFillsOnlyMissingLocales(): void
    {
        $review = new GoogleReview();
        $review->setOriginalText('Super magasin')
            ->setOriginalLanguage('fr')
            ->setTranslation('de', 'Tolles Geschäft', 'vor 2 Monaten');

        $repository = $this->createMock(GoogleReviewRepository::class);
        $repository->method('findAll')->willReturn([$review]);
        $repository->expects(self::once())->method('save')->with($review, false);
        $repository->expects(self::once())->method('flush');

        $command = new TranslateMissingReviewsCommand(
            $repository,
            $this->webspaceManager(['de', 'en']),
            $this->translator(),
        );

        $tester = new CommandTester($command);
        $result = $tester->execute([]);

        self::assertSame(Command::SUCCESS, $result);
        // de war vorhanden und bleibt unverändert, en wird ergänzt
        self::assertSame('Tolles Geschäft', $review->getText('de'));
        self::assertSame('en:Super magasin', $review->getText('en'));
        self::assertStringContainsString('1 Übersetzungen ergänzt in 1 Bewertungen', $tester->getDisplay());
    }

    public function testSkipsReviewsWithoutOriginalText(): void
    {
        $review = new GoogleReview();
        $review->setAuthorName('Ohne Text');

        $repository = $this->createMock(GoogleReviewRepository::class);
        $repository->method('findAll')->willReturn([$review]);
        $repository->expects(self::never())->method('save');
        $repository->expects(self::never())->method('flush');

        $command = new TranslateMissingReviewsCommand(
            $repository,
            $this->webspaceManager(['de', 'en']),
            $this->translator(),
        );

        $tester = new CommandTester($command);
        $result = $tester->execute([]);

        self::assertSame(Command::SUCCESS, $result);
    }
}
