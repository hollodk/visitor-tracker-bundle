<?php

namespace Beast\VisitorTrackerBundle\Command;

use Beast\VisitorTrackerBundle\Service\VisitorCompareHelper;
use Beast\VisitorTrackerBundle\Service\VisitorLogFetcher;
use Beast\VisitorTrackerBundle\Service\VisitorLogHelper;
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
    public function __construct(
        private VisitorLogFetcher $fetcher,
        private VisitorCompareHelper $helper
    ) {
        parent::__construct();
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
        $top = (int) $input->getOption('top');

        try {
            [$aFrom, $aTo, $bFrom, $bTo] = $this->resolveDateRanges($input);
        } catch (\Exception $e) {
            $io->error("Invalid date format. Use Y-m-d.");
            return Command::FAILURE;
        }

        $io->title(sprintf(
            '📊 Comparing %s → %s vs %s → %s',
            $aFrom->format('Y-m-d'), $aTo->format('Y-m-d'),
            $bFrom->format('Y-m-d'), $bTo->format('Y-m-d')
        ));

        $dataA = $this->fetcher->fetch(['from' => $aFrom->format('Y-m-d'), 'to' => $aTo->format('Y-m-d')]);
        $statsA = $dataA['parsed'];

        $dataB = $this->fetcher->fetch(['from' => $bFrom->format('Y-m-d'), 'to' => $bTo->format('Y-m-d')]);
        $statsB = $dataB['parsed'];

        $this->helper->printNumericComparison($io, 'Total Visits', $statsA['total'], $statsB['total']);
        $this->helper->printNumericComparison($io, 'Unique Visitors', $statsA['unique'], $statsB['unique']);
        $this->helper->printNumericComparison($io, 'Returning Visitors', $statsA['returning'], $statsB['returning']);
        $this->helper->printNumericComparison($io, 'Bot Traffic', $statsA['bots'], $statsB['bots']);

        $this->helper->printTopComparison($io, '🌍 Countries', $statsA['byCountry'], $statsB['byCountry'], $top);
        $this->helper->printTopComparison($io, '📱 Devices', $statsA['byDevice'], $statsB['byDevice'], $top);
        $this->helper->printTopComparison($io, '🧑‍💻 Browsers', $statsA['byBrowser'], $statsB['byBrowser'], $top);
        $this->helper->printTopComparison($io, '💻 OS', $statsA['byOS'], $statsB['byOS'], $top);
        $this->helper->printTopComparison($io, '🎯 UTM Campaigns', $statsA['utmCampaigns'], $statsB['utmCampaigns'], $top);
        $this->helper->printTopComparison($io, '🔗 Referrers', $statsA['byReferrer'], $statsB['byReferrer'], $top);
        $this->helper->printTopComparison($io, '📄 URIs', $statsA['byUri'], $statsB['byUri'], $top);

        return Command::SUCCESS;
    }

    private function resolveDateRanges(InputInterface $input): array
    {
        return [
            $input->getOption('from')
                ? new \DateTimeImmutable($input->getOption('from'))
                : (new \DateTimeImmutable('last sunday'))->modify('-2 weeks +1 day'),
            $input->getOption('to')
                ? new \DateTimeImmutable($input->getOption('to'))
                : (new \DateTimeImmutable('last sunday'))->modify('-1 week'),
            $input->getOption('vs-from')
                ? new \DateTimeImmutable($input->getOption('vs-from'))
                : (new \DateTimeImmutable('last sunday'))->modify('-1 week +1 day'),
            $input->getOption('vs-to')
                ? new \DateTimeImmutable($input->getOption('vs-to'))
                : (new \DateTimeImmutable('last sunday'))->modify('-1 day'),
        ];
    }
}

