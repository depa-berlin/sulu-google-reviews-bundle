<?php

declare(strict_types=1);

namespace Depa\SuluGoogleReviewsBundle\Tests\Unit\Translation;

use Depa\SuluGoogleReviewsBundle\Translation\DeeplReviewTranslator;
use PHPUnit\Framework\TestCase;

class DeeplReviewTranslatorTest extends TestCase
{
    private function client(): object
    {
        return new class() {
            /** @var list<array{text: string, source: ?string, target: string}> */
            public array $calls = [];

            public function translateText(string $text, ?string $source, string $target, ?array $options = []): object
            {
                $this->calls[] = ['text' => $text, 'source' => $source, 'target' => $target];

                return (object) ['text' => $target . ':' . $text];
            }
        };
    }

    public function testReturnsTranslatedText(): void
    {
        $client = $this->client();
        $translator = new DeeplReviewTranslator($client);

        self::assertSame('DE:Hello', $translator->translate('Hello', 'de'));
    }

    public function testMapsEnglishToRegionalDeeplTarget(): void
    {
        $client = $this->client();
        $translator = new DeeplReviewTranslator($client);

        $translator->translate('Hallo', 'en');

        self::assertSame('EN-GB', $client->calls[0]['target']);
    }

    public function testUppercasesUnmappedLocale(): void
    {
        $client = $this->client();
        $translator = new DeeplReviewTranslator($client);

        $translator->translate('Hallo', 'fr');

        self::assertSame('FR', $client->calls[0]['target']);
    }

    public function testRegionLocaleFallsBackToBaseLanguageTarget(): void
    {
        $client = $this->client();
        $translator = new DeeplReviewTranslator($client);

        // de_at hat bei DeepL keinen Regionalcode -> Basis-Sprache DE (nicht "DE_AT")
        $translator->translate('Hallo', 'de_at');

        self::assertSame('DE', $client->calls[0]['target']);
    }

    public function testRegionLocaleUsesSpecificDeeplTargetWhenMapped(): void
    {
        $client = $this->client();
        $translator = new DeeplReviewTranslator($client);

        $translator->translate('Hallo', 'pt_br');

        self::assertSame('PT-BR', $client->calls[0]['target']);
    }

    public function testForwardsSourceLocaleAsBaseLanguage(): void
    {
        $client = $this->client();
        $translator = new DeeplReviewTranslator($client);

        $translator->translate('Bonjour', 'de', 'fr_FR');

        self::assertSame('FR', $client->calls[0]['source']);
    }

    public function testNullSourceWhenNotProvided(): void
    {
        $client = $this->client();
        $translator = new DeeplReviewTranslator($client);

        $translator->translate('Hallo', 'de');

        self::assertNull($client->calls[0]['source']);
    }
}
