<?php

namespace Beast\VisitorTrackerBundle\Service;

use Beast\VisitorTrackerBundle\Service\VisitorSettings;

class VisitorLogFetcher
{
    public function __construct(private VisitorLogConfig $config)
    {
    }

    public function fetch(array $options): array
    {
        $logPath = $this->config->getLogDir();

        if (!file_exists($logPath)) {
            throw new \RuntimeException("Log directory not found at: $logPath");
        }

        $from = new \DateTimeImmutable($options['from'] ?? '-7 days');
        $to = new \DateTimeImmutable($options['to'] ?? 'now');

        $logLines = VisitorLogHelper::loadLogsForDateRange($logPath, $from, $to);

        return [
            'from' => $from,
            'to' => $to,
            'lines' => $logLines,
            'parsed' => VisitorLogHelper::parseLogLines($logLines),
        ];
    }

    public function fetchMemoryStats(array $options): array
    {
        $sort = $options['sort'] ?? 'avg';
        $top = (int)($options['top'] ?? 10);

        $logData = $this->fetch($options);
        $lines = $logData['lines'];

        $routes = [];

        foreach ($lines as $line) {
            $entry = json_decode($line, true);
            if (!is_array($entry) || empty($entry['memory_usage_bytes']) || empty($entry['uri'])) continue;

            $key = $entry['route'] ?? $entry['uri'];
            $mem = (int) $entry['memory_usage_bytes'];

            if (!isset($routes[$key])) {
                $routes[$key] = ['count' => 0, 'total' => 0, 'max' => 0];
            }

            $routes[$key]['count']++;
            $routes[$key]['total'] += $mem;
            $routes[$key]['max'] = max($routes[$key]['max'], $mem);
        }

        foreach ($routes as &$data) {
            $data['avg'] = $data['count'] ? round($data['total'] / $data['count']) : 0;
        }

        uasort($routes, match ($sort) {
            'count' => fn($a, $b) => $b['count'] <=> $a['count'],
            'max' => fn($a, $b) => $b['max'] <=> $a['max'],
            default => fn($a, $b) => $b['avg'] <=> $a['avg'],
        });

        return [
            'from' => $logData['from'],
            'to' => $logData['to'],
            'stats' => array_slice($routes, 0, $top, true),
        ];
    }

    public function fetchMetricStats(int $days): array
    {
        $entries = [];

        for ($i = 0; $i < $days; $i++) {
            $date = new \DateTimeImmutable("-$i days");
            $file = $this->config->getLogFileForDate($date);
            if (!file_exists($file)) continue;

            $lines = file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            foreach ($lines as $line) {
                $decoded = json_decode($line);
                if (is_object($decoded)) $entries[] = $decoded;
            }
        }

        $clients = [];
        $routes = [];
        $durations = [];
        $totalMemory = 0;
        $totalSize = 0;

        foreach ($entries as $entry) {
            $client = $entry->ip ?? 'unknown';
            $route = $entry->route ?? ($entry->uri ?? 'unknown');
            $duration = $entry->duration_ms ?? 0;
            $memory = $entry->memory_usage_bytes ?? 0;
            $size = $entry->response_size_bytes ?? 0;

            $durations[] = $duration;
            $totalMemory += $memory;
            $totalSize += $size;

            $clients[$client]['count'] = ($clients[$client]['count'] ?? 0) + 1;
            $clients[$client]['duration'] = ($clients[$client]['duration'] ?? 0) + $duration;
            $clients[$client]['memory'] = ($clients[$client]['memory'] ?? 0) + $memory;
            $clients[$client]['size'] = ($clients[$client]['size'] ?? 0) + $size;

            $routes[$route]['count'] = ($routes[$route]['count'] ?? 0) + 1;
            $routes[$route]['duration'] = ($routes[$route]['duration'] ?? 0) + $duration;
            $routes[$route]['memory'] = max($routes[$route]['memory'] ?? 0, $memory);
            $routes[$route]['size'] = ($routes[$route]['size'] ?? 0) + $size;
        }

        $clientStats = array_map(function ($ip, $data) {
            return [
                'ip' => $ip,
                'requests' => $data['count'],
                'avg_latency' => round($data['duration'] / $data['count'], 2),
                'avg_memory_mb' => round($data['memory'] / $data['count'] / 1048576, 2),
                'total_volume_kb' => round($data['size'] / 1024, 2),
            ];
        }, array_keys($clients), $clients);

        $routeStats = array_map(function ($route, $data) {
            return [
                'route' => $route,
                'requests' => $data['count'],
                'avg_latency' => round($data['duration'] / $data['count'], 2),
                'max_memory_mb' => round($data['memory'] / 1048576, 2),
                'avg_payload_kb' => round($data['size'] / $data['count'] / 1024, 2),
            ];
        }, array_keys($routes), $routes);

        sort($durations);
        $count = count($durations);
        $medianLatency = $count % 2 === 0
            ? ($durations[$count / 2 - 1] + $durations[$count / 2]) / 2
            : $durations[floor($count / 2)];

        return [
            'entries' => $entries,
            'topClients' => array_slice(array_values($this->sortBy($clientStats, 'requests')), 0, 5),
            'topVolumeClients' => array_slice(array_values($this->sortBy($clientStats, 'total_volume_kb')), 0, 5),
            'topMemoryRoutes' => array_slice(array_values($this->sortBy($routeStats, 'max_memory_mb')), 0, 5),
            'topSlowRoutes' => array_slice(array_values($this->sortBy($routeStats, 'avg_latency')), 0, 5),
            'topRequestedRoutes' => array_slice(array_values($this->sortBy($routeStats, 'requests')), 0, 5),
            'avgLatency' => round(array_sum($durations) / max(1, count($durations)), 2),
            'medianLatency' => round($medianLatency, 2),
            'avgMemoryMb' => round($totalMemory / max(1, count($entries)) / 1048576, 2),
            'avgPayloadKb' => round($totalSize / max(1, count($entries)) / 1024, 2)
        ];
    }

