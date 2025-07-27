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
    name: 'visitor:metric',
    description: 'Show summarized API performance metrics from logs'
)]
class VisitorMetricCommand extends Command
{
    public function __construct(private VisitorLogFetcher $fetcher, private VisitorRenderHelper $render)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption('since', null, InputOption::VALUE_OPTIONAL, 'How many days back to include (default 1)', 1);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $days = (int) $input->getOption('since');

        $stats = $this->fetcher->fetchMetricStats($days);

        if (empty($stats['entries'])) {
            $io->warning("No entries found.");
            return Command::SUCCESS;
        }

        $io->title("API Health Metrics (last $days day(s))");

        $this->render->renderClientTable($io, 'Top Clients by Requests', $stats['topClients']);
        $this->render->renderClientTable($io, 'Top Clients by Volume', $stats['topVolumeClients']);
        $this->render->renderRouteTable($io, 'Top Memory-Heavy Routes', $stats['topMemoryRoutes']);
        $this->render->renderRouteTable($io, 'Slowest Routes by Latency', $stats['topSlowRoutes']);
        $this->render->renderRouteTable($io, 'Most Requested Routes', $stats['topRequestedRoutes']);

        $this->render->renderOverallSummary(
            $io,
            $stats['avgLatency'],
            $stats['medianLatency'],
            $stats['avgMemoryMb'],
            $stats['avgPayloadKb']
        );

        return Command::SUCCESS;
    }
}
