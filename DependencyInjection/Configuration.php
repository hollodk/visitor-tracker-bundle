<?php

namespace Beast\VisitorTrackerBundle\DependencyInjection;

use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

class Configuration implements ConfigurationInterface
{
    public function getConfigTreeBuilder(): TreeBuilder
    {
        $treeBuilder = new TreeBuilder('beast_visitor_tracker');

        $treeBuilder->getRootNode()
            ->children()
                ->booleanNode('geo_enabled')->defaultTrue()->end()
                ->booleanNode('ip_anonymize')->defaultFalse()->end()
                ->scalarNode('log_dir')->defaultValue('%kernel.project_dir%/var/visitor_tracker/logs')->end()
            ->end();

        return $treeBuilder;
    }
}
