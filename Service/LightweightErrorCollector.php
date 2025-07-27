<?php

namespace Beast\VisitorTrackerBundle\Service;

class LightweightErrorCollector
{
    private array $counts = [
        'notice' => 0,
        'warning' => 0,
        'deprecated' => 0,
        'error' => 0,
    ];

    public function register(): void
    {
        set_error_handler([$this, 'handle']);
        register_shutdown_function([$this, 'handleShutdown']);
    }

    public function handle(int $errno, string $errstr): bool
    {
        match (true) {
            $errno === E_DEPRECATED,
            $errno === E_USER_DEPRECATED => $this->counts['deprecated']++,

            $errno === E_NOTICE,
            $errno === E_USER_NOTICE => $this->counts['notice']++,

            $errno === E_WARNING,
            $errno === E_USER_WARNING => $this->counts['warning']++,

            default => null,
        };

        // Let Symfony continue handling the error
        return false;
    }

    public function handleShutdown(): void
    {
        $error = error_get_last();
        if ($error && in_array($error['type'], [E_ERROR, E_PARSE, E_COMPILE_ERROR, E_CORE_ERROR])) {
            $this->counts['error']++;
        }
    }

    public function getCounts(): array
    {
        return $this->counts;
    }
}
