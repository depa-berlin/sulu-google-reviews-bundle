<?php

declare(strict_types=1);

namespace Depa\SuluGoogleReviewsBundle\DependencyInjection;

use Depa\SuluGoogleReviewsBundle\Command\FetchGoogleReviewsCommand;
use Depa\SuluGoogleReviewsBundle\Entity\GoogleReview;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\Extension;
use Symfony\Component\DependencyInjection\Extension\PrependExtensionInterface;
use Symfony\Component\DependencyInjection\Loader\YamlFileLoader;

class DepaGoogleReviewsExtension extends Extension implements PrependExtensionInterface
{
    public function load(array $configs, ContainerBuilder $container): void
    {
        $loader = new YamlFileLoader($container, new FileLocator(__DIR__ . '/../../Resources/config'));
        $loader->load('services.yaml');

        $config = $this->processConfiguration(new Configuration(), $configs);

        $command = $container->getDefinition(FetchGoogleReviewsCommand::class);
        $command->setArgument('$apiKey', $config['api_key']);
        $command->setArgument('$placeId', $config['place_id']);
    }

    public function prepend(ContainerBuilder $container): void
    {
        if ($container->hasExtension('sulu_admin')) {
            $container->prependExtensionConfig('sulu_admin', [
                'lists' => [
                    'directories' => [__DIR__ . '/../../Resources/config/lists'],
                ],
                'forms' => [
                    'directories' => [__DIR__ . '/../../Resources/config/forms'],
                ],
                'resources' => [
                    GoogleReview::RESOURCE_KEY => [
                        'routes' => [
                            'list'   => 'depa.google_reviews.cget',
                            'detail' => 'depa.google_reviews.get',
                        ],
                    ],
                ],
            ]);
        }

        if ($container->hasExtension('twig')) {
            $container->prependExtensionConfig('twig', [
                'paths' => [
                    __DIR__ . '/../../Resources/views' => null,
                ],
            ]);
        }

        if ($container->hasExtension('doctrine')) {
            $container->prependExtensionConfig('doctrine', [
                'orm' => [
                    'mappings' => [
                        'DepaGoogleReviewsBundle' => [
                            'type'   => 'attribute',
                            'dir'    => __DIR__ . '/../Entity',
                            'prefix' => 'Depa\\SuluGoogleReviewsBundle\\Entity',
                            'alias'  => 'DepaGoogleReviewsBundle',
                        ],
                    ],
                ],
            ]);
        }
    }
}
