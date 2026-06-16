<?php

declare(strict_types=1);

namespace Depa\SuluGoogleReviewsBundle\Tests\Unit\Command;

use Depa\SuluGoogleReviewsBundle\Command\FetchGoogleReviewsCommand;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

class FetchGoogleReviewsCommandTest extends TestCase
{
    public function testFailsWithEmptyApiKey(): void
    {
        $httpClient = $this->createMock(HttpClientInterface::class);
        $repository = $this->createMock(\Depa\SuluGoogleReviewsBundle\Repository\GoogleReviewRepository::class);

        $command = new FetchGoogleReviewsCommand(
            $httpClient,
            $repository,
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
        $httpClient = $this->createMock(HttpClientInterface::class);
        $repository = $this->createMock(\Depa\SuluGoogleReviewsBundle\Repository\GoogleReviewRepository::class);

        $command = new FetchGoogleReviewsCommand(
            $httpClient,
            $repository,
            'test-api-key',
            '',
        );

        $tester = new CommandTester($command);
        $result = $tester->execute([]);

        self::assertSame(Command::FAILURE, $result);
    }

    public function testFailsOnApiError(): void
    {
        $httpClient = $this->createMock(HttpClientInterface::class);
        $repository = $this->createMock(\Depa\SuluGoogleReviewsBundle\Repository\GoogleReviewRepository::class);

        $response = $this->createMock(ResponseInterface::class);
        $response->method('getStatusCode')->willReturn(403);
        $response->method('toArray')->willReturn(['error' => ['message' => 'API key invalid', 'status' => 'PERMISSION_DENIED']]);

        $httpClient->method('request')->willReturn($response);

        $command = new FetchGoogleReviewsCommand(
            $httpClient,
            $repository,
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
        $httpClient = $this->createMock(HttpClientInterface::class);
        $repository = $this->createMock(\Depa\SuluGoogleReviewsBundle\Repository\GoogleReviewRepository::class);

        $response = $this->createMock(ResponseInterface::class);
        $response->method('getStatusCode')->willReturn(200);
        $response->method('toArray')->willReturn([]);

        $httpClient->method('request')->willReturn($response);

        $command = new FetchGoogleReviewsCommand(
            $httpClient,
            $repository,
            'test-api-key',
            'test-place-id',
        );

        $tester = new CommandTester($command);
        $result = $tester->execute([]);

        self::assertSame(Command::SUCCESS, $result);
    }

    public function testSkipsReviewsBelowFourStars(): void
    {
        $httpClient = $this->createMock(HttpClientInterface::class);
        $repository = $this->createMock(\Depa\SuluGoogleReviewsBundle\Repository\GoogleReviewRepository::class);

        $response = $this->createMock(ResponseInterface::class);
        $response->method('getStatusCode')->willReturn(200);
        $response->method('toArray')->willReturn([
            'reviews' => [
                [
                    'authorAttribution' => ['displayName' => 'Bad User'],
                    'rating' => 2,
                    'text' => ['text' => 'Bad'],
                    'publishTime' => '2023-11-14T22:13:20Z',
                    'relativePublishTimeDescription' => 'vor 1 Monat',
                ],
                [
                    'authorAttribution' => ['displayName' => 'Good User'],
                    'rating' => 3,
                    'text' => ['text' => 'Ok'],
                    'publishTime' => '2023-11-14T22:13:21Z',
                    'relativePublishTimeDescription' => 'vor 1 Monat',
                ],
            ],
        ]);

        $httpClient->method('request')->willReturn($response);
        $repository->expects(self::never())->method('save');

        $command = new FetchGoogleReviewsCommand(
            $httpClient,
            $repository,
            'test-api-key',
            'test-place-id',
        );

        $tester = new CommandTester($command);
        $result = $tester->execute([]);

        self::assertSame(Command::SUCCESS, $result);
        self::assertStringContainsString('Importiert: 0', $tester->getDisplay());
        self::assertStringContainsString('Übersprungen: 2', $tester->getDisplay());
    }
}
