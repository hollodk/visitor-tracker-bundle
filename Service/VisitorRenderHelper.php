<?php

namespace Beast\VisitorTrackerBundle\Service;

use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

class VisitorRenderHelper
{
    public function title(SymfonyStyle $io, string $text): void
    {
        $io->title($text);
    }

    public function success(SymfonyStyle $io, string $text): void
    {
        $io->success($text);
    }

    public function line(SymfonyStyle $io, string $text): void
    {
        $io->writeln($text);
    }

    public function table(SymfonyStyle $io, string $title, array $data, array $headers, int $limit = null): void
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

    public function barChart(SymfonyStyle $io, string $title, array $data, int $maxBars = 50, int $limit = 10): void
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

    public function memoryTable(SymfonyStyle $io, string $title, array $routes): void
    {
        if (empty($routes)) {
            $io->section($title);
            $io->text('No memory usage data found.');
            return;
        }

        $table = array_map(
            fn($k, $v) => [
                $k,
                $v['count'],
                number_format($v['avg'] / 1048576, 2), // avg MB
                number_format($v['max'] / 1048576, 2), // max MB
            ],
            array_keys($routes),
            $routes
        );

        $io->section($title);
        $io->table(['Route/URI', 'Requests', 'Avg Mem (MB)', 'Max Mem (MB)'], $table);
    }

    public function renderClientTable(SymfonyStyle $io, string $title, array $rows): void
    {
        $io->section($title);
        $io->table(['IP', 'Requests', 'Avg Latency (ms)', 'Avg Memory (MB)', 'Total Volume (KB)'],
            array_map(fn($r) => array_values($r), $rows));
    }

    public function renderRouteTable(SymfonyStyle $io, string $title, array $rows): void
    {
        $io->section($title);
        $io->table(['Route', 'Requests', 'Avg Latency (ms)', 'Max Memory (MB)', 'Avg Payload (KB)'],
            array_map(fn($r) => array_values($r), $rows));
    }

    public function renderMetricTable(SymfonyStyle $io, $list)
    {
        $io->section($list['title']);
        $io->table($list['headers'], $list['rows']);
    }

    public function renderOverallSummary(SymfonyStyle $io, float $avgLatency, float $median, float $mem, float $payload): void
    {
        $io->section('Overall Averages');
        $io->text("Avg Latency: {$avgLatency} ms");
        $io->text("Median Latency: {$median} ms");
        $io->text("Avg Memory Usage: {$mem} MB");
        $io->text("Avg Payload Size: {$payload} KB");
    }

    public function renderSlowRoutesTable(SymfonyStyle $io, string $title, array $entries): void
    {
        $table = [];

        foreach ($entries as $key => $data) {
            $highlight = ($data['avg'] > 1000 || $data['max'] > 3000) ? '🔥' : '';
            $table[] = [
                $key,
                $data['count'],
                "{$data['avg']} ms",
                "{$data['max']} ms",
                $data['status_code'] ?? '-',
                $data['auth'] ?? '-',
                $highlight,
            ];
        }

        $io->section($title);
        $io->table(['Route/URI', 'Requests', 'Avg Duration', 'Max Duration', 'Status', 'Auth', '⚠'], $table);
    }

    public function renderHighAverageDurationTable(SymfonyStyle $io, array $summary): void
    {
        $stats = [];

        foreach ($summary['byRouteDuration'] as $route => $durations) {
            $count = count($durations);
            $avg = $count ? round(array_sum($durations) / $count) : 0;
            $max = $count ? round(max($durations)) : 0;

            // Add emoji if avg > 500ms or max > 1000ms
            $warn = ($avg > 500 || $max > 1000) ? '🔥' : '';

            $stats[] = [
                'route' => $route,
                'count' => $count,
                'avg' => "{$avg} ms",
                'max' => "{$max} ms",
                'warn' => $warn,
            ];
        }

        usort($stats, fn($a, $b) => $b['avg'] <=> $a['avg']);

        $tableRows = array_map(fn($r) => [
            $r['route'],
            $r['count'],
            $r['avg'],
            $r['max'],
            $r['warn'],
        ], array_slice($stats, 0, 10));

        $io->section('🐌 Routes with High Average Duration');
        $io->table(
            ['Route', 'Requests', 'Avg Duration', 'Max Duration', '⚠'],
            $tableRows
        );
    }

