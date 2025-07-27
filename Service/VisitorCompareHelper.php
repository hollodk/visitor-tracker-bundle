<?php

namespace Beast\VisitorTrackerBundle\Service;

use Symfony\Component\Console\Style\SymfonyStyle;

class VisitorCompareHelper
{
    public function printNumericComparison(SymfonyStyle $io, string $label, int $a, int $b): void
    {
        $change = $b - $a;
        $percent = $a !== 0 ? round(($change / $a) * 100, 1) : 0;
        $symbol = $change > 0 ? '🔺' : ($change < 0 ? '🔻' : '➖');

        $io->writeln(sprintf(
            "<info>%s</info>: A: <fg=blue>%d</> | B: <fg=yellow>%d</> | %s %s%%",
            $label, $a, $b, $symbol, $percent
        ));
    }

    public function printTopComparison(SymfonyStyle $io, string $label, array $a, array $b, int $limit): void
    {
        $io->section($label);
        $allKeys = array_unique(array_merge(array_keys($a), array_keys($b)));

        $results = [];
        foreach ($allKeys as $key) {
            $valA = $a[$key] ?? 0;
            $valB = $b[$key] ?? 0;
            $diff = $valB - $valA;
            $results[$key] = ['A' => $valA, 'B' => $valB, 'Δ' => $diff];
        }

        uasort($results, fn($x, $y) => abs($y['Δ']) <=> abs($x['Δ']));
        $results = array_slice($results, 0, $limit, true);

        $table = [];
        foreach ($results as $key => $row) {
            $symbol = $row['Δ'] > 0 ? '🔺' : ($row['Δ'] < 0 ? '🔻' : '➖');
            $table[] = [$key, $row['A'], $row['B'], sprintf("%s %s", $symbol, $row['Δ'])];
        }

        $io->table(['Item', 'A', 'B', 'Change'], $table);
    }
}

