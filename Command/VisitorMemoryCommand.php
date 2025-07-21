<?php

namespace Beast\VisitorTrackerBundle\Command;

use Beast\VisitorTrackerBundle\Service\VisitorLogHelper;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'visitor:memory',
    description: 'Show memory usage stats per route/URI'
)]
class VisitorMemoryCommand extends Command
{
    private string $logPath;

    public function __construct()
    {
        parent::__construct();
        $this->logPath = __DIR__ . '/../../../../var/visitor_tracker/logs';
    }

    protected function configure(): void
    {
        $this
            ->addOption('from', null, InputOption::VALUE_OPTIONAL, 'Start date (Y-m-d)', (new \DateTimeImmutable('-7 days'))->format('Y-m-d'))
            ->addOption('to', null, InputOption::VALUE_OPTIONAL, 'End date (Y-m-d)', (new \DateTimeImmutable())->format('Y-m-d'))
            ->addOption('sort', null, InputOption::VALUE_OPTIONAL, 'Sort by: avg, max, count', 'avg')
            ->addOption('top', null, InputOption::VALUE_OPTIONAL, 'Number of entries to show', 10);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $from = new \DateTimeImmutable($input->getOption('from'));
        $to = new \DateTimeImmutable($input->getOption('to'));
        $sort = $input->getOption('sort');
        $top = (int) $input->getOption('top');

        $lines = VisitorLogHelper::loadLogsForDateRange($this->logPath, $from, $to);

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

        $routes = array_slice($routes, 0, $top, true);

        $io->title("🧠 Memory Usage by Route/URI ({$from->format('Y-m-d')} to {$to->format('Y-m-d')})");
        $io->table(
            ['Route/URI', 'Requests', 'Avg Mem (MB)', 'Max Mem (MB)'],
            array_map(fn($k, $v) => [
                $k,
                $v['count'],
                number_format($v['avg'] / 1048576, 2),
                number_format($v['max'] / 1048576, 2),
            ], array_keys($routes), $routes)
        );

        return Command::SUCCESS;
    }
}

