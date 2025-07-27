<?php

namespace Beast\VisitorTrackerBundle\Service;

class VisitorLogMaintenance
{
    public function __construct(private VisitorLogConfig $config) {}

    public function findOldLogFiles(int $days): array
    {
        $logDir = $this->config->getLogDir();
        if (!is_dir($logDir)) {
            return [];
        }

        $threshold = (new \DateTimeImmutable())->modify("-{$days} days");
        $files = glob($logDir . '/*.log');
        $candidates = [];

        foreach ($files as $file) {
            $basename = basename($file);
            if (preg_match('/^(\\d{4}-\\d{2}-\\d{2})\\.log$/', $basename, $m)) {
                $fileDate = \DateTimeImmutable::createFromFormat('Y-m-d', $m[1]);
                if ($fileDate && $fileDate < $threshold) {
                    $candidates[] = $file;
                }
            }
        }

        return $candidates;
    }

    public function deleteFiles(array $files): array
    {
        $deleted = [];
        $failed = [];

        foreach ($files as $file) {
            if (@unlink($file)) {
                $deleted[] = $file;
            } else {
                $failed[] = $file;
            }
        }

        return ['deleted' => $deleted, 'failed' => $failed];
    }

    public function getTotalSize(array $files): int
    {
        return array_sum(array_map('filesize', $files));
    }
}

