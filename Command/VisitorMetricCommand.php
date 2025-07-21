<?php

namespace Beast\VisitorTrackerBundle\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Beast\VisitorTrackerBundle\Service\VisitorLogConfig;

#[AsCommand(
    name: 'visitor:metric',
    description: 'Show summarized API performance metrics from logs'
)]
class VisitorMetricCommand extends Command
{
    public function __construct(private VisitorLogConfig $config)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption('since', null, InputOption::VALUE_OPTIONAL, 'How many days back to include (default 1)', 1);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $since = (int) $input->getOption('since');

        $entries = [];
        for ($i = 0; $i < $since; $i++) {
            $date = (new \DateTimeImmutable("-$i days"));
            $file = $this->config->getLogFileForDate($date);
            if (file_exists($file)) {
                $lines = file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
                foreach ($lines as $line) {
                    $decoded = json_decode($line);
                    if (is_object($decoded)) {
                        $entries[] = $decoded;
                    }
                }
            }
        }

        if (empty($entries)) {
            $io->warning("No entries found for the last $since day(s).");
            return Command::SUCCESS;
        }

        $clients = [];
        $routes = [];
        $durations = [];
        $totalMemory = 0;
        $totalSize = 0;

        foreach ($entries as $entry) {
            $client = $entry->ip ?? 'unknown';
            $route = $entry->route ?? ($entry->uri ?? 'unknown');
            $duration = $entry->duration_ms ?? 0;
            $memory = $entry->memory_usage_bytes ?? 0;
            $size = $entry->response_size_bytes ?? 0;

            $durations[] = $duration;
            $totalMemory += $memory;
            $totalSize += $size;

            $clients[$client]['count'] = ($clients[$client]['count'] ?? 0) + 1;
            $clients[$client]['duration'] = ($clients[$client]['duration'] ?? 0) + $duration;
            $clients[$client]['memory'] = ($clients[$client]['memory'] ?? 0) + $memory;
            $clients[$client]['size'] = ($clients[$client]['size'] ?? 0) + $size;

            $routes[$route]['count'] = ($routes[$route]['count'] ?? 0) + 1;
            $routes[$route]['duration'] = ($routes[$route]['duration'] ?? 0) + $duration;
            $routes[$route]['memory'] = max($routes[$route]['memory'] ?? 0, $memory);
            $routes[$route]['size'] = ($routes[$route]['size'] ?? 0) + $size;
        }

        $clientStats = array_map(function ($ip, $data) {
            return [
                'ip' => $ip,
                'requests' => $data['count'],
                'avg_latency' => round($data['duration'] / $data['count'], 2),
                'avg_memory_mb' => round($data['memory'] / $data['count'] / 1048576, 2),
                'total_volume_kb' => round($data['size'] / 1024, 2),
            ];
        }, array_keys($clients), $clients);

        $routeStats = array_map(function ($route, $data) {
            return [
                'route' => $route,
                'requests' => $data['count'],
                'avg_latency' => round($data['duration'] / $data['count'], 2),
                'max_memory_mb' => round($data['memory'] / 1048576, 2),
                'avg_payload_kb' => round($data['size'] / $data['count'] / 1024, 2),
            ];
        }, array_keys($routes), $routes);

        usort($clientStats, fn($a, $b) => $b['requests'] <=> $a['requests']);
        $topClients = array_slice($clientStats, 0, 5);

        usort($clientStats, fn($a, $b) => $b['total_volume_kb'] <=> $a['total_volume_kb']);
        $topVolumeClients = array_slice($clientStats, 0, 5);

        usort($routeStats, fn($a, $b) => $b['max_memory_mb'] <=> $a['max_memory_mb']);
        $topMemoryRoutes = array_slice($routeStats, 0, 5);

        usort($routeStats, fn($a, $b) => $b['avg_latency'] <=> $a['avg_latency']);
        $topSlowRoutes = array_slice($routeStats, 0, 5);

        usort($routeStats, fn($a, $b) => $b['requests'] <=> $a['requests']);
        $topRequestedRoutes = array_slice($routeStats, 0, 5);

        sort($durations);
        $count = count($durations);
        $medianLatency = $count % 2 === 0
            ? ($durations[$count / 2 - 1] + $durations[$count / 2]) / 2
            : $durations[floor($count / 2)];

        $overallLatency = round(array_sum($durations) / count($durations), 2);
        $overallMemoryMb = round($totalMemory / count($entries) / 1048576, 2);
        $overallPayloadKb = round($totalSize / count($entries) / 1024, 2);

        $io->title("API Health Metrics (last $since day(s))");

        $io->section('Top Clients by Request Count');
        $io->table(['IP', 'Requests', 'Avg Latency (ms)', 'Avg Memory (MB)', 'Total Volume (KB)'], $topClients);

        $io->section('Top Clients by Data Volume');
        $io->table(['IP', 'Requests', 'Avg Latency (ms)', 'Avg Memory (MB)', 'Total Volume (KB)'], $topVolumeClients);

        $io->section('Top Memory-Heavy Routes');
        $io->table(['Route', 'Requests', 'Avg Latency (ms)', 'Max Memory (MB)', 'Avg Payload (KB)'], $topMemoryRoutes);

        $io->section('Slowest Routes by Latency');
        $io->table(['Route', 'Requests', 'Avg Latency (ms)', 'Max Memory (MB)', 'Avg Payload (KB)'], $topSlowRoutes);

        $io->section('Most Requested Routes');
        $io->table(['Route', 'Requests', 'Avg Latency (ms)', 'Max Memory (MB)', 'Avg Payload (KB)'], $topRequestedRoutes);

        $io->section('Overall Averages');
        $io->text("Avg Latency: {$overallLatency} ms");
        $io->text("Median Latency: {$medianLatency} ms");
        $io->text("Avg Memory Usage: {$overallMemoryMb} MB");
        $io->text("Avg Payload Size: {$overallPayloadKb} KB");

        return Command::SUCCESS;
    }
}

