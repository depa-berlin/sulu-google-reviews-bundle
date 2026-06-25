<?php

declare(strict_types=1);

namespace Depa\SuluGoogleReviewsBundle;

use Depa\SuluGoogleReviewsBundle\DependencyInjection\Compiler\TranslatorIntegrationPass;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\HttpKernel\Bundle\Bundle;

/**
 * Class name follows Symfony Flex's naming convention (vendor + last namespace
 * segment) so the bundle is auto-registered in config/bundles.php on
 * `composer require` without a recipe. The DI extension (DepaSuluGoogleReviewsExtension)
 * and config alias (depa_sulu_google_reviews) follow the same convention.
 */
class DepaSuluGoogleReviewsBundle extends Bundle
{
    public function build(ContainerBuilder $container): void
    {
        parent::build($container);

        $container->addCompilerPass(new TranslatorIntegrationPass());
    }

    public function getPath(): string
    {
        return \dirname(__DIR__);
    }
}
