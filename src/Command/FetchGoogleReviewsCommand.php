<?php

declare(strict_types=1);

namespace Depa\SuluGoogleReviewsBundle\Command;

use Depa\SuluGoogleReviewsBundle\Entity\GoogleReview;
use Depa\SuluGoogleReviewsBundle\Repository\GoogleReviewRepository;
use Sulu\Component\Webspace\Manager\WebspaceManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * @phpstan-type NormalizedReview array{
 *     authorName: string,
 *     rating: int,
 *     timestamp: int,
 *     text: string,
 *     textLanguage: string|null,
 *     originalText: string|null,
 *     originalLanguage: string|null,
 *     textIsOriginal: bool
 * }
 */
#[AsCommand(
    name: 'sulu:google-reviews:fetch',
    description: 'Fetches Google Places reviews (≥4 stars) per webspace locale and stores them in the database.'
)]
class FetchGoogleReviewsCommand extends Command
{
    /**
     * Places API (New, v1): returns up to 5 reviews ranked by "most relevant".
     * The endpoint offers no sort option (verified against the official reference).
     */
    private const API_URL_NEW = 'https://places.googleapis.com/v1/places';

    /**
     * Places API (Legacy): the only Google endpoint that supports reviews_sort=newest.
     * Requires the legacy "Places API" to be enabled for the project and allowed on the key.
     */
    private const API_URL_LEGACY = 'https://maps.googleapis.com/maps/api/place/details/json';

    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly GoogleReviewRepository $repository,
        private readonly WebspaceManagerInterface $webspaceManager,
        private readonly ?string $apiKey = null,
        private readonly ?string $placeId = null,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption(
            'newest',
            null,
            InputOption::VALUE_NONE,
            'Fetch the newest reviews via the Legacy Places API (reviews_sort=newest) instead of Google\'s "most relevant". '
            . 'Requires the legacy "Places API" enabled for the project and allowed on the API key.'
        );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $apiKey = (string) $this->apiKey;
        $placeId = (string) $this->placeId;

        if ('' === $apiKey || '' === $placeId) {
            $io->error('GOOGLE_PLACES_API_KEY und GOOGLE_PLACE_ID müssen in .env.local gesetzt sein.');

            return Command::FAILURE;
        }

        $newest = (bool) $input->getOption('newest');
        $io->note($newest
            ? 'Modus: neueste Bewertungen (Legacy Places API, reviews_sort=newest).'
            : 'Modus: von Google vorgeschlagene Bewertungen ("most relevant", Places API New).');

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
            $reviews = $newest
                ? $this->fetchLegacyReviews($io, $apiKey, $placeId, $locale)
                : $this->fetchNewReviews($io, $apiKey, $placeId, $locale);

            // null = Abruf fehlgeschlagen (Warnung wurde bereits ausgegeben); [] = erfolgreich, aber leer.
            if (null === $reviews) {
                continue;
            }

            $succeededLocales[] = $locale;

