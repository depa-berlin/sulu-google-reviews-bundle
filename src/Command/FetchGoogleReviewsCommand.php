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
    private const API_URL = 'https://maps.googleapis.com/maps/api/place/details/json';

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
            $response = $this->httpClient->request('GET', self::API_URL, [
                'query' => [
                    'place_id' => $this->placeId,
                    'fields'   => 'reviews',
                    'key'      => $this->apiKey,
                ],
            ]);

            $data = $response->toArray();
        } catch (TransportExceptionInterface $e) {
            $io->error('Google Places API nicht erreichbar: ' . $e->getMessage());

            return Command::FAILURE;
        } catch (\Exception $e) {
            $io->error('Fehler beim Abrufen der API-Antwort: ' . $e->getMessage());

            return Command::FAILURE;
        }

        $status = $data['status'] ?? 'UNKNOWN';
        if ('OK' !== $status) {
            $io->error(\sprintf(
                'Google API returned status "%s": %s',
                $status,
                $data['error_message'] ?? 'keine Fehlermeldung'
            ));

            return Command::FAILURE;
        }

        if (!isset($data['result']['reviews'])) {
            $io->warning('Keine Bewertungen in der API-Antwort gefunden.');

            return Command::SUCCESS;
        }

        $reviews = $data['result']['reviews'];
        $imported = 0;
        $skipped = 0;

        foreach ($reviews as $reviewData) {
            if (($reviewData['rating'] ?? 0) < 4) {
                ++$skipped;
                continue;
            }

            $authorName = $reviewData['author_name'] ?? '';
            $timestamp = $reviewData['time'] ?? 0;

            $existing = $this->repository->findOneBy([
                'authorName'         => $authorName,
                'createdAtTimestamp' => $timestamp,
            ]);

            if (null !== $existing) {
                ++$skipped;
                continue;
            }

            $review = new GoogleReview();
            $review->setAuthorName($authorName);
            $review->setProfilePhotoUrl($reviewData['profile_photo_url'] ?? null);
            $review->setRating((int) $reviewData['rating']);
            $review->setText($reviewData['text'] ?? '');
            $review->setCreatedAtTimestamp((int) $timestamp);
            $review->setRelativeTimeDescription($reviewData['relative_time_description'] ?? '');

            $this->repository->save($review, false);
            ++$imported;
        }

        if ($imported > 0) {
            /** @var \Doctrine\ORM\EntityManager $em */
            $em = $this->repository->getEntityManager();
            $em->flush();
        }

        $io->success(\sprintf('Importiert: %d, Übersprungen: %d', $imported, $skipped));

        return Command::SUCCESS;
    }
}
