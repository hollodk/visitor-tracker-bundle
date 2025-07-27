<?php

namespace Beast\VisitorTrackerBundle\Command;

use Beast\VisitorTrackerBundle\Service\VisitorLogFetcher;
use Beast\VisitorTrackerBundle\Service\VisitorRenderHelper;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'visitor:snapshot',
    description: 'Displays snapshot of current production',
)]
class VisitorSnapshotCommand extends Command
{
    public function __construct(private VisitorLogFetcher $fetcher, private VisitorRenderHelper $renderer) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $now = $this->fetcher->fetchSummarizeLogs([
            'from' => '-1 hour',
            'to' => 'now',
        ]);

        $prev = $this->fetcher->fetchSummarizeLogs([
            'from' => '-2 hour',
            'to' => '-1 hour',
        ]);

        $current = $now['summary'];
        $nowLines = $now['lines'];
        $previous = $prev['summary'];
        $prevLines = $prev['lines'];

        $this->renderer->title($io, '📊 Visitor Snapshot — LIVE Overview');

        $header = ['Metric', 'Now', 'Last 60m', 'Change'];
        $data = [
            ['👥 Unique Visitors', $current['unique'], $previous['unique'], $this->percentChange($previous['unique'], $current['unique'])],
            ['📄 Pageviews', $current['total'], $previous['total'], $this->percentChange($previous['total'], $current['total'])],
            ['⏱ Avg. Load Time', $current['performance']['avg_duration_ms'] . ' ms', $previous['performance']['avg_duration_ms'] . ' ms', $this->percentChange($previous['performance']['avg_duration_ms'], $current['performance']['avg_duration_ms'], true)],
            ['💾 Avg. Memory', $current['php_warnings']['error'], $previous['php_warnings']['error'], $this->percentChange($previous['php_warnings']['error'], $current['php_warnings']['error'])],
            ['📦 Avg. Payload', $current['bots'], $previous['bots'], $this->percentChange($previous['bots'], $current['bots'])],
            ['🚨 500 Errors', $current['bots'], $previous['bots'], $this->percentChange($previous['bots'], $current['bots'])],
            ['🚧 Other Warnings', $current['bots'], $previous['bots'], $this->percentChange($previous['bots'], $current['bots'])],
            ['🤖 Bots Detected', $current['bots'], $previous['bots'], $this->percentChange($previous['bots'], $current['bots'])],
        ];

        $this->renderer->title($io, '📊 Visitor Activity Overview');
        $io->table($header, $data);

        $source = 'ip';

        $list = $this->fetcher->fetchMetricTable($nowLines, $source, $sortBy = 'requests', 3);
        $this->renderer->renderMetricTable($io, $list);

        $list = $this->fetcher->fetchMetricTable($prevLines, $source, $sortBy = 'requests', 3);
        $this->renderer->renderMetricTable($io, $list);

        $this->renderer->line($io, sprintf("📆 Last Updated: %s", (new \DateTime())->format('d M Y H:i')));

        return Command::SUCCESS;
    }

    private function percentChange($old, $new, $inverse = false): string
    {
        if ($old == 0) return $new > 0 ? '+∞%' : '0%';

        $change = $inverse
            ? 100 * ($old - $new) / $old
            : 100 * ($new - $old) / $old;

        return sprintf('%+0.1f%%', $change);
    }

}
