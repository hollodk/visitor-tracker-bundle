<?php

namespace Beast\VisitorTrackerBundle\Command;

use Beast\VisitorTrackerBundle\Service\VisitorLogFetcher;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'visitor:ip-insight',
    description: 'Get a deep-dive into a single IP or CIDR usage over time.'
)]
class VisitorIpInsightCommand extends Command
{
    public function __construct(
        private readonly VisitorLogFetcher $fetcher
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('ip', null, InputOption::VALUE_REQUIRED, 'IP address or CIDR block to analyze')
            ->addOption('from', null, InputOption::VALUE_OPTIONAL, 'Start date (Y-m-d)', '-7 days')
            ->addOption('to', null, InputOption::VALUE_OPTIONAL, 'End date (Y-m-d)', 'now')
            ->addOption('top', null, InputOption::VALUE_OPTIONAL, 'How many top routes to show', 10);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $ip = $input->getOption('ip');
        if (!$ip) {
            $io->error('Please specify an IP address or CIDR using --ip');
            return Command::INVALID;
        }

        $options = [
            'ip' => $ip,
            'from' => $input->getOption('from'),
            'to' => $input->getOption('to')
        ];

        $result = $this->fetcher->fetch($options);
        $entries = $result['lines'];

        if (empty($entries)) {
            $io->warning("No matching log entries for: $ip");
            return Command::SUCCESS;
        }

        $io->section("📊 Insight for IP/CIDR: $ip");
        $io->listing([
            'Date Range: ' . $result['from']->format('Y-m-d') . ' to ' . $result['to']->format('Y-m-d'),
            'Entries matched: ' . count($entries),
        ]);

        $table = $this->fetcher->fetchMetricTable(
            $entries,
            groupBy: 'route',
            sortBy: 'requests',
            top: (int) $input->getOption('top')
        );

        $io->title($table['title']);
        $io->table($table['headers'], $table['rows']);


        $from = '-24 hours';
        $data = $this->fetcher->fetchSummarizeLogs(['from' => $from, 'to' => 'now']);
        $stats = $data['summary']['time']['hourly_stats'];

        ksort($stats); // Ensure sorted by time
        $rows = [];

        foreach ($stats as $time => $entry) {
            $row = [
                $time,
                $entry['unique_ips'],
                $entry['requests'],
                $entry['bots'],
                $entry['avg_duration_ms'] . ' ms',
                $entry['avg_memory_mb'] . ' MB',
                $entry['avg_response_kb'] . ' KB',
            ];

            $rows[] = $row;
        }

        $headers = [
            'Hour',
            '👥 Unique',
            '📄 Views',
            '🤖 Bots',
            '⏱  Avg Load',
            '💾 Mem',
            '📦 Payload',
        ];

        $io->title("📈 Visitor Trends (last 24 hours)");
        $io->table($headers, $rows);

        return Command::SUCCESS;
    }
}
