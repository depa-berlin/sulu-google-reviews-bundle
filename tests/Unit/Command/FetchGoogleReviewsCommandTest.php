<?php

declare(strict_types=1);

namespace Depa\SuluGoogleReviewsBundle\Tests\Unit\Command;

use Depa\SuluGoogleReviewsBundle\Command\FetchGoogleReviewsCommand;
use Depa\SuluGoogleReviewsBundle\Repository\GoogleReviewRepository;
use PHPUnit\Framework\TestCase;
use Sulu\Component\Webspace\Manager\WebspaceManagerInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

class FetchGoogleReviewsCommandTest extends TestCase
{
    /**
     * @param list<string> $locales
     */
    private function webspaceManager(array $locales = ['de']): WebspaceManagerInterface
    {
        $manager = $this->createMock(WebspaceManagerInterface::class);
        $manager->method('getAllLocales')->willReturn($locales);

        return $manager;
    }

    public function testFailsWithEmptyApiKey(): void
    {
        $command = new FetchGoogleReviewsCommand(
            $this->createMock(HttpClientInterface::class),
            $this->createMock(GoogleReviewRepository::class),
            $this->webspaceManager(),
            '',
            'test-place-id',
        );

        $tester = new CommandTester($command);
        $result = $tester->execute([]);

        self::assertSame(Command::FAILURE, $result);
        self::assertStringContainsString('GOOGLE_PLACES_API_KEY', $tester->getDisplay());
    }

    public function testFailsWithEmptyPlaceId(): void
    {
        $command = new FetchGoogleReviewsCommand(
            $this->createMock(HttpClientInterface::class),
            $this->createMock(GoogleReviewRepository::class),
            $this->webspaceManager(),
            'test-api-key',
            '',
        );

        $tester = new CommandTester($command);
        $result = $tester->execute([]);

        self::assertSame(Command::FAILURE, $result);
    }

    public function testFailsWhenNoLocaleSucceeds(): void
    {
        $response = $this->createMock(ResponseInterface::class);
        $response->method('getStatusCode')->willReturn(403);
        $response->method('toArray')->willReturn(['error' => ['message' => 'API key invalid', 'status' => 'PERMISSION_DENIED']]);

        $httpClient = $this->createMock(HttpClientInterface::class);
        $httpClient->method('request')->willReturn($response);

        $command = new FetchGoogleReviewsCommand(
            $httpClient,
            $this->createMock(GoogleReviewRepository::class),
            $this->webspaceManager(),
            'test-api-key',
            'test-place-id',
        );

        $tester = new CommandTester($command);
        $result = $tester->execute([]);

        self::assertSame(Command::FAILURE, $result);
        self::assertStringContainsString('API key invalid', $tester->getDisplay());
    }

    public function testSucceedsWithNoReviews(): void
    {
        $response = $this->createMock(ResponseInterface::class);
        $response->method('getStatusCode')->willReturn(200);
        $response->method('toArray')->willReturn([]);

        $httpClient = $this->createMock(HttpClientInterface::class);
        $httpClient->method('request')->willReturn($response);

        $command = new FetchGoogleReviewsCommand(
            $httpClient,
            $this->createMock(GoogleReviewRepository::class),
            $this->webspaceManager(),
            'test-api-key',
            'test-place-id',
        );

        $tester = new CommandTester($command);
        $result = $tester->execute([]);

        self::assertSame(Command::SUCCESS, $result);
    }

    public function testSkipsReviewsBelowFourStars(): void
    {
        $response = $this->createMock(ResponseInterface::class);
        $response->method('getStatusCode')->willReturn(200);
        $response->method('toArray')->willReturn([
            'reviews' => [
                [
                    'authorAttribution' => ['displayName' => 'Bad User'],
                    'rating' => 2,
                    'text' => ['text' => 'Bad', 'languageCode' => 'de'],
                    'publishTime' => '2023-11-14T22:13:20Z',
                    'relativePublishTimeDescription' => 'vor 1 Monat',
                ],
                [
                    'authorAttribution' => ['displayName' => 'Good User'],
                    'rating' => 3,
                    'text' => ['text' => 'Ok', 'languageCode' => 'de'],
                    'publishTime' => '2023-11-14T22:13:21Z',
                    'relativePublishTimeDescription' => 'vor 1 Monat',
                ],
            ],
        ]);

        $httpClient = $this->createMock(HttpClientInterface::class);
        $httpClient->method('request')->willReturn($response);

        $repository = $this->createMock(GoogleReviewRepository::class);
        $repository->expects(self::never())->method('save');

        $command = new FetchGoogleReviewsCommand(
            $httpClient,
            $repository,
            $this->webspaceManager(),
            'test-api-key',
            'test-place-id',
        );

        $tester = new CommandTester($command);
        $result = $tester->execute([]);

        self::assertSame(Command::SUCCESS, $result);
        self::assertStringContainsString('Neu: 0', $tester->getDisplay());
        self::assertStringContainsString('Übersprungen: 2', $tester->getDisplay());
    }

    public function testImportsReviewAcrossMultipleLocalesAsSingleEntity(): void
    {
        $deResponse = $this->createMock(ResponseInterface::class);
        $deResponse->method('getStatusCode')->willReturn(200);
        $deResponse->method('toArray')->willReturn([
            'reviews' => [
                [
                    'authorAttribution' => ['displayName' => 'Jean Dupont', 'photoUri' => 'https://example.com/p.jpg'],
                    'rating' => 5,
                    'text' => ['text' => 'Tolles Geschäft', 'languageCode' => 'de'],
                    'originalText' => ['text' => 'Super magasin', 'languageCode' => 'fr'],
                    'publishTime' => '2024-01-15T10:30:00Z',
                    'relativePublishTimeDescription' => 'vor 2 Monaten',
                ],
            ],
        ]);

        $enResponse = $this->createMock(ResponseInterface::class);
        $enResponse->method('getStatusCode')->willReturn(200);
        $enResponse->method('toArray')->willReturn([
            'reviews' => [
                [
                    'authorAttribution' => ['displayName' => 'Jean Dupont', 'photoUri' => 'https://example.com/p.jpg'],
                    'rating' => 5,
                    'text' => ['text' => 'Great shop', 'languageCode' => 'en'],
                    'originalText' => ['text' => 'Super magasin', 'languageCode' => 'fr'],
                    'publishTime' => '2024-01-15T10:30:00Z',
                    'relativePublishTimeDescription' => '2 months ago',
                ],
            ],
        ]);

        $httpClient = $this->createMock(HttpClientInterface::class);
        $httpClient->method('request')->willReturnOnConsecutiveCalls($deResponse, $enResponse);

        $repository = $this->createMock(GoogleReviewRepository::class);
        // Beide Locales betreffen dieselbe Bewertung -> findOneBy nur beim ersten Mal, danach aus dem Run-Cache
        $repository->method('findOneBy')->willReturn(null);
        $repository->expects(self::once())->method('save');
        $repository->expects(self::once())->method('flush');

        $command = new FetchGoogleReviewsCommand(
            $httpClient,
            $repository,
            $this->webspaceManager(['de', 'en']),
            'test-api-key',
            'test-place-id',
        );

        $tester = new CommandTester($command);
        $result = $tester->execute([]);

        self::assertSame(Command::SUCCESS, $result);
        self::assertStringContainsString('Neu: 1', $tester->getDisplay());
        self::assertStringContainsString('de, en', $tester->getDisplay());
    }
}
