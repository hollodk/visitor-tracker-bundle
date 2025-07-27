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
    name: 'visitor:slow',
    description: 'Shows the slowest routes or URIs based on request duration',
)]
class VisitorSlowCommand extends Command
{
    public function __construct(private VisitorLogFetcher $fetcher, private VisitorRenderHelper $render)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('from', null, InputOption::VALUE_OPTIONAL, 'Start date (Y-m-d)', (new \DateTimeImmutable('-7 days'))->format('Y-m-d'))
            ->addOption('to', null, InputOption::VALUE_OPTIONAL, 'End date (Y-m-d)', (new \DateTimeImmutable())->format('Y-m-d'))
            ->addOption('sort', null, InputOption::VALUE_OPTIONAL, 'Sort by: avg, max, count', 'avg')
            ->addOption('top', null, InputOption::VALUE_OPTIONAL, 'Number of items to show', 10)
            ->addOption('auth', null, InputOption::VALUE_OPTIONAL, 'Filter by auth type: user or anon')
            ->addOption('status', null, InputOption::VALUE_OPTIONAL, 'Filter by HTTP status code')
            ->addOption('uri', null, InputOption::VALUE_OPTIONAL, 'Filter by URI substring');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $result = $this->fetcher->fetchSlowRoutes([
            'from' => $input->getOption('from'),
            'to' => $input->getOption('to'),
            'top' => $input->getOption('top'),
            'sort' => $input->getOption('sort'),
            'auth' => $input->getOption('auth'),
            'status' => $input->getOption('status'),
            'uri' => $input->getOption('uri'),
        ]);

        if (empty($result['entries'])) {
            $io->warning('No matching entries found.');
            return Command::SUCCESS;
        }

        $this->render->renderSlowRoutesTable(
            $io,
            sprintf('🐢 Top %d Slowest Routes/URIs from %s to %s',
            count($result['entries']),
            $result['from']->format('Y-m-d'),
            $result['to']->format('Y-m-d')
            ),
            $result['entries']
        );

        return Command::SUCCESS;
    }
}
