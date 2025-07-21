<?php

namespace Beast\VisitorTrackerBundle\Service;

use Symfony\Component\DependencyInjection\Attribute\Autowire;

class VisitorSettings
{
    public function __construct(
        #[Autowire('%beast_visitor_tracker.geo_enabled%')] private readonly bool $geoEnabled,
        #[Autowire('%beast_visitor_tracker.session_enabled%')] private readonly bool $sessionEnabled,
        #[Autowire('%beast_visitor_tracker.ip_anonymize%')] private readonly bool $ipAnonymize,
        #[Autowire('%beast_visitor_tracker.log_dir%')] private readonly string $logDir,
    ) {}

    public function isGeoEnabled(): bool
    {
        return $this->geoEnabled;
    }

    public function isSessionEnabled(): bool
    {
        return $this->sessionEnabled;
    }

    public function isIpAnonymize(): bool
    {
        return $this->ipAnonymize;
    }

    public function getLogDir(): string
    {
        return $this->logDir;
    }
}

