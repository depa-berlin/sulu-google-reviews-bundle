<?php

declare(strict_types=1);

namespace Depa\SuluGoogleReviewsBundle\Translation;

/**
 * Minimal duck-typed contract of robole/sulu-ai-translator-bundle's DeeplService.
 *
 * This bundle does not depend on that (optional) package; the interface only
 * describes the method shape so the adapter stays statically analysable without
 * referencing the foreign class.
 */
interface DeeplTranslatorClientInterface
{
    /**
     * @param array<string, mixed>|null $options
     */
    public function translateText(string $text, ?string $source, string $target, ?array $options = []): object;
}
