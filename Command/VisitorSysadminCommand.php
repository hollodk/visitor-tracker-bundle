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
        private VisitorRenderHelper $renderer
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

        $r = $this->fetcher->fetchSummarizeLogs([
            'from' => $from,
            'to' => $to,
        ]);
        $summary = $r['summary'];

        $stats = $this->renderer->renderHealth($io, $summary);

        $this->renderer->title($io, '📊 Top Status Codes');
        foreach (array_slice($summary['traffic']['status_codes'], 0, 5) as $code => $count) {
            $io->text(" - $code: $count");
        }

        $io->section('📂 Content Types');
        foreach (array_slice($summary['traffic']['content_types'], 0, 5) as $type => $count) {
            $io->text(" - $type: $count");
        }

        $io->section('🌐 Routes Accessed');
        foreach (array_slice($summary['byRoute'] ?? [], 0, 5) as $route => $count) {
            $io->text(" - $route: $count");
        }

        $io->section('🕵️ <200d>♂️ Methods Used');
        foreach (array_slice($summary['byMethod'] ?? [], 0, 5) as $method => $count) {
            $io->text(" - $method: $count");
        }

        $io->section('🌍 Countries');
        foreach (array_slice($summary['geo']['countries'], 0, 5) as $country => $count) {
            $io->text(" - $country: $count");
        }

        $io->section('🧭 Locales');
        foreach (array_slice($summary['byLocale'] ?? [], 0, 5) as $locale => $count) {
            $io->text(" - $locale: $count");
        }

        return Command::SUCCESS;
    }
}
