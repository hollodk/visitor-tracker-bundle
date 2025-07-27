<?php

namespace Beast\VisitorTrackerBundle\Command;

use Beast\VisitorTrackerBundle\Service\VisitorLogFetcher;
use Beast\VisitorTrackerBundle\Service\VisitorRenderHelper;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: 'visitor:metric',
    description: 'Detailed Metric overview of visitor logs',
)]
class VisitorMetricCommand extends Command
{
    public function __construct(
        private VisitorLogFetcher $fetcher,
        private VisitorRenderHelper $renderer,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('source', null, InputOption::VALUE_OPTIONAL, 'Source (route, ip)', 'route')
            ->addOption('from', null, InputOption::VALUE_OPTIONAL, 'Start date', '-7 days')
            ->addOption('to', null, InputOption::VALUE_OPTIONAL, 'End date', 'now')
            ->addOption('limit', null, InputOption::VALUE_OPTIONAL, 'Limit for tables', 10);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $r = $this->fetcher->fetchSummarizeLogs([
            'from' => $input->getOption('from'),
            'to' => $input->getOption('to'),
        ]);

        $summary = $r['summary'];
        $lines = $r['lines'];

        $limit = (int) $input->getOption('limit');

        $source = $input->getOption('source');

        $list = $this->fetcher->fetchMetricTable($lines, $source, $sortBy = 'avg_memory', $limit);
        $this->renderer->renderMetricTable($io, $list);

        $list = $this->fetcher->fetchMetricTable($lines, $source, $sortBy = 'avg_duration', $limit);
        $this->renderer->renderMetricTable($io, $list);

        $list = $this->fetcher->fetchMetricTable($lines, $source, $sortBy = 'requests', $limit);
        $this->renderer->renderMetricTable($io, $list);

        $list = $this->fetcher->fetchMetricTable($lines, $source, $sortBy = 'payload_kb', $limit);
        $this->renderer->renderMetricTable($io, $list);

        return Command::SUCCESS;
    }
}
