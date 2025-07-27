<?php

namespace Beast\VisitorTrackerBundle\Service;

class VisitorLogHelper
{
    public function __construct(private VisitorLogFetcher $fetcher)
    {
    }

    public function fetchSummarizeLogs(array $rawLines): array
    {
        $this->fetcher->fetch($options);

        $parsed = self::parseLogLines($rawLines);
        return self::buildAggregates($parsed);
    }

    public function buildAggregates(array $entries): array
    {
        $byDate = $byHour = $byCountry = $byCity = $byUri = [];
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

            $byDate[$day] = ($byDate[$day] ?? 0) + 1;
            $byHour[$hour] = ($byHour[$hour] ?? 0) + 1;

            $visitorIds[] = $visitorId;

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

            // Duration and memory
            $durations[] = floatval($entry['duration_ms'] ?? 0);
            $memoryUsage[] = floatval($entry['memory_usage_bytes'] ?? 0);
            $responseSizes[] = floatval($entry['response_size_bytes'] ?? 0);

            // PHP Warnings
            if (!empty($entry['php_warnings']) && is_array($entry['php_warnings'])) {
                foreach ($phpWarningTotals as $type => $_) {
                    $phpWarningTotals[$type] += $entry['php_warnings'][$type] ?? 0;
                }
            }

            // Authenticated
            $authType = ($entry['auth'] ?? 'anon') === 'anon' ? 'anon' : 'auth';
            $authCount[$authType]++;

            // Content Type
            if (!empty($entry['content_type'])) {
                $contentTypes[$entry['content_type']] = ($contentTypes[$entry['content_type']] ?? 0) + 1;
            }

            // Status Code
            $status = (int) ($entry['status_code'] ?? 0);
            if ($status > 0) {
                $statusCodes[$status] = ($statusCodes[$status] ?? 0) + 1;
            }
        }

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

            'byDate' => $byDate,
            'byHour' => $byHour,
            'byCountry' => $byCountry,
            'byCity' => $byCity,
            'byUri' => $byUri,
            'utmSources' => $utmSources,
            'utmCampaigns' => $utmCampaigns,
            'byBrowser' => $byBrowser,
            'byOS' => $byOS,
            'byDevice' => $byDevice,
            'byReferrer' => $byReferrer,
            'byContentType' => $contentTypes,
            'byStatusCode' => $statusCodes,

            'daily_uniques' => $dailyUniques,
            'daily_returning' => $dailyReturnings,
            'weekly_uniques' => $weeklyUniques,
            'weekly_returning' => $weeklyReturnings,

            'avg_duration_ms' => $durations ? round(array_sum($durations) / count($durations), 2) : 0,
            'max_duration_ms' => $durations ? max($durations) : 0,
            'avg_memory_mb' => $memoryUsage ? round(array_sum($memoryUsage) / count($memoryUsage) / 1024 / 1024, 2) : 0,
            'max_memory_mb' => $memoryUsage ? round(max($memoryUsage) / 1024 / 1024, 2) : 0,
            'avg_response_kb' => $responseSizes ? round(array_sum($responseSizes) / count($responseSizes) / 1024, 2) : 0,

            'php_warnings' => $phpWarningTotals,
            'auth_counts' => $authCount,
        ];
    }

}
