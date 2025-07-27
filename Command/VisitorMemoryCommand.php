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
    name: 'visitor:memory',
    description: 'Show memory usage stats per route/URI'
)]
class VisitorMemoryCommand extends Command
{
    public function __construct(private VisitorRenderHelper $render, private VisitorLogFetcher $fetcher)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('from', null, InputOption::VALUE_OPTIONAL, 'Start date (Y-m-d)', (new \DateTimeImmutable('-7 days'))->format('Y-m-d'))
            ->addOption('to', null, InputOption::VALUE_OPTIONAL, 'End date (Y-m-d)', (new \DateTimeImmutable())->format('Y-m-d'))
            ->addOption('sort', null, InputOption::VALUE_OPTIONAL, 'Sort by: avg, max, count', 'avg')
            ->addOption('top', null, InputOption::VALUE_OPTIONAL, 'Number of entries to show', 10);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $result = $this->fetcher->fetchMemoryStats([
            'from' => $input->getOption('from'),
            'to' => $input->getOption('to'),
            'sort' => $input->getOption('sort'),
            'top' => $input->getOption('top'),
        ]);

        $from = $result['from'];
        $to = $result['to'];
        $stats = $result['stats'];

        if (empty($stats)) {
            $io->warning('No matching log entries found.');
            return Command::SUCCESS;
        }

        $this->render->memoryTable(
            $io,
            "🧠 Memory Usage by Route/URI ({$from->format('Y-m-d')} to {$to->format('Y-m-d')})",
            $stats
        );

        return Command::SUCCESS;
    }
}

