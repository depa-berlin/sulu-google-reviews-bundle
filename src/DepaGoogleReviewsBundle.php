<?php

declare(strict_types=1);

namespace Depa\SuluGoogleReviewsBundle;

use Depa\SuluGoogleReviewsBundle\DependencyInjection\Compiler\TranslatorIntegrationPass;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\HttpKernel\Bundle\Bundle;

class DepaGoogleReviewsBundle extends Bundle
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
