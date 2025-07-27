<?php

namespace Beast\VisitorTrackerBundle;

use Beast\VisitorTrackerBundle\DependencyInjection\BeastVisitorTrackerExtension;
use Symfony\Component\HttpKernel\Bundle\Bundle;

class BeastVisitorTrackerBundle extends Bundle
{
    public function getContainerExtension(): ?\Symfony\Component\DependencyInjection\Extension\ExtensionInterface
    {
        return new BeastVisitorTrackerExtension();
    }
}
