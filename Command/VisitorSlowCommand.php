<?php

namespace Beast\VisitorTrackerBundle\Command;

use Beast\VisitorTrackerBundle\Service\VisitorLogHelper;
use Beast\VisitorTrackerBundle\Service\VisitorLogConfig;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'visitor:slow',
    description: 'Shows the slowest routes or URIs based on request duration',
)]
class VisitorSlowCommand extends Command
{
    private string $logPath;

    public function __construct(private VisitorLogConfig $config)
    {
        parent::__construct();
        $this->logPath = $this->config->getLogDir();
    }

    protected function configure(): void
    {
        $this
            ->addOption('from', null, InputOption::VALUE_OPTIONAL, 'Start date (Y-m-d)', (new \DateTimeImmutable('-7 days'))->format('Y-m-d'))
            ->addOption('to', null, InputOption::VALUE_OPTIONAL, 'End date (Y-m-d)', (new \DateTimeImmutable())->format('Y-m-d'))
            ->addOption('sort', null, InputOption::VALUE_OPTIONAL, 'Sort by: avg, max, count', 'avg')
            ->addOption('top', null, InputOption::VALUE_OPTIONAL, 'Number of items to show', 10)
            ->addOption('auth', null, InputOption::VALUE_OPTIONAL, 'Filter by auth type: user or anon')
            ->addOption('status', null, InputOption::VALUE_OPTIONAL, 'Filter by HTTP status code')
            ->addOption('uri', null, InputOption::VALUE_OPTIONAL, 'Filter by URI substring');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $from = new \DateTimeImmutable($input->getOption('from'));
        $to = new \DateTimeImmutable($input->getOption('to'));
        $top = (int) $input->getOption('top') ?: 10;
        $sort = $input->getOption('sort');
        $auth = $input->getOption('auth');
        $status = $input->getOption('status');
        $uriFilter = $input->getOption('uri');

        $lines = VisitorLogHelper::loadLogsForDateRange($this->logPath, $from, $to);

        $entries = [];
        foreach ($lines as $line) {
            $entry = json_decode($line, true);
            if (!is_array($entry)) continue;
            if (!isset($entry['duration_ms']) || !is_numeric($entry['duration_ms'])) continue;

            if ($auth && ($entry['auth'] ?? null) !== $auth) continue;
            if ($status && (int)($entry['status_code'] ?? 0) !== (int)$status) continue;
            if ($uriFilter && !str_contains($entry['uri'] ?? '', $uriFilter)) continue;

            $key = $entry['route'] ?? $entry['uri'];
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

        if (empty($entries)) {
            $io->warning('No matching entries found.');
            return Command::SUCCESS;
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

        $entries = array_slice($entries, 0, $top, true);

        $table = [];
        foreach ($entries as $key => $data) {
            $highlight = $data['avg'] > 1000 || $data['max'] > 3000 ? ' 🔥' : '';
            $table[] = [
                $key,
                $data['count'],
                "{$data['avg']} ms",
                "{$data['max']} ms",
                $data['status_code'] ?? '-',
                $data['auth'] ?? '-',
                $highlight
            ];
        }

        $io->title(sprintf('🐢 Top %d Slowest Routes/URIs from %s to %s', $top, $from->format('Y-m-d'), $to->format('Y-m-d')));
        $io->table(['Route/URI', 'Requests', 'Avg Duration', 'Max Duration', 'Status', 'Auth', '⚠'], $table);

        return Command::SUCCESS;
    }
}

