<?php

namespace Beast\VisitorTrackerBundle\Command;

use Beast\VisitorTrackerBundle\Service\VisitorLogHelper;
use Beast\VisitorTrackerBundle\Service\VisitorLogConfig;
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
    private string $logPath;

    public function __construct(private VisitorLogConfig $config)
    {
        parent::__construct();
        $this->logPath = $this->config->getLogDir();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        if (!file_exists($this->logPath)) {
            $io->error('No log directory found at: ' . $this->logPath);
            return Command::FAILURE;
        }

        $from = new \DateTimeImmutable('-30 days'); // You can make this dynamic later
        $to = new \DateTimeImmutable('now');

        $logLines = VisitorLogHelper::loadLogsForDateRange($this->logPath, $from, $to);
        if (empty($logLines)) {
            $io->warning('No log entries found in selected date range.');
            return Command::SUCCESS;
        }

        $parsed = VisitorLogHelper::parseLogLines($logLines);

        $io->title('📈 Visitor Statistics (Last 30 Days)');
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