    private function sortBy(array $array, string $key): array
    {
        usort($array, fn($a, $b) => $b[$key] <=> $a[$key]);
        return $array;
    }

    public function fetchSlowRoutes(array $options): array
    {
        $from = new \DateTimeImmutable($options['from'] ?? '-7 days');
        $to = new \DateTimeImmutable($options['to'] ?? 'now');
        $sort = $options['sort'] ?? 'avg';
        $top = (int) ($options['top'] ?? 10);
        $auth = $options['auth'] ?? null;
        $status = $options['status'] ?? null;
        $uriFilter = $options['uri'] ?? null;

        $lines = VisitorLogHelper::loadLogsForDateRange($this->config->getLogDir(), $from, $to);

        $entries = [];

        foreach ($lines as $line) {
            $entry = json_decode($line, true);
            if (!is_array($entry) || !is_numeric($entry['duration_ms'] ?? null)) continue;

            if ($auth && ($entry['auth'] ?? null) !== $auth) continue;
            if ($status && (int)($entry['status_code'] ?? 0) !== (int)$status) continue;
            if ($uriFilter && !str_contains($entry['uri'] ?? '', $uriFilter)) continue;

            $key = $entry['route'] ?? $entry['uri'] ?? null;
            if (!$key) continue;

            if (!isset($entries[$key])) {
                $entries[$key] = [
                    'count' => 0,
                    'total' => 0,
                    'max' => 0,
                    'uri' => $entry['uri'] ?? null,
                    'route' => $entry['route'] ?? null,
                    'status_code' => $entry['status_code'] ?? null,
                    'auth' => $entry['auth'] ?? null,
                ];
            }

            $entries[$key]['count']++;
            $entries[$key]['total'] += $entry['duration_ms'];
            $entries[$key]['max'] = max($entries[$key]['max'], $entry['duration_ms']);
        }

        foreach ($entries as &$data) {
            $data['avg'] = round($data['total'] / $data['count'], 2);
            $data['max'] = round($data['max'], 2);
        }

        uasort($entries, match ($sort) {
            'max' => fn($a, $b) => $b['max'] <=> $a['max'],
            'count' => fn($a, $b) => $b['count'] <=> $a['count'],
            default => fn($a, $b) => $b['avg'] <=> $a['avg'],
        });

        return [
            'from' => $from,
            'to' => $to,
            'entries' => array_slice($entries, 0, $top, true),
        ];
    }

    public function shouldIncludeEntry(array $entry, ?string $filter, array &$seen): bool
    {
        $visitorId = $entry['visitor_id'] ?? md5($entry['ip'] ?? uniqid());
        $isBot = isset($entry['user_agent']) && preg_match('/bot|crawl|spider|slurp|bing/i', $entry['user_agent']);
        $isReturning = isset($seen[$visitorId]);
        $seen[$visitorId] = true;

        return match ($filter) {
            'bot' => $isBot,
            'utm' => !empty($entry['utm']),
            'referrer' => !empty($entry['referer']),
            'new' => !$isReturning,
            'return' => $isReturning,
            default => true,
        };
    }

}
