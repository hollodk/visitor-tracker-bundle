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
    name: 'visitor:devops',
    description: 'Detailed DevOps overview of visitor logs',
)]
class VisitorDevopsCommand extends Command
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
            ->addOption('from', null, InputOption::VALUE_OPTIONAL, 'Start date', '-7 days')
            ->addOption('to', null, InputOption::VALUE_OPTIONAL, 'End date', 'now')
            ->addOption('limit', null, InputOption::VALUE_OPTIONAL, 'Limit for tables', 10);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $summary = $this->fetcher->fetchSummarizeLogs([
            'from' => $input->getOption('from'),
            'to' => $input->getOption('to'),
        ]);

        $limit = (int) $input->getOption('limit');

        $stats = $this->renderer->renderHealth($io, $summary);
        $stats = $this->renderer->renderDevopsStats($io, $summary, $limit);

        return Command::SUCCESS;
    }
}
