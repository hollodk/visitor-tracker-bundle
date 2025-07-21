<?php

namespace Beast\VisitorTrackerBundle\Service;

use Beast\VisitorTrackerBundle\Service\VisitorSettings;

class VisitorLogConfig
{
    public function __construct(
        private VisitorSettings $settings,
    ) {}

    public function getLogDir(): string
    {
        return rtrim($this->settings->getLogDir(), '/');
    }

    public function getLogFileForDate(\DateTimeInterface $date): string
    {
        return $this->getLogDir() . '/' . $date->format('Y-m-d') . '.log';
    }

    public function getTodayLogFile(): string
    {
        return $this->getLogFileForDate(new \DateTimeImmutable());
    }
}

