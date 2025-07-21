<?php

namespace Beast\VisitorTrackerBundle\Command;

use Beast\VisitorTrackerBundle\Service\VisitorLogConfig;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'visitor:cleanup',
    description: 'Clean up old visitor logs'
)]
class VisitorCleanupCommand extends Command
{
    public function __construct(private readonly VisitorLogConfig $config)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('days', null, InputOption::VALUE_OPTIONAL, 'How old files must be to delete (default: 90)', 90)
            ->addOption('force', 'f', InputOption::VALUE_NONE, 'Delete without confirmation');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $days = (int) $input->getOption('days');
        $force = $input->getOption('force');
        $threshold = (new \DateTimeImmutable())->modify("-{$days} days");

        $logDir = $this->config->getLogDir();
        if (!is_dir($logDir)) {
            $io->warning("No log directory found at: $logDir");
            return Command::SUCCESS;
        }

        $deleted = 0;
        $skipped = 0;
        $files = glob($logDir . '/*.log');

        $candidates = [];
        foreach ($files as $file) {
            $basename = basename($file);
            if (preg_match('/^(\\d{4}-\\d{2}-\\d{2})\\.log$/', $basename, $m)) {
                $fileDate = \DateTimeImmutable::createFromFormat('Y-m-d', $m[1]);
                if ($fileDate < $threshold) {
                    $candidates[] = $file;
                }
            }
        }

        if (empty($candidates)) {
            $io->success("No logs older than {$days} days to clean.");
            return Command::SUCCESS;
        }

        $io->title("🧹 Visitor Log Cleanup");
        $io->listing(array_map('basename', $candidates));
        $io->text("Total: " . count($candidates) . " files older than $days days");

        if (!$force && !$io->confirm('Delete these files?', false)) {
            $io->warning('Aborted.');
            return Command::SUCCESS;
        }

        foreach ($candidates as $file) {
            if (@unlink($file)) {
                $deleted++;
            } else {
                $skipped++;
            }
        }

        $io->success("Deleted $deleted file(s).");
        if ($skipped > 0) {
            $io->warning("$skipped file(s) could not be deleted.");
        }

        return Command::SUCCESS;
    }
}

