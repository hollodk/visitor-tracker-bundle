<?php

namespace Beast\VisitorTrackerBundle\Command;

use Beast\VisitorTrackerBundle\Service\VisitorLogMaintenance;
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
    public function __construct(private VisitorLogMaintenance $maintenance)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('days', null, InputOption::VALUE_OPTIONAL, 'How old files must be to delete (default: 90)', 90)
            ->addOption('force', 'f', InputOption::VALUE_NONE, 'Delete without confirmation')
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Simulate only — show what would be deleted');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $days = (int) $input->getOption('days');
        $force = $input->getOption('force');
        $dryRun = $input->getOption('dry-run');

        $candidates = $this->maintenance->findOldLogFiles($days);

        if (empty($candidates)) {
            $io->success("No logs older than {$days} days to clean.");
            return 2;
        }

        $totalSize = $this->maintenance->getTotalSize($candidates);
        $io->title("🧹 Visitor Log Cleanup");
        $io->listing(array_map('basename', $candidates));
        $io->text("Total: " . count($candidates) . " file(s), " . round($totalSize / 1024 / 1024, 2) . " MB");

        if ($dryRun) {
            $io->info("Dry-run mode — no files were deleted.");
            return 0;
        }

        if (!$force && !$io->confirm('Delete these files?', false)) {
            $io->warning('Aborted by user.');
            return 3;
        }

        $result = $this->maintenance->deleteFiles($candidates);
        $deleted = count($result['deleted']);
        $failed = count($result['failed']);

        $io->success("Deleted {$deleted} " . ($deleted === 1 ? "file" : "files") . ".");
        if ($failed > 0) {
            $io->warning("{$failed} file(s) could not be deleted.");
            return 4;
        }

        return 0;
    }
}
