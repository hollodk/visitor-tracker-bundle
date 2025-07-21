<?php

namespace Beast\VisitorTrackerBundle\Service;

class VisitorLogConfig
{
    public function __construct(
        private readonly string $logDir,
    ) {}

    public function getLogDir(): string
    {
        return rtrim($this->logDir, '/');
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

