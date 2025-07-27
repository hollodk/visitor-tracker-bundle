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

}
