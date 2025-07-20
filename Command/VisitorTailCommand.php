<?php

namespace Beast\VisitorTrackerBundle\Command;

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
        $date = $input->getOption('date');
        $logFile = __DIR__ . "/../../../../var/visitor_tracker/logs/{$date}.log";

        if (!file_exists($logFile)) {
            $io->error("No log file found for: $date");
            return Command::FAILURE;
        }

        $filter = $input->getOption('filter');
        $preview = $input->getOption('preview');
        $follow = $input->getOption('follow');

        if ($preview !== null) {
            $lines = file($logFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            $previewLines = array_slice($lines, -intval($preview));
            foreach ($previewLines as $line) {
                $this->renderEntry($line, $output, $filter);
            }
            return Command::SUCCESS;
        }

        $handle = fopen($logFile, 'r');
        if (!$handle) {
            $io->error("Could not open file: $logFile");
            return Command::FAILURE;
        }

        if ($follow) {
            while (true) {
                clearstatcache(true, $logFile);
                $lines = file($logFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
                $newLines = array_slice($lines, $lastLineCount);
                $lastLineCount = count($lines);

                foreach ($newLines as $line) {
                    $this->renderEntry($line, $output, $filter);
                }

                if (!$follow) break;
                usleep(500000); // 500ms
            }
            /**
            fseek($handle, 0, SEEK_END);
            while (true) {
                $line = fgets($handle);
                if ($line !== false) {
                    $this->renderEntry($line, $output, $filter);
                } else {
                    usleep(200000); // 200ms
                }
            }
             */
        } else {
            while (($line = fgets($handle)) !== false) {
                $this->renderEntry($line, $output, $filter);
            }
            fclose($handle);
        }

        return Command::SUCCESS;
    }

    private function renderEntry(string $line, OutputInterface $output, ?string $filter = null): void
    {
        $entry = json_decode($line, true);
        if (!is_array($entry)) return;

        $visitorId = $entry['visitor_id'] ?? md5($entry['ip'] ?? uniqid());
        $isBot = preg_match('/bot|crawl|spider|slurp|bing/i', $entry['user_agent'] ?? '');
        $isReturning = isset($this->seenVisitors[$visitorId]);
        $this->seenVisitors[$visitorId] = true;

        if ($filter === 'bot' && !$isBot) return;
        if ($filter === 'utm' && empty($entry['utm'])) return;
        if ($filter === 'referrer' && empty($entry['referer'])) return;
        if ($filter === 'new' && $isReturning) return;
        if ($filter === 'return' && !$isReturning) return;

        $flag = $entry['country_code'] ?? '🌍';
        $ref = $entry['referer'] ?? 'direct';
        $utm = $entry['utm']['utm_campaign'] ?? '';
        $browser = $entry['browser'] ?? '';
        $os = $entry['os'] ?? '';
        $device = $entry['device_type'] ?? '';
        $city = $entry['city'] ?? '';
        $isp = $entry['isp'] ?? '';
        $visitorType = $isReturning ? 'Returning' : 'First time';
        $timestamp = $entry['date'] ?? '';

        $output->writeln("");
        $output->writeln("🕒 <info>{$timestamp}</info>");
        if ($isBot) {
            $output->writeln("🤖 <comment>BOT DETECTED:</comment> " . substr($entry['user_agent'], 0, 80));
        } else {
            $output->writeln("$flag [{$entry['country']}] <info>{$browser}</info> on <comment>{$os}</comment> | <info>{$device}</info>");
            $output->writeln("➡️  <fg=blue>{$entry['uri']}</>");
            if (!empty($utm)) {
                $output->writeln("📢 utm_campaign: <fg=yellow>{$utm}</>");
            }
            if (!empty($ref)) {
                $output->writeln("🧭 Referrer: <fg=cyan>{$ref}</>");
            }
            $output->writeln("📡 {$isp} | {$city} | <fg=magenta>{$visitorType} Visitor</>");
        }
    }
}
