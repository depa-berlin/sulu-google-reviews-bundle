<?php

declare(strict_types=1);

namespace Depa\SuluGoogleReviewsBundle\DependencyInjection;

use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

class Configuration implements ConfigurationInterface
{
    public function getConfigTreeBuilder(): TreeBuilder
    {
        $treeBuilder = new TreeBuilder('depa_sulu_google_reviews');

        $treeBuilder->getRootNode()
            ->addDefaultsIfNotSet()
            ->children()
                ->scalarNode('api_key')
                    ->info('Google Places API key. Defaults to the GOOGLE_PLACES_API_KEY env var (empty if unset).')
                    ->defaultValue('%env(default::GOOGLE_PLACES_API_KEY)%')
                ->end()
                ->scalarNode('place_id')
                    ->info('Google Place ID. Defaults to the GOOGLE_PLACE_ID env var (empty if unset).')
                    ->defaultValue('%env(default::GOOGLE_PLACE_ID)%')
                ->end()
            ->end();

        return $treeBuilder;
    }
}
