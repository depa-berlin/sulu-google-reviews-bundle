<?php

declare(strict_types=1);

namespace Depa\SuluGoogleReviewsBundle\Command;

use Depa\SuluGoogleReviewsBundle\Repository\GoogleReviewRepository;
use Depa\SuluGoogleReviewsBundle\Translation\ReviewTranslatorInterface;
use Sulu\Component\Webspace\Manager\WebspaceManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'sulu:google-reviews:translate-missing',
    description: 'Fills missing per-locale translations of stored reviews using the configured translator.'
)]
class TranslateMissingReviewsCommand extends Command
{
    private const MAX_CONSECUTIVE_FAILURES = 10;

    public function __construct(
        private readonly GoogleReviewRepository $repository,
        private readonly WebspaceManagerInterface $webspaceManager,
        private readonly ?ReviewTranslatorInterface $translator = null,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        if (null === $this->translator) {
            $io->error(
                'Kein Übersetzungsdienst konfiguriert. Bitte robole/sulu-ai-translator-bundle installieren '
                . 'und Depa\\SuluGoogleReviewsBundle\\Translation\\ReviewTranslatorInterface an einen Adapter binden.'
            );

            return Command::FAILURE;
        }

        $locales = array_values(array_unique($this->webspaceManager->getAllLocales()));
        if ([] === $locales) {
            $locales = ['de'];
        }

        $reviews = $this->repository->findAll();
        $translated = 0;
        $reviewsTouched = 0;
        $consecutiveFailures = 0;
        $aborted = false;

        foreach ($reviews as $review) {
            $source = $review->getOriginalText();
            if (null === $source || '' === $source) {
                continue;
            }

            $existing = $review->getTranslations();
            $changed = false;

            foreach ($locales as $locale) {
                if (isset($existing[$locale]['text']) && '' !== $existing[$locale]['text']) {
                    continue;
                }

                try {
                    $text = $this->translator->translate($source, $locale, $review->getOriginalLanguage());
                    $consecutiveFailures = 0;
                } catch (\Throwable $e) {
                    $io->warning(\sprintf('Bewertung #%s / %s: %s', (string) $review->getId(), $locale, $e->getMessage()));

                    // Bei einer Fehlerserie (z. B. Kontingent erschöpft, Rate-Limit) abbrechen,
                    // statt den Übersetzungsdienst pro Item weiter zu hämmern.
                    if (++$consecutiveFailures >= self::MAX_CONSECUTIVE_FAILURES) {
                        $aborted = true;
                        break 2;
                    }

                    continue;
                }

                $review->setTranslation($locale, $text, '');
                $changed = true;
                ++$translated;
            }

            // Inkrementell speichern, damit Fortschritt einen späteren Abbruch/Timeout übersteht.
            if ($changed) {
                $this->repository->save($review, false);
                $this->repository->flush();
                ++$reviewsTouched;
            }
        }

        if ($aborted) {
            $io->error(\sprintf(
                'Abbruch nach %d aufeinanderfolgenden Übersetzungsfehlern. Bereits ergänzt: %d in %d Bewertungen.',
                self::MAX_CONSECUTIVE_FAILURES,
                $translated,
                $reviewsTouched
            ));

            return Command::FAILURE;
        }

        $io->success(\sprintf(
            'Sprachen: %s | %d Übersetzungen ergänzt in %d Bewertungen.',
            \implode(', ', $locales),
            $translated,
            $reviewsTouched
        ));

        return Command::SUCCESS;
    }
}
