<?php

declare(strict_types=1);

namespace Depa\SuluGoogleReviewsBundle\Translation;

/**
 * Optional translation service for backfilling missing review translations.
 *
 * An implementation is provided by the consuming application (e.g. wrapping
 * robole/sulu-ai-translator-bundle's DeeplService). If no implementation is
 * bound, the backfill command degrades gracefully.
 */
interface ReviewTranslatorInterface
{
    /**
     * Translates $text into the given Sulu locale.
     *
     * @param string      $targetLocale Sulu locale, e.g. "de", "en", "fr"
     * @param string|null $sourceLocale BCP-47 source language or null for auto-detection
     */
    public function translate(string $text, string $targetLocale, ?string $sourceLocale = null): string;
}
