<?php

namespace Beast\VisitorTrackerBundle\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'visitor:stats',
    description: 'Displays visitor tracking statistics from the log file',
)]
class VisitorStatsCommand extends Command
{
    private string $logFile;

    public function __construct()
    {
        parent::__construct();
        $this->logPath = __DIR__ . '/../../../../var/visitor_tracker/logs';
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        if (!file_exists($this->logPath)) {
            $io->error('No log file found at: ' . $this->logPath);
            return Command::FAILURE;
        }

        $logFiles = glob($this->logPath . '/*.log');

        $lines = [];
        foreach ($logFiles as $file) {
            $fileLines = file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            $lines = array_merge($lines, $fileLines);
        }

        $parsed = $this->parseLogLines($lines);

        $io->title('📈 Visitor Statistics');
        $io->success("Total visits: {$parsed['total']}");
        $io->writeln("🧍 Unique visitors: {$parsed['unique']}");
        $io->writeln("🔁 Returning visitors: {$parsed['returning']}");

        $this->printBarChart($io, '📊 Hourly Traffic (last 24h)', $parsed['byHour'], 40, 24);
        $this->printBarChart($io, '🌍 Country Traffic Chart', $parsed['byCountry'], 40, 10);
        $this->printBarChart($io, '📱 Devices in Use', $parsed['byDevice'], 30, 10);

        $this->printBarChart($io, '📅 Daily Unique Visitors', $parsed['daily_uniques'], 40, 10);
        $this->printBarChart($io, '🔁 Daily Returning Visitors', $parsed['daily_returning'], 40, 10);

        $this->printBarChart($io, '📊 Weekly Unique Visitors', $parsed['weekly_uniques'], 40, 6);
        $this->printBarChart($io, '🔄 Weekly Returning Visitors', $parsed['weekly_returning'], 40, 6);

        $this->printTable($io, '🗓  Visits Per Day', $parsed['byDate'], ['Date', 'Visits']);
        $this->printTable($io, '⏱  Visits by Hour (last 24h)', $parsed['byHour'], ['Hour', 'Visits']);
        $this->printTable($io, '🌍 Top Countries', $parsed['byCountry'], ['Country', 'Count'], 10);
        $this->printTable($io, '🏙️ Top Cities', $parsed['byCity'], ['City', 'Count'], 10);
        $this->printTable($io, '🧑‍💻 Browsers', $parsed['byBrowser'], ['Browser', 'Count'], 10);
        $this->printTable($io, '💻 Operating Systems', $parsed['byOS'], ['OS', 'Count'], 10);
        $this->printTable($io, '📱 Device Types', $parsed['byDevice'], ['Device', 'Count']);
        $this->printTable($io, '📣 UTM Sources', $parsed['utmSources'], ['Source', 'Count']);
        $this->printTable($io, '🎯 UTM Campaigns', $parsed['utmCampaigns'], ['Campaign', 'Count']);
        $this->printTable($io, '🔗 Referrers', $parsed['byReferrer'], ['Referrer', 'Count'], 10);
        $this->printTable($io, '📄 Top Visited Pages', $parsed['byUri'], ['URI', 'Count'], 10);

        $io->note("🤖 Bot visits: {$parsed['bots']}");

        return Command::SUCCESS;
    }

    private function parseLogLines(array $lines): array
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

            // Initialize if not set
            $dailyUniqueVisitors[$date] ??= [];
            $dailyReturningVisitors[$date] ??= [];

            if (!isset($seenVisitors[$visitorId])) {
                // First time we've ever seen this visitor
                $seenVisitors[$visitorId] = $date;
                $dailyUniqueVisitors[$date][$visitorId] = true;
            } else {
                // Already seen, mark as returning
                $dailyReturningVisitors[$date][$visitorId] = true;
            }

            // Initialize if not set
            $dailyUniqueVisitors[$date] ??= [];
            $dailyReturningVisitors[$date] ??= [];

