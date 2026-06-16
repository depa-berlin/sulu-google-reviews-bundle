<?php

declare(strict_types=1);

namespace Depa\SuluGoogleReviewsBundle\Translation;

/**
 * Adapter that fulfils ReviewTranslatorInterface using the DeepL client from
 * robole/sulu-ai-translator-bundle. The client is injected duck-typed (native
 * type "object") so this bundle keeps no hard dependency on that package; it is
 * only wired up by TranslatorIntegrationPass when the service is present.
 */
class DeeplReviewTranslator implements ReviewTranslatorInterface
{
    /**
     * Sulu locales that need a region-specific DeepL target code.
     */
    private const TARGET_MAP = [
        'en' => 'EN-GB',
        'pt' => 'PT-PT',
    ];

    /**
     * @param DeeplTranslatorClientInterface $client robole DeeplService (duck-typed)
     */
    public function __construct(
        private readonly object $client,
    ) {
    }

    public function translate(string $text, string $targetLocale, ?string $sourceLocale = null): string
    {
        $target = self::TARGET_MAP[$targetLocale] ?? \strtoupper($targetLocale);

        /** @var object{text: string} $result */
        $result = $this->client->translateText($text, null, $target);

        return (string) $result->text;
    }
}