            foreach ($reviews as $reviewData) {
                $rating = $reviewData['rating'];
                $authorName = $reviewData['authorName'];
                $timestamp = $reviewData['timestamp'];
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

                // Separater Originaltext nur, wenn die API ihn liefert (Places API New bei übersetztem Text).
                if (null !== $reviewData['originalText']) {
                    $review->setOriginalText($reviewData['originalText']);
                    $review->setOriginalLanguage($reviewData['originalLanguage']);
                } elseif (null === $review->getOriginalText() && $reviewData['textIsOriginal']) {
                    // Angezeigter Text ist zugleich das Original (nicht übersetzt) → als Original übernehmen.
                    // Bei übersetztem Legacy-Text ist textIsOriginal=false, damit wir keine Übersetzung
                    // fälschlich als Original speichern; ein späterer Default-Lauf trägt das echte Original nach.
                    $review->setOriginalText($reviewData['text']);
                    $review->setOriginalLanguage($reviewData['textLanguage']);
                }

                $review->setTranslation($locale, $reviewData['text']);
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
                // Ein fehlgeschlagenes flush() rollt die gesamte Unit of Work zurück und
                // schließt den EntityManager — es wurde nichts gespeichert. Das passiert
                // praktisch nur bei einem parallelen Import-Lauf. Ehrlich als Fehler melden,
                // damit Cron/CI das nicht als Erfolg werten; der nächste Lauf importiert erneut.
                $io->error('Import nicht gespeichert: paralleler Lauf hat dieselben Bewertungen bereits geschrieben. Bitte erneut ausführen.');

                return Command::FAILURE;
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

    /**
     * Fetches reviews from the Places API (New, v1) — Google's "most relevant" ranking.
     *
     * @return list<NormalizedReview>|null null on failure (a warning has been emitted)
     */
    private function fetchNewReviews(SymfonyStyle $io, string $apiKey, string $placeId, string $locale): ?array
    {
        try {
            $response = $this->httpClient->request('GET', self::API_URL_NEW . '/' . \rawurlencode($placeId), [
                'headers' => [
                    'X-Goog-Api-Key'   => $apiKey,
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

            return null;
        } catch (\Exception $e) {
            $io->warning(\sprintf('Sprache "%s": Fehler beim Abrufen der API-Antwort: %s', $locale, $e->getMessage()));

            return null;
        }

        if (200 !== $statusCode) {
            $io->warning(\sprintf(
                'Sprache "%s": Google Places API Fehler (HTTP %d): %s',
                $locale,
                $statusCode,
                $data['error']['message'] ?? 'keine Fehlermeldung'
            ));

            return null;
        }

        $normalized = [];
        foreach ($data['reviews'] ?? [] as $review) {
            $publishTime = $review['publishTime'] ?? null;

            $normalized[] = [
                'authorName'       => $review['authorAttribution']['displayName'] ?? '',
                'rating'           => (int) ($review['rating'] ?? 0),
                'timestamp'        => null !== $publishTime ? (\strtotime($publishTime) ?: 0) : 0,
                'text'             => $review['text']['text'] ?? '',
                'textLanguage'     => $review['text']['languageCode'] ?? null,
                'originalText'     => $review['originalText']['text'] ?? null,
                'originalLanguage' => $review['originalText']['languageCode'] ?? null,
                // Ohne separaten Originaltext ist der angezeigte Text bereits das Original.
                'textIsOriginal'   => !isset($review['originalText']['text']),
            ];
        }

        return $normalized;
    }

    /**
     * Fetches reviews from the Legacy Places API with reviews_sort=newest.
     *
     * The legacy endpoint returns HTTP 200 even on logical errors; the real outcome is in
     * the JSON "status" field. The response shape differs from the v1 API (snake_case,
     * "time" as a Unix timestamp, no separate original-text field), so it is normalized here.
     *
     * @return list<NormalizedReview>|null null on failure (a warning has been emitted)
     */
    private function fetchLegacyReviews(SymfonyStyle $io, string $apiKey, string $placeId, string $locale): ?array
    {
        try {
            $response = $this->httpClient->request('GET', self::API_URL_LEGACY, [
                'query' => [
                    'place_id'     => $placeId,
                    'fields'       => 'reviews',
                    'reviews_sort' => 'newest',
                    'language'     => \str_replace('_', '-', $locale),
                    'key'          => $apiKey,
                ],
            ]);

            $statusCode = $response->getStatusCode();
            $data = $response->toArray(false);
        } catch (TransportExceptionInterface $e) {
            $io->warning(\sprintf('Sprache "%s": Legacy Places API nicht erreichbar: %s', $locale, $e->getMessage()));

            return null;
        } catch (\Exception $e) {
            $io->warning(\sprintf('Sprache "%s": Fehler beim Abrufen der Legacy-API-Antwort: %s', $locale, $e->getMessage()));

            return null;
        }

        $apiStatus = $data['status'] ?? 'UNKNOWN';

        // "ZERO_RESULTS" ist ein gültiges Ergebnis ohne Bewertungen, kein Fehler.
        if ('ZERO_RESULTS' === $apiStatus) {
            return [];
        }

        if (200 !== $statusCode || 'OK' !== $apiStatus) {
            $io->warning(\sprintf(
                'Sprache "%s": Legacy Places API Fehler (HTTP %d, %s): %s',
                $locale,
                $statusCode,
                $apiStatus,
                $data['error_message'] ?? 'keine Fehlermeldung'
            ));

            return null;
        }

        $normalized = [];
        foreach ($data['result']['reviews'] ?? [] as $review) {
            $translated = (bool) ($review['translated'] ?? false);
            $text = $review['text'] ?? '';
            $language = $review['language'] ?? null;

            $normalized[] = [
                'authorName'   => $review['author_name'] ?? '',
                'rating'       => (int) ($review['rating'] ?? 0),
                'timestamp'    => (int) ($review['time'] ?? 0),
                'text'         => $text,
                'textLanguage' => $language,
                // Die Legacy-API liefert keinen separaten Originaltext. Ist der Text übersetzt,
                // kennen wir das Original nicht → null; textIsOriginal=false verhindert, dass die
                // Übersetzung als Original gespeichert wird.
                'originalText'     => $translated ? null : $text,
                'originalLanguage' => $translated ? null : ($review['original_language'] ?? $language),
                'textIsOriginal'   => !$translated,
            ];
        }

        return $normalized;
    }
}