    public function renderErrorProneRoutesTable(SymfonyStyle $io, array $summary): void
    {
        $rows = [];

        foreach ($summary['byRouteStatus'] as $route => $statuses) {
            $row = [
                $route,
                $statuses[200] ?? 0,
                $statuses[400] ?? 0,
                $statuses[404] ?? 0,
                $statuses[500] ?? 0,
            ];

            // Count all other status codes
            $otherCount = array_sum(array_filter($statuses, fn($code, $k) => !in_array((int)$k, [200, 400, 404, 500]), ARRAY_FILTER_USE_BOTH));
            $row[] = $otherCount;
            $row[] = $row[2]+$row[3]+$row[4]+$otherCount;

            $rows[] = $row;
        }

        // Optional: sort routes by total errors (404 + 500 + other)
        usort($rows, fn($a, $b) =>
            ($b[2] + $b[3] + $b[4]) <=> ($a[2] + $a[3] + $a[4])
        );

        $io->section('⚠️ Error-Prone Routes');
        $io->table(['Route', '200', '400', '404', '500', 'Other', 'Total'], array_slice($rows, 0, 10));
    }

    public function renderTopClientsTable(SymfonyStyle $io, array $entries, int $limit = 10): void
    {
        $clients = [];

        foreach ($entries as $entry) {
            $ip = $entry['ip'] ?? 'unknown';
            $clients[$ip]['count'] = ($clients[$ip]['count'] ?? 0) + 1;
            $clients[$ip]['total_duration'] = ($clients[$ip]['total_duration'] ?? 0) + ($entry['duration_ms'] ?? 0);
            $clients[$ip]['total_memory'] = ($clients[$ip]['total_memory'] ?? 0) + ($entry['memory_usage_bytes'] ?? 0);
            $clients[$ip]['total_size'] = ($clients[$ip]['total_size'] ?? 0) + ($entry['response_size_bytes'] ?? 0);
        }

        $rows = [];

        foreach ($clients as $ip => $data) {
            $count = $data['count'];
            $rows[] = [
                $ip,
                $count,
                round($data['total_memory'] / $count / 1048576, 2), // MB
                round($data['total_duration'] / $count, 2), // ms
                round($data['total_size'] / 1024, 2), // KB
            ];
        }

        usort($rows, fn($a, $b) => $b[1] <=> $a[1]); // Sort by request count

        $io->section('📡 Top Clients by Requests');
        $io->table(['IP', 'Requests', 'Avg Memory (MB)', 'Avg Duration (ms)', 'Total Payload (KB)'], array_slice($rows, 0, $limit));
    }

    public function renderVisitorEntry(array $entry, OutputInterface $output, bool $isBot, bool $isReturning): void
    {
        $flag = $entry['country_code'] ?? '🌍';
        $ref = $entry['referer'] ?? '';
        $utm = $entry['utm']['utm_campaign'] ?? '';
        $browser = $entry['browser'] ?? '';
        $os = $entry['os'] ?? '';
        $device = $entry['device_type'] ?? '';
        $city = $entry['city'] ?? '';
        $isp = $entry['isp'] ?? '';
        $country = $entry['country'] ?? 'Unknown';
        $visitorType = $isReturning ? 'Returning' : 'New';
        $timestamp = isset($entry['date']) ? (new \DateTimeImmutable($entry['date']))->format('H:i') : '--:--';
        $uri = $entry['uri'] ?? '';

        if ($isBot) {
            $output->writeln("🤖 {$timestamp} BOT detected: " . substr($entry['user_agent'] ?? '', 0, 80));
        } else {
            $line1 = sprintf("🕒 %s [%s] %s/%s/%s | %s", $timestamp, $visitorType, $browser, $os, $device, $uri);
            $line2 = sprintf("%s %s (%s) 📡 %s", $flag, $country, $city ?: 'n/a', $isp ?: 'Unknown ISP');
            if ($utm) $line2 .= " 📢 $utm";
            if ($ref) $line2 .= " 🔗 $ref";

            $output->writeln($line1);
            $output->writeln($line2);
        }
    }

