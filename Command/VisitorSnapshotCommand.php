<?php

namespace Beast\VisitorTrackerBundle\Command;

use Beast\VisitorTrackerBundle\Service\VisitorLogFetcher;
use Beast\VisitorTrackerBundle\Service\VisitorRenderHelper;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
name: 'visitor:snapshot',
    description: 'Displays snapshot of current production with period comparison',
)]
class VisitorSnapshotCommand extends Command
{
    public function __construct(
        private VisitorLogFetcher $fetcher,
        private VisitorRenderHelper $renderer
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('period', null, InputOption::VALUE_REQUIRED, 'Compare timeframe: hour, day, or week', 'hour');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $period = $input->getOption('period');
        [$fromNow, $fromPrevious] = $this->resolveTimeframes($period);

        $now = $this->fetcher->fetchSummarizeLogs([
            'from' => $fromNow['from'],
            'to' => $fromNow['to'],
        ]);

        $prev = $this->fetcher->fetchSummarizeLogs([
            'from' => $fromPrevious['from'],
            'to' => $fromPrevious['to'],
        ]);

        $current = $now['summary'];
        $nowLines = $now['lines'];
        $previous = $prev['summary'];
        $prevLines = $prev['lines'];

        $label = ucfirst($period);
        $this->renderer->title($io, "📊 Visitor Snapshot — Last $label Overview");

        $header = ['Metric', 'Now', "Previous $label", 'Change'];
        $data = [
            ['👥 Unique Visitors', $current['unique'], $previous['unique'], $this->percentChange($previous['unique'], $current['unique'])],
            ['📄 Pageviews', $current['total'], $previous['total'], $this->percentChange($previous['total'], $current['total'])],
            ['⏱  Avg. Load Time', $current['performance']['avg_duration_ms'] . ' ms', $previous['performance']['avg_duration_ms'] . ' ms', $this->percentChange($previous['performance']['avg_duration_ms'], $current['performance']['avg_duration_ms'], true)],
            ['💾 Avg. Memory', $current['performance']['avg_memory_mb'] . ' MB', $previous['performance']['avg_memory_mb'] . ' MB', $this->percentChange($previous['performance']['avg_memory_mb'], $current['performance']['avg_memory_mb'], true)],
            ['📦 Avg. Payload', $current['performance']['avg_response_kb'] . ' KB', $previous['performance']['avg_response_kb'] . ' KB', $this->percentChange($previous['performance']['avg_response_kb'], $current['performance']['avg_response_kb'], true)],
            ['🚨 500 Errors', $current['php_warnings']['error'], $previous['php_warnings']['error'], $this->percentChange($previous['php_warnings']['error'], $current['php_warnings']['error'])],
            ['🚧 Other Warnings', array_sum($current['php_warnings']), array_sum($previous['php_warnings']), $this->percentChange(array_sum($previous['php_warnings']), array_sum($current['php_warnings']))],
            ['🤖 Bots Detected', $current['bots'], $previous['bots'], $this->percentChange($previous['bots'], $current['bots'])],
        ];

        $io->table($header, $data);

        $this->renderer->title($io, '📈 Current Top Visitors');
        $currentList = $this->fetcher->fetchMetricTable($nowLines, 'ip', 'requests', 3);
        $this->renderer->renderMetricTable($io, $currentList);

        $this->renderer->title($io, "📉 Previous Top Visitors ($label ago)");
        $prevList = $this->fetcher->fetchMetricTable($prevLines, 'ip', 'requests', 3);
        $this->renderer->renderMetricTable($io, $prevList);

        $this->renderer->line($io, sprintf("📆 Last Updated: %s", (new \DateTime())->format('d M Y H:i')));

        return Command::SUCCESS;
    }

    private function resolveTimeframes(string $period): array
    {
        return match ($period) {
            'hour' => [
                ['from' => '-1 hour', 'to' => 'now'],
                ['from' => '-2 hour', 'to' => '-1 hour'],
            ],
            'day' => [
                ['from' => '-1 day', 'to' => 'now'],
                ['from' => '-2 day', 'to' => '-1 day'],
            ],
            'week' => [
                ['from' => '-1 week', 'to' => 'now'],
                ['from' => '-2 week', 'to' => '-1 week'],
            ],
default => throw new \InvalidArgumentException("Unsupported period: $period"),
        };
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
