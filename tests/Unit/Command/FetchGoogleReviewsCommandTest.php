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

    public function testFailsOnApiStatusNotOk(): void
    {
        $httpClient = $this->createMock(HttpClientInterface::class);
        $repository = $this->createMock(\Depa\SuluGoogleReviewsBundle\Repository\GoogleReviewRepository::class);

        $response = $this->createMock(ResponseInterface::class);
        $response->method('toArray')->willReturn(['status' => 'REQUEST_DENIED', 'error_message' => 'API key invalid']);

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
        self::assertStringContainsString('REQUEST_DENIED', $tester->getDisplay());
    }

    public function testSucceedsWithNoReviews(): void
    {
        $httpClient = $this->createMock(HttpClientInterface::class);
        $repository = $this->createMock(\Depa\SuluGoogleReviewsBundle\Repository\GoogleReviewRepository::class);

        $response = $this->createMock(ResponseInterface::class);
        $response->method('toArray')->willReturn(['status' => 'OK', 'result' => []]);

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
        $response->method('toArray')->willReturn([
            'status' => 'OK',
            'result' => [
                'reviews' => [
                    ['author_name' => 'Bad User', 'rating' => 2, 'text' => 'Bad', 'time' => 1700000000, 'relative_time_description' => 'vor 1 Monat'],
                    ['author_name' => 'Good User', 'rating' => 3, 'text' => 'Ok',  'time' => 1700000001, 'relative_time_description' => 'vor 1 Monat'],
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
