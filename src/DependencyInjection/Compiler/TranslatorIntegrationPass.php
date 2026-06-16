<?php

declare(strict_types=1);

namespace Depa\SuluGoogleReviewsBundle\DependencyInjection\Compiler;

use Depa\SuluGoogleReviewsBundle\Command\TranslateMissingReviewsCommand;
use Depa\SuluGoogleReviewsBundle\Translation\DeeplReviewTranslator;
use Depa\SuluGoogleReviewsBundle\Translation\ReviewTranslatorInterface;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Reference;

/**
 * Wires the DeepL adapter to ReviewTranslatorInterface when
 * robole/sulu-ai-translator-bundle is installed — without this bundle
 * depending on that package. A project may bind its own translator
 * implementation, which takes precedence.
 */
class TranslatorIntegrationPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container): void
    {
        // Respect a translator already provided by the project.
        if ($container->has(ReviewTranslatorInterface::class)) {
            return;
        }

        if (!$container->has('ai_translator.deepl_service')) {
            return;
        }

        $definition = new Definition(DeeplReviewTranslator::class, [new Reference('ai_translator.deepl_service')]);
        $definition->setPublic(false);
        $container->setDefinition(DeeplReviewTranslator::class, $definition);
        $container->setAlias(ReviewTranslatorInterface::class, DeeplReviewTranslator::class);

        if ($container->hasDefinition(TranslateMissingReviewsCommand::class)) {
            $container->getDefinition(TranslateMissingReviewsCommand::class)
                ->setArgument('$translator', new Reference(ReviewTranslatorInterface::class));
        }
    }
}
