<?php

namespace Beast\VisitorTrackerBundle\Service;

class VisitorLogBuffer
{
    private array $entries = [];

    public function addEntry(array $entry): void
    {
        $this->entries[] = $entry;
    }

    public function flush(string $logFile): void
    {
        if (empty($this->entries)) return;

        @mkdir(dirname($logFile), 0777, true);
        $handle = fopen($logFile, 'a');
        if (!$handle) return;

        foreach ($this->entries as $entry) {
            fwrite($handle, json_encode($entry) . PHP_EOL);
        }

        fclose($handle);
        $this->entries = [];
    }

    public function updateLastEntry(callable $modifier): void
    {
        if (empty($this->entries)) return;

        $lastIndex = array_key_last($this->entries);
        $this->entries[$lastIndex] = $modifier($this->entries[$lastIndex]) ?? $this->entries[$lastIndex];
    }

    public function getLastEntry(): ?array
    {
        return end($this->entries) ?: null;
    }
}

