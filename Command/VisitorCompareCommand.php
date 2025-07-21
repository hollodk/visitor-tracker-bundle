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
    name: 'visitor:compare',
    description: 'Compare visitor stats between two date ranges'
)]
class VisitorCompareCommand extends Command
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
            ->addOption('from', null, InputOption::VALUE_OPTIONAL, 'Start date for Range A (Y-m-d)')
            ->addOption('to', null, InputOption::VALUE_OPTIONAL, 'End date for Range A (Y-m-d)')
            ->addOption('vs-from', null, InputOption::VALUE_OPTIONAL, 'Start date for Range B (Y-m-d)')
            ->addOption('vs-to', null, InputOption::VALUE_OPTIONAL, 'End date for Range B (Y-m-d)')
            ->addOption('top', null, InputOption::VALUE_OPTIONAL, 'Limit of top items to show', 5);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $topLimit = (int) $input->getOption('top');

        try {
            $rangeAStart = $input->getOption('from')
                ? new \DateTimeImmutable($input->getOption('from'))
                : (new \DateTimeImmutable('last sunday'))->modify('-2 weeks +1 day'); // Monday

            $rangeAEnd = $input->getOption('to')
                ? new \DateTimeImmutable($input->getOption('to'))
                : (new \DateTimeImmutable('last sunday'))->modify('-1 day'); // Saturday

            $rangeBStart = $input->getOption('vs-from')
                ? new \DateTimeImmutable($input->getOption('vs-from'))
                : (new \DateTimeImmutable('last sunday'))->modify('-1 week +1 day'); // Monday

            $rangeBEnd = $input->getOption('vs-to')
                ? new \DateTimeImmutable($input->getOption('vs-to'))
                : (new \DateTimeImmutable('last sunday'))->modify('-1 day'); // Saturday
        } catch (\Exception $e) {
            $io->error("Invalid date format. Use Y-m-d.");
            return Command::FAILURE;
        }

        $io->title(sprintf(
            '📊 Comparing %s → %s with %s → %s',
            $rangeAStart->format('Y-m-d'),
            $rangeAEnd->format('Y-m-d'),
            $rangeBStart->format('Y-m-d'),
            $rangeBEnd->format('Y-m-d')
        ));

        $linesA = VisitorLogHelper::loadLogsForDateRange($this->logPath, $rangeAStart, $rangeAEnd);
        $linesB = VisitorLogHelper::loadLogsForDateRange($this->logPath, $rangeBStart, $rangeBEnd);

        $statsA = VisitorLogHelper::parseLogLines($linesA);
        $statsB = VisitorLogHelper::parseLogLines($linesB);

        $this->printComparison($io, 'Total Visits', $statsA['total'], $statsB['total']);
        $this->printComparison($io, 'Unique Visitors', $statsA['unique'], $statsB['unique']);
        $this->printComparison($io, 'Returning Visitors', $statsA['returning'], $statsB['returning']);
        $this->printComparison($io, 'Bot Traffic', $statsA['bots'], $statsB['bots']);

        $this->printTopComparison($io, '🌍 Countries', $statsA['byCountry'], $statsB['byCountry'], $topLimit);
        $this->printTopComparison($io, '📱 Devices', $statsA['byDevice'], $statsB['byDevice'], $topLimit);
        $this->printTopComparison($io, '🧑‍💻 Browsers', $statsA['byBrowser'], $statsB['byBrowser'], $topLimit);
        $this->printTopComparison($io, '💻 OS', $statsA['byOS'], $statsB['byOS'], $topLimit);
        $this->printTopComparison($io, '🎯 UTM Campaigns', $statsA['utmCampaigns'], $statsB['utmCampaigns'], $topLimit);
        $this->printTopComparison($io, '🔗 Referrers', $statsA['byReferrer'], $statsB['byReferrer'], $topLimit);
        $this->printTopComparison($io, '📄 URIs', $statsA['byUri'], $statsB['byUri'], $topLimit);

        return Command::SUCCESS;
    }

    private function printComparison(SymfonyStyle $io, string $label, int $a, int $b): void
    {
        $change = $b - $a;
        $percent = $a !== 0 ? round(($change / $a) * 100, 1) : 0;
        $symbol = $change > 0 ? '🔺' : ($change < 0 ? '🔻' : '➖');

        $io->writeln(sprintf(
            "<info>%s</info>: A: <fg=blue>%d</> | B: <fg=yellow>%d</> | %s %s%%",
            $label, $a, $b, $symbol, $percent
        ));
    }

    private function printTopComparison(SymfonyStyle $io, string $label, array $a, array $b, int $limit): void
    {
        $io->section($label);

        $allKeys = array_unique(array_merge(array_keys($a), array_keys($b)));

        $results = [];
        foreach ($allKeys as $key) {
            $valA = $a[$key] ?? 0;
            $valB = $b[$key] ?? 0;
            $diff = $valB - $valA;
            $results[$key] = [
                'A' => $valA,
                'B' => $valB,
                'Δ' => $diff,
            ];
        }

        uasort($results, fn($x, $y) => abs($y['Δ']) <=> abs($x['Δ']));
        $results = array_slice($results, 0, $limit, true);

        $table = [];
        foreach ($results as $key => $row) {
            $symbol = $row['Δ'] > 0 ? '🔺' : ($row['Δ'] < 0 ? '🔻' : '➖');
            $table[] = [
                $key,
                $row['A'],
                $row['B'],
                sprintf("%s %s", $symbol, $row['Δ']),
            ];
        }

        $io->table(['Item', 'A', 'B', 'Change'], $table);
    }
}

