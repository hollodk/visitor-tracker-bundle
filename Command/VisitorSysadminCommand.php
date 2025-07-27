<?php

namespace Beast\VisitorTrackerBundle\Command;

use Beast\VisitorTrackerBundle\Service\VisitorLogFetcher;
use Beast\VisitorTrackerBundle\Service\VisitorRenderHelper;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'visitor:sysadmin',
    description: 'Shows a system health summary from recent visitor logs',
)]
class VisitorSysadminCommand extends Command
{
    public function __construct(
        private VisitorLogFetcher $fetcher,
        private VisitorRenderHelper $renderHelper
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('from', null, InputOption::VALUE_OPTIONAL, 'Start date (Y-m-d)', (new \DateTimeImmutable('-1 day'))->format('Y-m-d'))
            ->addOption('to', null, InputOption::VALUE_OPTIONAL, 'End date (Y-m-d)', (new \DateTimeImmutable())->format('Y-m-d'));
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $from = $input->getOption('from');
        $to = $input->getOption('to');

        $data = $this->fetcher->fetchSummarizeLogs([
            'from' => $from,
            'to' => $to,
        ]);

        $stats = $this->renderHelper->renderHealth($io, $data);
        $stats = $this->renderHelper->renderSysadminStats($io, $data);

        return Command::SUCCESS;
    }
}
