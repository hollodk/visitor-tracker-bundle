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
            ->addOption('from', null, InputOption::VALUE_OPTIONAL, 'Start date', '-1 days')
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

        $this->renderer->title($io, '📡 DEVOPS PERFORMANCE SNAPSHOT (Past 24h)');

        $this->renderer->renderRequestStats($io, $summary);
        $this->renderer->renderResponseMetrics($io, $summary);
        $this->renderer->renderPhpWarnings($io, $summary);
        $this->renderer->renderAuthenticated($io, $summary);
        $this->renderer->renderTopStatusCodes($io, $summary);

        $source = 'route';
        $list = $this->fetcher->fetchMetricTable($lines, $source, $sortBy = 'avg_duration', $limit, 100, 50);
        $this->renderer->renderMetricTable($io, $list);

        $source = 'route';
        $list = $this->fetcher->fetchMetricTable($lines, $source, $sortBy = 'avg_memory', $limit, 50, 20);
        $this->renderer->renderMetricTable($io, $list);

        $source = 'route';
        $list = $this->fetcher->fetchMetricTable($lines, $source, $sortBy = 'requests', $limit, 1000, 500);
        $this->renderer->renderMetricTable($io, $list);

        $source = 'referrer_domain';
        $list = $this->fetcher->fetchMetricTable($lines, $source, $sortBy = 'requests', $limit, 1000, 500);
        $this->renderer->renderMetricTable($io, $list);

        $source = 'ip';
        $list = $this->fetcher->fetchMetricTable($lines, $source, $sortBy = 'payload_kb', $limit, 1000, 500);
        $this->renderer->renderMetricTable($io, $list);

        $source = 'ip';
        $list = $this->fetcher->fetchMetricTable($lines, $source, $sortBy = 'requests', $limit, 1000, 500);
        $this->renderer->renderMetricTable($io, $list);

        $this->renderer->renderErrorProneRoutesTable($io, $summary);

        /*
        $this->renderer->barChart($io, '🌍 Top Referrer Domains', $summary['byReferrerDomain'], 50, $limit);
        $this->renderer->barChart($io, '🧭 HTTP Methods', $summary['byMethod'], 30, $limit);
        $this->renderer->barChart($io, '📂 Top Content Types', $summary['byContentType'], 30, $limit);

        $this->renderer->renderTopClientsTable($io, $lines);
        $this->renderer->renderHighAverageDurationTable($io, $summary);

        $list = $this->fetcher->fetchMetricTable($lines, 'route', $sortBy = 'avg_memory', 10);
        $this->renderer->renderMetricTable($io, $list);
         */

        return Command::SUCCESS;
    }
}
