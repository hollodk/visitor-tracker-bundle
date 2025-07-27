<?php

namespace Beast\VisitorTrackerBundle\Command;

use Beast\VisitorTrackerBundle\Service\VisitorLogConfig;
use Beast\VisitorTrackerBundle\Service\VisitorLogFetcher;
use Beast\VisitorTrackerBundle\Service\VisitorRenderHelper;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'visitor:tail',
    description: 'Tail and pretty-print the visitor logs in real-time or preview mode'
)]
class VisitorTailCommand extends Command
{
    private array $seenVisitors = [];

    public function __construct(
        private VisitorLogConfig $config,
        private VisitorLogFetcher $fetcher,
        private VisitorRenderHelper $render
    )
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('date', null, InputOption::VALUE_OPTIONAL, 'Date to tail (Y-m-d)', (new \DateTime())->format('Y-m-d'))
            ->addOption('follow', 'f', InputOption::VALUE_NONE, 'Keep watching the log file (like tail -f)')
            ->addOption('filter', null, InputOption::VALUE_OPTIONAL, 'Filter by type: bot, utm, referrer, new, return', null)
            ->addOption('preview', null, InputOption::VALUE_OPTIONAL, 'Show last N entries without following', null);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $logFile = $this->config->getTodayLogFile();
        if (!file_exists($logFile)) {
            $io->error("No log file found for today.");
            return Command::FAILURE;
        }

        $filter = $input->getOption('filter');
        $preview = $input->getOption('preview');
        $follow = $input->getOption('follow');

        if ($preview !== null) {
            $lines = file($logFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            foreach (array_slice($lines, -intval($preview)) as $line) {
                $this->renderLine($line, $output, $filter);
            }
            return Command::SUCCESS;
        }

        $lastLineCount = 0;
        while (true) {
            clearstatcache(true, $logFile);
            $lines = file($logFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            $newLines = array_slice($lines, $lastLineCount);
            $lastLineCount = count($lines);

            foreach ($newLines as $line) {
                $this->renderLine($line, $output, $filter);
            }

            if (!$follow) break;
            usleep(500000);
        }

        return Command::SUCCESS;
    }

    private function renderLine(string $line, OutputInterface $output, ?string $filter): void
    {
        $entry = json_decode($line, true);
        if (!is_array($entry)) return;

        $include = $this->fetcher->shouldIncludeEntry($entry, $filter, $this->seenVisitors);
        if (!$include) return;

        $visitorId = $entry['visitor_id'] ?? md5($entry['ip'] ?? uniqid());
        $isBot = isset($entry['user_agent']) && preg_match('/bot|crawl|spider|slurp|bing/i', $entry['user_agent']);
        $isReturning = isset($this->seenVisitors[$visitorId]);

        $this->render->renderVisitorEntry($entry, $output, $isBot, $isReturning);
    }
}
