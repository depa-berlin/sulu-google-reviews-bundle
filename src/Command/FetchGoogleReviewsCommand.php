<?php

declare(strict_types=1);

namespace Depa\SuluGoogleReviewsBundle\Command;

use Depa\SuluGoogleReviewsBundle\Entity\GoogleReview;
use Depa\SuluGoogleReviewsBundle\Repository\GoogleReviewRepository;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

#[AsCommand(
    name: 'sulu:google-reviews:fetch',
    description: 'Fetches Google Places reviews (≥4 stars) and stores new ones in the database.'
)]
class FetchGoogleReviewsCommand extends Command
{
    private const API_URL = 'https://places.googleapis.com/v1/places';

    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly GoogleReviewRepository $repository,
        private readonly string $apiKey,
        private readonly string $placeId,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        if ('' === $this->apiKey || '' === $this->placeId) {
            $io->error('GOOGLE_PLACES_API_KEY und GOOGLE_PLACE_ID müssen in .env.local gesetzt sein.');

            return Command::FAILURE;
        }

        try {
            $response = $this->httpClient->request('GET', self::API_URL . '/' . \rawurlencode($this->placeId), [
                'headers' => [
                    'X-Goog-Api-Key'   => $this->apiKey,
                    'X-Goog-FieldMask' => 'reviews',
                ],
                'query' => [
                    'languageCode' => 'de',
                ],
            ]);

            $statusCode = $response->getStatusCode();
            $data = $response->toArray(false);
        } catch (TransportExceptionInterface $e) {
            $io->error('Google Places API nicht erreichbar: ' . $e->getMessage());

            return Command::FAILURE;
        } catch (\Exception $e) {
            $io->error('Fehler beim Abrufen der API-Antwort: ' . $e->getMessage());

            return Command::FAILURE;
        }

        if (200 !== $statusCode) {
            $io->error(\sprintf(
                'Google Places API Fehler (HTTP %d): %s',
                $statusCode,
                $data['error']['message'] ?? 'keine Fehlermeldung'
            ));

            return Command::FAILURE;
        }

        if (empty($data['reviews'])) {
            $io->warning('Keine Bewertungen in der API-Antwort gefunden.');

            return Command::SUCCESS;
        }

        $reviews = $data['reviews'];
        $imported = 0;
        $updated = 0;
        $skipped = 0;

        foreach ($reviews as $reviewData) {
            $rating = (int) ($reviewData['rating'] ?? 0);
            if ($rating < 4) {
                ++$skipped;
                continue;
            }

            $authorName = $reviewData['authorAttribution']['displayName'] ?? '';
            $publishTime = $reviewData['publishTime'] ?? null;
            $timestamp = null !== $publishTime ? (\strtotime($publishTime) ?: 0) : 0;

            $existing = $this->repository->findOneBy([
                'authorName'         => $authorName,
                'createdAtTimestamp' => $timestamp,
            ]);

            $review = $existing ?? new GoogleReview();
            $review->setAuthorName($authorName);
            $review->setProfilePhotoUrl($reviewData['authorAttribution']['photoUri'] ?? null);
            $review->setRating($rating);
            $review->setText($reviewData['text']['text'] ?? '');
            $review->setCreatedAtTimestamp($timestamp);
            $review->setRelativeTimeDescription($reviewData['relativePublishTimeDescription'] ?? '');

            $this->repository->save($review, false);

            if (null === $existing) {
                ++$imported;
            } else {
                ++$updated;
            }
        }

        if ($imported > 0 || $updated > 0) {
            try {
                $this->repository->getEntityManager()->flush();
            } catch (\Doctrine\DBAL\Exception\UniqueConstraintViolationException) {
                $io->warning('Einige Bewertungen wurden durch einen parallelen Import-Lauf bereits gespeichert und übersprungen.');
            }
        }

        $io->success(\sprintf('Importiert: %d, Aktualisiert: %d, Übersprungen: %d', $imported, $updated, $skipped));

        return Command::SUCCESS;
    }
}
