<?php

declare(strict_types=1);

namespace Depa\SuluGoogleReviewsBundle\Tests\Unit\Command;

use Depa\SuluGoogleReviewsBundle\Command\FetchGoogleReviewsCommand;
use Depa\SuluGoogleReviewsBundle\Entity\GoogleReview;
use Depa\SuluGoogleReviewsBundle\Repository\GoogleReviewRepository;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

class FetchGoogleReviewsCommandTest extends TestCase
{
    private MockObject&HttpClientInterface $httpClient;
    private MockObject&GoogleReviewRepository $repository;
    private CommandTester $tester;

    protected function setUp(): void
    {
        $this->httpClient = $this->createMock(HttpClientInterface::class);
        $this->repository = $this->createMock(GoogleReviewRepository::class);

        $command = new FetchGoogleReviewsCommand(
            $this->httpClient,
            $this->repository,
            'test-api-key',
            'test-place-id',
        );

        $this->tester = new CommandTester($command);
    }

    public function testFailsWithEmptyApiKey(): void
    {
        $command = new FetchGoogleReviewsCommand(
            $this->httpClient,
            $this->repository,
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
            $this->httpClient,
            $this->repository,
            'test-api-key',
            '',
        );

        $tester = new CommandTester($command);
        $result = $tester->execute([]);

        self::assertSame(Command::FAILURE, $result);
    }

    public function testFailsOnApiStatusNotOk(): void
    {
        $response = $this->createMock(ResponseInterface::class);
        $response->method('toArray')->willReturn(['status' => 'REQUEST_DENIED', 'error_message' => 'API key invalid']);

        $this->httpClient->method('request')->willReturn($response);

        $result = $this->tester->execute([]);

        self::assertSame(Command::FAILURE, $result);
        self::assertStringContainsString('REQUEST_DENIED', $this->tester->getDisplay());
    }

    public function testSucceedsWithNoReviews(): void
    {
        $response = $this->createMock(ResponseInterface::class);
        $response->method('toArray')->willReturn(['status' => 'OK', 'result' => []]);

        $this->httpClient->method('request')->willReturn($response);

        $result = $this->tester->execute([]);

        self::assertSame(Command::SUCCESS, $result);
    }

    public function testSkipsReviewsBelowFourStars(): void
    {
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

        $this->httpClient->method('request')->willReturn($response);
        $this->repository->expects(self::never())->method('save');

        $result = $this->tester->execute([]);

        self::assertSame(Command::SUCCESS, $result);
        self::assertStringContainsString('Importiert: 0', $this->tester->getDisplay());
        self::assertStringContainsString('Übersprungen: 2', $this->tester->getDisplay());
    }

    public function testImportsNewReviews(): void
    {
        $response = $this->createMock(ResponseInterface::class);
        $response->method('toArray')->willReturn([
            'status' => 'OK',
            'result' => [
                'reviews' => [
                    ['author_name' => 'Maria S.', 'rating' => 5, 'text' => 'Super!', 'time' => 1700000000, 'relative_time_description' => 'vor 3 Monaten'],
                ],
            ],
        ]);

        $this->httpClient->method('request')->willReturn($response);

        $this->repository->method('findOneBy')->willReturn(null);
        $this->repository->expects(self::once())->method('save')->with(self::isInstanceOf(GoogleReview::class), false);
        $this->repository->method('getEntityManager')->willReturn($this->createMock(\Doctrine\ORM\EntityManagerInterface::class));

        $result = $this->tester->execute([]);

        self::assertSame(Command::SUCCESS, $result);
        self::assertStringContainsString('Importiert: 1', $this->tester->getDisplay());
    }

    public function testSkipsDuplicateReviews(): void
    {
        $response = $this->createMock(ResponseInterface::class);
        $response->method('toArray')->willReturn([
            'status' => 'OK',
            'result' => [
                'reviews' => [
                    ['author_name' => 'Maria S.', 'rating' => 5, 'text' => 'Super!', 'time' => 1700000000, 'relative_time_description' => 'vor 3 Monaten'],
                ],
            ],
        ]);

        $this->httpClient->method('request')->willReturn($response);
        $this->repository->method('findOneBy')->willReturn(new GoogleReview());
        $this->repository->expects(self::never())->method('save');
        $this->repository->method('getEntityManager')->willReturn($this->createMock(\Doctrine\ORM\EntityManagerInterface::class));

        $result = $this->tester->execute([]);

        self::assertSame(Command::SUCCESS, $result);
        self::assertStringContainsString('Übersprungen: 1', $this->tester->getDisplay());
    }
}
