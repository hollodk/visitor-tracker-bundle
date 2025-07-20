<?php

namespace Beast\VisitorTrackerBundle\Service;

class VisitorLogHelper
{
    public static function loadLogsForDateRange(string $logDir, \DateTimeInterface $from, \DateTimeInterface $to): array
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

    public static function parseLogLines(array $lines): array
    {
        $byDate = $byHour = $byCountry = $byCity = $byUri = [];
        $utmSources = $utmCampaigns = $byBrowser = $byOS = $byDevice = $byReferrer = [];
        $total = $bots = 0;
        $visitorIds = [];

        $dailyUniqueVisitors = [];
        $dailyReturningVisitors = [];
        $seenVisitors = [];

        foreach ($lines as $line) {
            $entry = json_decode($line, true);
            if (!is_array($entry)) continue;

            $date = substr($entry['date'] ?? '', 0, 10);
            $visitorId = $entry['visitor_id'] ?? null;

            if (!$visitorId || !$date) continue;

            // Unique vs returning
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
            $byDate[$day] = ($byDate[$day] ?? 0) + 1;

            $visitorIds[] = $visitorId;

            $hour = $entryTime->format('Y-m-d H:00');
            $byHour[$hour] = ($byHour[$hour] ?? 0) + 1;

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

        $uniqueVisitors = count(array_unique($visitorIds));
        $repeatedVisitors = count($visitorIds) - $uniqueVisitors;

        return [
            'total' => $total,
            'bots' => $bots,
            'unique' => $uniqueVisitors,
            'returning' => $repeatedVisitors,
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
            'daily_uniques' => $dailyUniques,
            'daily_returning' => $dailyReturnings,
            'weekly_uniques' => $weeklyUniques,
            'weekly_returning' => $weeklyReturnings,
        ];
    }
}
