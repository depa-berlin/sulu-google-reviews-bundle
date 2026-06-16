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
     * DeepL target codes that require a region (DeepL rejects bare "EN"/"PT").
     * Keyed by normalized Sulu locale (lowercased, "_" → "-"); base language as fallback.
     */
    private const TARGET_MAP = [
        'en'    => 'EN-GB',
        'en-us' => 'EN-US',
        'pt'    => 'PT-PT',
        'pt-br' => 'PT-BR',
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
        $target = $this->toDeeplTarget($targetLocale);
        $source = null !== $sourceLocale && '' !== $sourceLocale
            ? \strtoupper($this->baseLanguage($sourceLocale))
            : null;

        /** @var object{text: string} $result */
        $result = $this->client->translateText($text, $source, $target);

        return (string) $result->text;
    }

    private function toDeeplTarget(string $locale): string
    {
        $normalized = \str_replace('_', '-', \strtolower($locale));

        if (isset(self::TARGET_MAP[$normalized])) {
            return self::TARGET_MAP[$normalized];
        }

        $base = $this->baseLanguage($locale);

        return self::TARGET_MAP[$base] ?? \strtoupper($base);
    }

    private function baseLanguage(string $locale): string
    {
        $base = \strtolower($locale);
        $base = \explode('_', $base)[0];

        return \explode('-', $base)[0];
    }
}
