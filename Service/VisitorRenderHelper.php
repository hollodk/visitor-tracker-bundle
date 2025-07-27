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

    public function renderSysadminStats(SymfonyStyle $io, $summary)
    {
        $io->section('📊 Top Status Codes');
        foreach (array_slice($summary['byStatusCode'], 0, 5) as $code => $count) {
            $io->text(" - $code: $count");
        }

        $io->section('📂 Content Types');
        foreach (array_slice($summary['byContentType'], 0, 5) as $type => $count) {
            $io->text(" - $type: $count");
        }

        $io->section('🌐 Routes Accessed');
        foreach (array_slice($summary['byRoute'] ?? [], 0, 5) as $route => $count) {
            $io->text(" - $route: $count");
        }

        $io->section('🕵️‍♂️ Methods Used');
        foreach (array_slice($summary['byMethod'] ?? [], 0, 5) as $method => $count) {
            $io->text(" - $method: $count");
        }

        $io->section('🌍 Countries');
        foreach (array_slice($summary['byCountry'], 0, 5) as $country => $count) {
            $io->text(" - $country: $count");
        }

        $io->section('🧭 Locales');
        foreach (array_slice($summary['byLocale'] ?? [], 0, 5) as $locale => $count) {
            $io->text(" - $locale: $count");
        }
    }

    public function renderDevopsStats(SymfonyStyle $io, $summary, $limit)
    {
        $this->title($io, '⚙️ DevOps Visitor Metrics');

        $this->barChart($io, '🌍 Top Referrer Domains', $summary['byReferrerDomain'], 50, $limit);
        $this->barChart($io, '🧭 HTTP Methods', $summary['byMethod'], 30, $limit);
        $this->barChart($io, '📂 Top Content Types', $summary['byContentType'], 30, $limit);
        $this->barChart($io, '❌ Top Failing Routes (4xx/5xx)', $this->extractFailingRoutes($summary['byRouteStatus'] ?? []), 40);

        $io->section('💾 Heavy Clients (Memory Usage)');
        $io->table(
            ['IP', 'Requests', 'Memory (MB)', 'Payload (KB)'],
            $this->extractHeavyClients($summary['parsed'] ?? [])
        );

        $this->renderSlowRoutesTable($io, '🐢 Slowest Routes', $this->toRouteDurationStats($summary['byRouteDuration'], $summary['byRouteStatus'] ?? []));

        $this->memoryTable($io, '🧠 Memory Usage by Route', $this->buildMemoryStats($summary['byRoute'], $summary['avg_memory_mb']));
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
        $clients = [];

        foreach ($entries as $entry) {
            $ip = $entry['ip'] ?? 'unknown';
            $clients[$ip]['requests'] = ($clients[$ip]['requests'] ?? 0) + 1;
            $clients[$ip]['memory'] = ($clients[$ip]['memory'] ?? 0) + ($entry['memory_usage_bytes'] ?? 0);
            $clients[$ip]['size'] = ($clients[$ip]['size'] ?? 0) + ($entry['response_size_bytes'] ?? 0);
        }

        uasort($clients, fn($a, $b) => $b['memory'] <=> $a['memory']); // or use 'size'

        return array_slice(array_map(function ($ip, $data) {
            return [
                'ip' => $ip,
                'requests' => $data['requests'],
                'memory_mb' => round($data['memory'] / 1048576, 2),
                'size_kb' => round($data['size'] / 1024, 2),
            ];
        }, array_keys($clients), $clients), 0, $limit);
    }

}
