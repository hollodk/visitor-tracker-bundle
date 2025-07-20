<?php

namespace Beast\VisitorTrackerBundle\DependencyInjection;

use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\Extension;

class BeastVisitorTrackerExtension extends Extension
{
    public function load(array $configs, ContainerBuilder $container)
    {
        $config = $this->processConfiguration(new Configuration(), $configs);

        $container->setParameter('beast_visitor_tracker.geo_enabled', $config['geo_enabled']);
        $container->setParameter('beast_visitor_tracker.ip_anonymize', $config['ip_anonymize']);
        $container->setParameter('beast_visitor_tracker.log_dir', $config['log_dir']);
    }
}
