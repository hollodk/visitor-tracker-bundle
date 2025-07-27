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
    name: 'visitor:trend',
    description: 'Shows a trend table of visitors over time (hourly or daily)',
)]
class VisitorTrendCommand extends Command
{
    public function __construct(private VisitorLogFetcher $fetcher)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('mode', null, InputOption::VALUE_REQUIRED, 'hour or day', 'hour');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $mode = $input->getOption('mode');

        if (!in_array($mode, ['hour', 'day'], true)) {
            $io->error('Mode must be "hour" or "day".');
            return Command::INVALID;
        }

        $from = $mode === 'hour' ? '-24 hours' : '-30 days';
        $data = $this->fetcher->fetchSummarizeLogs(['from' => $from, 'to' => 'now']);
        $stats = $data['summary']['time'][$mode === 'hour' ? 'hourly_stats' : 'daily_stats'];

        ksort($stats); // Ensure sorted by time
        $previous = null;
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

            if ($previous !== null) {
                $row[] = $this->percentChange($previous['requests'], $entry['requests']);
                $row[] = $this->percentChange($previous['unique_ips'], $entry['unique_ips']);
            } else {
                $row[] = '-';
                $row[] = '-';
            }

            $previous = $entry;
            $rows[] = $row;
        }

        $headers = [
            $mode === 'hour' ? 'Hour' : 'Date',
            '👥 Unique',
            '📄 Views',
            '🤖 Bots',
            '⏱ Avg Load',
            '💾 Mem',
            '📦 Payload',
            'Δ Views',
            'Δ Uniques',
        ];

        $io->title("📈 Visitor Trends (last " . ($mode === 'hour' ? '24 hours' : '30 days') . ")");
        $io->table($headers, $rows);

        return Command::SUCCESS;
    }

    private function percentChange($old, $new): string
    {
        if ($old == 0) return $new > 0 ? '+∞%' : '0%';
        $change = 100 * ($new - $old) / $old;
        return sprintf('%+0.1f%%', $change);
    }
}