            if (!isset($seenVisitors[$visitorId])) {
                // First time we've ever seen this visitor
                $seenVisitors[$visitorId] = $date;
                $dailyUniqueVisitors[$date][$visitorId] = true;
            } else {
                // Already seen, mark as returning
                $dailyReturningVisitors[$date][$visitorId] = true;
            }
        }

        $now = new \DateTimeImmutable();
        $cutoff = $now->modify('-24 hours');

        $dailyUniques = [];
        $dailyReturnings = [];

        foreach ($dailyUniqueVisitors as $date => $ids) {
            $dailyUniques[$date] = count($ids);
        }
        foreach ($dailyReturningVisitors as $date => $ids) {
            $dailyReturnings[$date] = count($ids);
        }

        $weeklyUniques = [];
        $weeklyReturnings = [];

        foreach ($dailyUniques as $date => $count) {
            $week = (new \DateTimeImmutable($date))->format('o-\WW');
            $weeklyUniques[$week] = ($weeklyUniques[$week] ?? 0) + $count;
        }
        foreach ($dailyReturnings as $date => $count) {
            $week = (new \DateTimeImmutable($date))->format('o-\WW');
            $weeklyReturnings[$week] = ($weeklyReturnings[$week] ?? 0) + $count;
        }

        foreach ($lines as $line) {
            $entry = json_decode($line, true);
            if (!is_array($entry)) continue;

            try {
                $entryTime = new \DateTimeImmutable($entry['date']);
            } catch (\Exception) {
                continue;
            }

            $total++;
            $day = $entryTime->format('Y-m-d');
            $byDate[$day] = ($byDate[$day] ?? 0) + 1;

            if (!empty($entry['visitor_id'])) {
                $visitorIds[] = $entry['visitor_id'];
            }

            if ($entryTime >= $cutoff) {
                $hour = $entryTime->format('Y-m-d H:00');
                $byHour[$hour] = ($byHour[$hour] ?? 0) + 1;
            }

            if (!empty($entry['country'])) {
                $byCountry[$entry['country']] = ($byCountry[$entry['country']] ?? 0) + 1;
            }

            if (!empty($entry['city'])) {
                $byCity[$entry['city']] = ($byCity[$entry['city']] ?? 0) + 1;
            }

            if (!empty($entry['uri'])) {
                $byUri[$entry['uri']] = ($byUri[$entry['uri']] ?? 0) + 1;
            }

            if (!empty($entry['utm']['utm_source'])) {
                $utmSources[$entry['utm']['utm_source']] = ($utmSources[$entry['utm']['utm_source']] ?? 0) + 1;
            }

            if (!empty($entry['utm']['utm_campaign'])) {
                $utmCampaigns[$entry['utm']['utm_campaign']] = ($utmCampaigns[$entry['utm']['utm_campaign']] ?? 0) + 1;
            }

            if (!empty($entry['browser'])) {
                $byBrowser[$entry['browser']] = ($byBrowser[$entry['browser']] ?? 0) + 1;
            }

            if (!empty($entry['os'])) {
                $byOS[$entry['os']] = ($byOS[$entry['os']] ?? 0) + 1;
            }

            if (!empty($entry['device'])) {
                $byDevice[$entry['device']] = ($byDevice[$entry['device']] ?? 0) + 1;
            }

            if (!empty($entry['referrer'])) {
                $byReferrer[$entry['referrer']] = ($byReferrer[$entry['referrer']] ?? 0) + 1;
            }

            if (!empty($entry['is_bot'])) {
                $bots++;
            }
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

    private function printTable(SymfonyStyle $io, string $title, array $data, array $headers, int $limit = null): void
    {
        if (empty($data)) {
            $io->section($title);
            $io->text('No data available.');
            return;
        }

        arsort($data);
        if ($limit) {
            $data = array_slice($data, 0, $limit, true);
        }

        $io->section($title);
        $io->table($headers, array_map(null, array_keys($data), array_values($data)));
    }

    private function printBarChart(SymfonyStyle $io, string $title, array $data, int $maxBars = 50, int $limit = 10): void
    {
        if (empty($data)) {
            $io->section($title);
            $io->text('No data available.');
            return;
        }

        arsort($data);
        if ($limit > 0) {
            $data = array_slice($data, 0, $limit, true);
        }

        $maxValue = max($data);
        $scale = $maxValue > 0 ? $maxBars / $maxValue : 1;

        $io->section($title);
        foreach ($data as $label => $count) {
            $bar = str_repeat('▓', (int) ($count * $scale));
            $io->writeln(sprintf('%-20s %s %d', $label, $bar, $count));
        }
    }

}

