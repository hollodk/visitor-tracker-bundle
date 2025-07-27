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

        $logLines = $this->loadLogsForDateRange($logPath, $from, $to);

        return [
            'from' => $from,
            'to' => $to,
            'lines' => $this->parseLogLines($logLines, $options),
        ];
    }

    public function fetchSummarizeLogs($options)
    {
        $result = $this->fetch($options);

        return [
            'summary' => $this->buildAggregates($result['lines']),
            'lines' => $result['lines'],
        ];
    }

    public function loadLogsForDateRange(string $logDir, \DateTimeInterface $from, \DateTimeInterface $to): array
    {
        $logs = [];
        $period = new \DatePeriod($from, new \DateInterval('P1D'), (clone $to)->modify('+1 day'));

        foreach ($period as $date) {
            $filename = sprintf('%s/%s.log', rtrim($logDir, '/'), $date->format('Y-m-d'));
            if (file_exists($filename)) {
                $lines = file($filename, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
                $logs = array_merge($logs, $lines);
            }
        }

        return $logs;
    }

    public function parseLogLines(array $lines, array $options): array
    {
        $from = new \DateTime($options['from']);
        $to = new \DateTime($options['to']);

        $entries = [];

        foreach ($lines as $line) {
            $line = trim($line);
            if (!$line) continue;

            $entry = json_decode($line, true);
            if (!is_array($entry)) continue;

            $entry['date'] ??= null;

            // Skip if no date or invalid format
            if (!$entry['date']) continue;

            try {
                $entryDate = new \DateTimeImmutable($entry['date']);
            } catch (\Exception) {
                continue;
            }

            // Filter by date range
            if ($from && $entryDate < $from) continue;
            if ($to && $entryDate > $to) continue;

            // Default values
            $entry['visitor_id'] ??= null;
            $entry['duration_ms'] ??= 0;
            $entry['memory_usage_bytes'] ??= 0;
            $entry['php_warnings'] ??= [
                'notice' => 0,
                'warning' => 0,
                'deprecated' => 0,
                'error' => 0,
            ];
            $entry['utm'] ??= [];

            $entries[] = $entry;
        }

        return $entries;
    }

    function fetchMetricTable(array $entries, string $groupBy = 'ip', string $sortBy = 'requests', int $top = 10, $danger = null, $warning = null)
    {
        $aggregates = [];

        $dangerIcon = '🔥';
        $warningIcon = '⚠';

        $uniqueIps = [];
        foreach ($entries as $entry) {
            $key = $entry[$groupBy] ?? 'unknown';
            $ipKey = $key.'_'.$entry['ip'];

            $aggregates[$key]['requests'] = ($aggregates[$key]['requests'] ?? 0) + 1;
            $aggregates[$key]['duration_total'] = ($aggregates[$key]['duration_total'] ?? 0) + ($entry['duration_ms'] ?? 0);
            $aggregates[$key]['duration_max'] = max($aggregates[$key]['duration_max'] ?? 0, $entry['duration_ms'] ?? 0);

            $aggregates[$key]['memory_total'] = ($aggregates[$key]['memory_total'] ?? 0) + ($entry['memory_usage_bytes'] ?? 0);
            $aggregates[$key]['memory_max'] = max($aggregates[$key]['memory_max'] ?? 0, $entry['memory_usage_bytes'] ?? 0);

            $aggregates[$key]['payload_total'] = ($aggregates[$key]['payload_total'] ?? 0) + ($entry['response_size_bytes'] ?? 0);

            if (!isset($uniqueIps[$ipKey])) {
                $aggregates[$key]['unique_ip'] = ($aggregates[$key]['unique_ip'] ?? 0) + 1;
                $uniqueIps[$ipKey] = true;
            }
        }

        $result = [];

        foreach ($aggregates as $key => $data) {
            $req = $data['requests'];

            $param = [
                'source'        => $key,
                'requests'      => $req,
                'avg_duration'  => round($data['duration_total'] / $req, 2),
                'max_duration'  => $data['duration_max'],
                'avg_memory'    => round($data['memory_total'] / $req / 1048576, 2),
                'max_memory'    => round($data['memory_max'] / 1048576, 2),
                'unique_ip'     => $data['unique_ip'] ?? 0,
                'payload_kb'    => round($data['payload_total'] / 1024, 2),
                'flag'          => null,
            ];

            if ($danger !== null && $param[$sortBy] > $danger) {
                $param['flag'] = $dangerIcon;
            } elseif ($warning !== null && $param[$sortBy] > $warning) {
                $param['flag'] = $warningIcon;
            }

            $result[] = $param;
        }

        usort($result, fn($a, $b) => $b[$sortBy] <=> $a[$sortBy]);

        $headers = [
            'Source', 'Requests', 'Avg Duration (ms)', 'Max Duration (ms)',
            'Avg Memory (MB)', 'Max Memory (MB)', 'Unique IP', 'Payload (KB)',
            'Flag'
        ];

        $rows = array_map(fn($r) => [
            $r['source'],
            $r['requests'],
            $r['avg_duration'],
            $r['max_duration'],
            $r['avg_memory'],
            $r['max_memory'],
            $r['unique_ip'],
            $r['payload_kb'],
            $r['flag'],
        ], array_slice($result, 0, $top));

        return [
            'title' => "📈 Top $top by " . ucfirst($groupBy) . " sort by ". ucfirst($sortBy),
            'headers' => $headers,
            'rows' => $rows,
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

        $lines = $this->loadLogsForDateRange($this->config->getLogDir(), $from, $to);

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

    public function buildAggregates(array $entries): array
    {
        $byMethod = $byRoute = $byRouteDuration = $byRouteStatus = $byReferrerDomain = [];
        $byCountry = $byCity = $byUri = [];
        $utmSources = $utmCampaigns = $byBrowser = $byOS = $byDevice = $byReferrer = [];
        $contentTypes = $statusCodes = [];

        $total = $bots = 0;
        $visitorIds = [];

        $dailyUniqueVisitors = [];
        $dailyReturningVisitors = [];
        $seenVisitors = [];

        $durations = [];
        $memoryUsage = [];
        $responseSizes = [];

        $dailyStats = [];
        $hourlyStats = [];

        $phpWarningTotals = [
            'notice' => 0,
            'warning' => 0,
            'deprecated' => 0,
            'error' => 0,
        ];

        $authCount = [
            'anon' => 0,
            'auth' => 0,
        ];

        $initStats = function () {
            return [
                'requests' => 0,
                'unique_ips' => [],
                'errors' => 0,
                'bots' => 0,
                'durations' => [],
                'memory' => [],
                'payload' => [],
            ];
        };

        foreach ($entries as $entry) {
            $date = substr($entry['date'] ?? '', 0, 10);
            $visitorId = $entry['visitor_id'] ?? null;

            if (!$visitorId || !$date) continue;

            $dailyUniqueVisitors[$date] ??= [];
            $dailyReturningVisitors[$date] ??= [];

            if (!isset($seenVisitors[$visitorId])) {
                $seenVisitors[$visitorId] = $date;
                $dailyUniqueVisitors[$date][$visitorId] = true;
            } else {
                $dailyReturningVisitors[$date][$visitorId] = true;
            }

            try {
                $entryTime = new \DateTimeImmutable($entry['date']);
            } catch (\Exception) {
                continue;
            }

            $total++;
            $day = $entryTime->format('Y-m-d');
            $hour = $entryTime->format('Y-m-d H:00');

            $dailyStats[$day] ??= $initStats();
            $hourlyStats[$hour] ??= $initStats();

            $visitorIds[] = $visitorId;

            $route = $entry['route'] ?? 'unknown';
            $duration = floatval($entry['duration_ms'] ?? 0);

            $byRoute[$route] = ($byRoute[$route] ?? 0) + 1;
            $byRouteDuration[$route][] = $duration;

            $status = (int)($entry['status_code'] ?? 0);
            if ($status) {
                $byRouteStatus[$route][$status] = ($byRouteStatus[$route][$status] ?? 0) + 1;
                $statusCodes[$status] = ($statusCodes[$status] ?? 0) + 1;
            }

            if (!empty($entry['method'])) {
                $byMethod[$entry['method']] = ($byMethod[$entry['method']] ?? 0) + 1;
            }

            if (!empty($entry['referrer_domain'])) {
                $byReferrerDomain[$entry['referrer_domain']] = ($byReferrerDomain[$entry['referrer_domain']] ?? 0) + 1;
            }

            if (!empty($entry['country'])) $byCountry[$entry['country']] = ($byCountry[$entry['country']] ?? 0) + 1;
            if (!empty($entry['city'])) $byCity[$entry['city']] = ($byCity[$entry['city']] ?? 0) + 1;
            if (!empty($entry['uri'])) $byUri[$entry['uri']] = ($byUri[$entry['uri']] ?? 0) + 1;
            if (!empty($entry['utm']['utm_source'])) $utmSources[$entry['utm']['utm_source']] = ($utmSources[$entry['utm']['utm_source']] ?? 0) + 1;
            if (!empty($entry['utm']['utm_campaign'])) $utmCampaigns[$entry['utm']['utm_campaign']] = ($utmCampaigns[$entry['utm']['utm_campaign']] ?? 0) + 1;
            if (!empty($entry['browser'])) $byBrowser[$entry['browser']] = ($byBrowser[$entry['browser']] ?? 0) + 1;
            if (!empty($entry['os'])) $byOS[$entry['os']] = ($byOS[$entry['os']] ?? 0) + 1;
            if (!empty($entry['device'])) $byDevice[$entry['device']] = ($byDevice[$entry['device']] ?? 0) + 1;
            if (!empty($entry['referrer'])) $byReferrer[$entry['referrer']] = ($byReferrer[$entry['referrer']] ?? 0) + 1;

            if (!empty($entry['is_bot'])) $bots++;

            $duration = floatval($entry['duration_ms'] ?? 0);
            $memory = floatval($entry['memory_usage_bytes'] ?? 0);
            $payload = floatval($entry['response_size_bytes'] ?? 0);

            $durations[] = $duration;
            $memoryUsage[] = $memory;
            $responseSizes[] = $payload;

            foreach (['dailyStats' => $day, 'hourlyStats' => $hour] as $type => $key) {
                $bucket = &${$type}[$key];
                $bucket['requests']++;
                $bucket['unique_ips'][$entry['ip'] ?? 'unknown'] = true;
                if (!empty($entry['is_bot'])) $bucket['bots']++;
                $bucket['durations'][] = $duration;
                $bucket['memory'][] = $memory;
                $bucket['payload'][] = $payload;
            }
            unset($bucket);

            if (!empty($entry['php_warnings']) && is_array($entry['php_warnings'])) {
                foreach ($phpWarningTotals as $type => $_) {
                    $phpWarningTotals[$type] += $entry['php_warnings'][$type] ?? 0;
                }
            }

            $authType = ($entry['auth'] ?? 'anon') === 'anon' ? 'anon' : 'auth';
            $authCount[$authType]++;

            if (!empty($entry['content_type'])) {
                $contentTypes[$entry['content_type']] = ($contentTypes[$entry['content_type']] ?? 0) + 1;
            }
        }

        $reduceStats = function ($buckets) {
            $result = [];
            foreach ($buckets as $key => $bucket) {
                $result[$key] = [
                    'requests' => $bucket['requests'],
                    'unique_ips' => count($bucket['unique_ips']),
                    'bots' => $bucket['bots'],
                    'avg_duration_ms' => $bucket['durations'] ? round(array_sum($bucket['durations']) / count($bucket['durations']), 2) : 0,
                    'avg_memory_mb' => $bucket['memory'] ? round(array_sum($bucket['memory']) / count($bucket['memory']) / 1024 / 1024, 2) : 0,
                    'avg_response_kb' => $bucket['payload'] ? round(array_sum($bucket['payload']) / count($bucket['payload']) / 1024, 2) : 0,
                ];
            }
            return $result;
        };

        $dailyStatsReduced = $reduceStats($dailyStats);
        $hourlyStatsReduced = $reduceStats($hourlyStats);

        $dailyUniques = [];
        $dailyReturnings = [];
        $weeklyUniques = [];
        $weeklyReturnings = [];

        foreach ($dailyUniqueVisitors as $date => $ids) {
            $dailyUniques[$date] = count($ids);
            $week = (new \DateTimeImmutable($date))->format('o-\WW');
            $weeklyUniques[$week] = ($weeklyUniques[$week] ?? 0) + count($ids);
        }

        foreach ($dailyReturningVisitors as $date => $ids) {
            $dailyReturnings[$date] = count($ids);
            $week = (new \DateTimeImmutable($date))->format('o-\WW');
            $weeklyReturnings[$week] = ($weeklyReturnings[$week] ?? 0) + count($ids);
        }

        return [
            'total' => $total,
            'unique' => count(array_unique($visitorIds)),
            'returning' => count($visitorIds) - count(array_unique($visitorIds)),
            'bots' => $bots,

            'time' => [
                'requests' => [
                    'by_date' => array_map(fn($v) => $v['requests'], $dailyStatsReduced),
                    'by_hour' => array_map(fn($v) => $v['requests'], $hourlyStatsReduced),
                ],
                'uniques' => [
                    'daily' => $dailyUniques,
                    'weekly' => $weeklyUniques,
                ],
                'returning' => [
                    'daily' => $dailyReturnings,
                    'weekly' => $weeklyReturnings,
                ],
                'daily_stats' => $dailyStatsReduced,
                'hourly_stats' => $hourlyStatsReduced,
            ],

            'traffic' => [
                'by_method' => $byMethod,
                'by_route' => $byRoute,
                'route_durations' => $byRouteDuration,
                'route_statuses' => $byRouteStatus,
                'status_codes' => $statusCodes,
                'referrer_domains' => $byReferrerDomain,
                'referrers' => $byReferrer,
                'content_types' => $contentTypes,
                'utm_sources' => $utmSources,
                'utm_campaigns' => $utmCampaigns,
            ],

            'geo' => [
                'countries' => $byCountry,
                'cities' => $byCity,
                'uris' => $byUri,
            ],

            'device' => [
                'browser' => $byBrowser,
                'os' => $byOS,
                'device' => $byDevice,
            ],

            'performance' => [
                'avg_duration_ms' => $durations ? round(array_sum($durations) / count($durations), 2) : 0,
                'max_duration_ms' => $durations ? max($durations) : 0,
                'avg_memory_mb' => $memoryUsage ? round(array_sum($memoryUsage) / count($memoryUsage) / 1024 / 1024, 2) : 0,
                'max_memory_mb' => $memoryUsage ? round(max($memoryUsage) / 1024 / 1024, 2) : 0,
                'avg_response_kb' => $responseSizes ? round(array_sum($responseSizes) / count($responseSizes) / 1024, 2) : 0,
            ],

            'php_warnings' => $phpWarningTotals,
            'auth' => $authCount,
        ];
    }

}