    public function renderHealth(SymfonyStyle $io, $summary)
    {
        $io->title('🛠️ System Health Summary');

        $io->listing([
            "Requests          : {$summary['total']}",
            "Unique Visitors   : {$summary['unique']}",
            "Returning Visitors: {$summary['returning']}",
            "Bots Detected     : {$summary['bots']}",
            "Avg Duration      : {$summary['avg_duration_ms']} ms",
            "Max Duration      : {$summary['max_duration_ms']} ms",
            "Avg Memory        : {$summary['avg_memory_mb']} MB",
            "Max Memory        : {$summary['max_memory_mb']} MB",
            "Avg Response Size : {$summary['avg_response_kb']} KB",
            "Auth Status       : anon={$summary['auth_counts']['anon']}, auth={$summary['auth_counts']['auth']}",
            "Warnings          : W:{$summary['php_warnings']['warning']} N:{$summary['php_warnings']['notice']} D:{$summary['php_warnings']['deprecated']} E:{$summary['php_warnings']['error']}"
        ]);
    }

    private function toRouteDurationStats(array $byRouteDuration, array $byRouteStatus): array
    {
        $result = [];

        foreach ($byRouteDuration as $route => $durations) {
            $avg = round(array_sum($durations) / count($durations), 2);
            $max = round(max($durations), 2);
            $result[$route] = [
                'count' => count($durations),
                'avg' => $avg,
                'max' => $max,
                'status_code' => key($byRouteStatus[$route] ?? []),
                'auth' => '-', // placeholder
            ];
        }

        return $result;
    }

    private function buildMemoryStats(array $byRoute, float $avgMemory): array
    {
        $result = [];

        foreach ($byRoute as $route => $count) {
            $result[$route] = [
                'count' => $count,
                'avg' => $avgMemory * 1024 * 1024, // convert MB back to bytes
                'max' => $avgMemory * 1024 * 1024 * 1.5,
            ];
        }

        return $result;
    }

    private function extractFailingRoutes(array $byRouteStatus, int $limit = 10): array
    {
        $errors = [];

        foreach ($byRouteStatus as $route => $statuses) {
            foreach ($statuses as $code => $count) {
                if ($code >= 400) {
                    $errors[$route] = ($errors[$route] ?? 0) + $count;
                }
            }
        }

        arsort($errors);
        return array_slice($errors, 0, $limit, true);
    }

    private function extractHeavyClients(array $entries, int $limit = 10): array
    {
    }

    public function renderRequestStats(SymfonyStyle $io, $summary)
    {
        $io->section('🔄 Request Stats');

        $io->listing([
            "Total Requests      : {$summary['total']}",
            "Unique Visitors     : {$summary['unique']}",
            "Returning Visitors  : {$summary['returning']}",
            "Authenticated Users : {$summary['auth_counts']['auth']}",
            "Bots Detected       : {$summary['bots']}",
        ]);
    }

    public function renderResponseMetrics(SymfonyStyle $io, $summary)
    {
        $io->section('📊 Response Metrics');

        $io->listing([
            "Avg Duration      : {$summary['avg_duration_ms']}",
            "Max Duration      : {$summary['max_duration_ms']}",
            "Avg Memory Usage  : {$summary['avg_memory_mb']}",
            "Max Memory Usage  : {$summary['max_memory_mb']}",
            "Avg Payload Size  : {$summary['avg_response_kb']}",
        ]);
    }

    public function renderPhpWarnings(SymfonyStyle $io, $summary)
    {
        $io->section('⚠️ PHP Warnings');

        $io->listing([
            "Notice            : {$summary['php_warnings']['notice']}",
            "Warnings          : {$summary['php_warnings']['warning']}",
            "Deprecated        : {$summary['php_warnings']['deprecated']}",
            "Errors            : {$summary['php_warnings']['error']}",
        ]);
    }

    public function renderAuthenticated(SymfonyStyle $io, $summary)
    {
        $io->section('🔧 Authenticated vs Anonymous');

        $io->listing([
            "Authenticated     : {$summary['auth_counts']['auth']}",
            "Anonymous         : {$summary['auth_counts']['anon']}",
        ]);
    }

    public function renderTopStatusCodes(SymfonyStyle $io, $summary)
    {
        $io->section('📈 Top Status Codes');

        $list = [];
        foreach ($summary['byStatusCode'] as $code=>$quantity) {
            $list[] = $code.': '.$quantity;
        }

        $io->listing($list);
    }
}
