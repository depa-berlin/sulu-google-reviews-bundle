<?php

declare(strict_types=1);

namespace Depa\SuluGoogleReviewsBundle\Command;

use Depa\SuluGoogleReviewsBundle\Entity\GoogleReview;
use Depa\SuluGoogleReviewsBundle\Repository\GoogleReviewRepository;
use Sulu\Component\Webspace\Manager\WebspaceManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

#[AsCommand(
    name: 'sulu:google-reviews:fetch',
    description: 'Fetches Google Places reviews (≥4 stars) per webspace locale and stores them in the database.'
)]
class FetchGoogleReviewsCommand extends Command
{
    private const API_URL = 'https://places.googleapis.com/v1/places';

    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly GoogleReviewRepository $repository,
        private readonly WebspaceManagerInterface $webspaceManager,
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

        $locales = array_values(array_unique($this->webspaceManager->getAllLocales()));
        if ([] === $locales) {
            $locales = ['de'];
        }

        /** @var array<string, GoogleReview> $seen distinct review (author|timestamp) within this run */
        $seen = [];
        /** @var array<string, true> $skippedKeys */
        $skippedKeys = [];
        $imported = 0;
        $updated = 0;
        $skipped = 0;
        $succeededLocales = [];

        foreach ($locales as $locale) {
            try {
                $response = $this->httpClient->request('GET', self::API_URL . '/' . \rawurlencode($this->placeId), [
                    'headers' => [
                        'X-Goog-Api-Key'   => $this->apiKey,
                        'X-Goog-FieldMask' => 'reviews',
                    ],
                    'query' => [
                        // Sulu-Locale (z. B. "de_at") in BCP-47 (z. B. "de-AT") umwandeln
                        'languageCode' => \str_replace('_', '-', $locale),
                    ],
                ]);

                $statusCode = $response->getStatusCode();
                $data = $response->toArray(false);
            } catch (TransportExceptionInterface $e) {
                $io->warning(\sprintf('Sprache "%s": Google Places API nicht erreichbar: %s', $locale, $e->getMessage()));
                continue;
            } catch (\Exception $e) {
                $io->warning(\sprintf('Sprache "%s": Fehler beim Abrufen der API-Antwort: %s', $locale, $e->getMessage()));
                continue;
            }

            if (200 !== $statusCode) {
                $io->warning(\sprintf(
                    'Sprache "%s": Google Places API Fehler (HTTP %d): %s',
                    $locale,
                    $statusCode,
                    $data['error']['message'] ?? 'keine Fehlermeldung'
                ));
                continue;
            }

            $succeededLocales[] = $locale;

            foreach ($data['reviews'] ?? [] as $reviewData) {
                $rating = (int) ($reviewData['rating'] ?? 0);
                $authorName = $reviewData['authorAttribution']['displayName'] ?? '';
                $publishTime = $reviewData['publishTime'] ?? null;
                $timestamp = null !== $publishTime ? (\strtotime($publishTime) ?: 0) : 0;
                $key = $authorName . '|' . $timestamp;

                if ($rating < 4) {
                    if (!isset($skippedKeys[$key])) {
                        $skippedKeys[$key] = true;
                        ++$skipped;
                    }
                    continue;
                }

                if (isset($seen[$key])) {
                    $review = $seen[$key];
                } else {
                    $existing = $this->repository->findOneBy([
                        'authorName'         => $authorName,
                        'createdAtTimestamp' => $timestamp,
                    ]);

                    $review = $existing ?? new GoogleReview();
                    $review->setAuthorName($authorName);
                    $review->setProfilePhotoUrl($reviewData['authorAttribution']['photoUri'] ?? null);
                    $review->setRating($rating);
                    $review->setCreatedAtTimestamp($timestamp);

                    $this->repository->save($review, false);
                    $seen[$key] = $review;

                    if (null === $existing) {
                        ++$imported;
                    } else {
                        ++$updated;
                    }
                }

                // Originaltext: liefert die API nur, wenn der angezeigte Text übersetzt wurde.
                $original = $reviewData['originalText'] ?? null;
                if (null !== $original && isset($original['text'])) {
                    $review->setOriginalText($original['text']);
                    $review->setOriginalLanguage($original['languageCode'] ?? null);
                } elseif (null === $review->getOriginalText()) {
                    // Kein separater Originaltext → Text war bereits in Originalsprache
                    $review->setOriginalText($reviewData['text']['text'] ?? '');
                    $review->setOriginalLanguage($reviewData['text']['languageCode'] ?? null);
                }

                $review->setTranslation(
                    $locale,
                    $reviewData['text']['text'] ?? '',
                    $reviewData['relativePublishTimeDescription'] ?? ''
                );
            }
        }

        if ([] === $succeededLocales) {
            $io->error('Kein einziger Sprach-Abruf war erfolgreich. Es wurde nichts importiert.');

            return Command::FAILURE;
        }

        if ($imported > 0 || $updated > 0) {
            try {
                $this->repository->flush();
            } catch (\Doctrine\DBAL\Exception\UniqueConstraintViolationException) {
                $io->warning('Einige Bewertungen wurden durch einen parallelen Import-Lauf bereits gespeichert und übersprungen.');
            }
        }

        $io->success(\sprintf(
            'Sprachen: %s | Neu: %d, Aktualisiert: %d, Übersprungen: %d',
            \implode(', ', $succeededLocales),
            $imported,
            $updated,
            $skipped
        ));

        return Command::SUCCESS;
    }
}
