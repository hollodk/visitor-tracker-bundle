<?php

namespace Beast\VisitorTrackerBundle\DependencyInjection;

use Beast\VisitorTrackerBundle\Service\VisitorSettings;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Extension\Extension;
use Symfony\Component\DependencyInjection\Loader\YamlFileLoader;

class BeastVisitorTrackerExtension extends Extension
{
    public function load(array $configs, ContainerBuilder $container): void
    {
        // Load configuration definition
        $configuration = new Configuration();
        $config = $this->processConfiguration($configuration, $configs);

        // Optionally load services.yaml (if you have one)
        $loader = new YamlFileLoader($container, new FileLocator(__DIR__ . '/../Resources/config'));
        try {
            $loader->load('services.yaml');
        } catch (\Throwable) {
            // ignore if file doesn't exist
        }

        // Register parameters if you still want them globally
        $container->setParameter('beast_visitor_tracker.geo_enabled', $config['geo_enabled']);
        $container->setParameter('beast_visitor_tracker.session_enabled', $config['session_enabled']);
        $container->setParameter('beast_visitor_tracker.ip_anonymize', $config['ip_anonymize']);
        $container->setParameter('beast_visitor_tracker.log_dir', $config['log_dir']);

        // Register VisitorSettings service
        $definition = new Definition(VisitorSettings::class);
        $definition->setArgument(0, $config['geo_enabled']);
        $definition->setArgument(1, $config['session_enabled']);
        $definition->setArgument(2, $config['ip_anonymize']);
        $definition->setArgument(3, $config['log_dir']);

        $definition->setPublic(true); // optional
        $container->setDefinition(VisitorSettings::class, $definition);
    }
}
