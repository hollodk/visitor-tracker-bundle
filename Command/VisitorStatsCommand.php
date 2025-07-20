<?php

namespace App\VisitorTrackerBundle\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'visitor:stats',
    description: 'Displays visitor tracking statistics from the log file',
)]
class VisitorStatsCommand extends Command
{
    private string $logFile;

    public function __construct()
    {
        parent::__construct();
        $this->logFile = __DIR__ . '/../../../var/visitor_tracker/visits.log';
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        if (!file_exists($this->logFile)) {
            $io->error('No log file found at: ' . $this->logFile);
            return Command::FAILURE;
        }

        $lines = file($this->logFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        $total = count($lines);

        $byDate = [];
        $byCountry = [];
        $byUri = [];
        $utmSources = [];
        $utmCampaigns = [];

        foreach ($lines as $line) {
            $entry = json_decode($line, true);
            if (!is_array($entry)) continue;

            $day = substr($entry['date'], 0, 10);
            $byDate[$day] = ($byDate[$day] ?? 0) + 1;

            $byCountry[$entry['country']] = ($byCountry[$entry['country']] ?? 0) + 1;
            $byUri[$entry['uri']] = ($byUri[$entry['uri']] ?? 0) + 1;

            if (!empty($entry['utm']['utm_source'])) {
                $utmSources[$entry['utm']['utm_source']] = ($utmSources[$entry['utm']['utm_source']] ?? 0) + 1;
            }
            if (!empty($entry['utm']['utm_campaign'])) {
                $utmCampaigns[$entry['utm']['utm_campaign']] = ($utmCampaigns[$entry['utm']['utm_campaign']] ?? 0) + 1;
            }
        }

        // Output sections
        $io->title('📈 Visitor Statistics');
        $io->success("Total visits: $total");

        $io->section('🗓 Visits Per Day');
        $io->table(['Date', 'Visits'], array_map(null, array_keys($byDate), array_values($byDate)));

        $io->section('🌍 Top Countries');
        arsort($byCountry);
        $io->table(['Country', 'Count'], array_slice(array_map(null, array_keys($byCountry), array_values($byCountry)), 0, 10));

        $io->section('📣 UTM Sources');
        arsort($utmSources);
        if (!empty($utmSources)) {
            $io->table(['Source', 'Count'], array_map(null, array_keys($utmSources), array_values($utmSources)));
        } else {
            $io->text('No UTM sources found.');
        }

        $io->section('🎯 UTM Campaigns');
        arsort($utmCampaigns);
        if (!empty($utmCampaigns)) {
            $io->table(['Campaign', 'Count'], array_map(null, array_keys($utmCampaigns), array_values($utmCampaigns)));
        } else {
            $io->text('No UTM campaigns found.');
        }

        $io->section('📄 Top Visited Pages');
        arsort($byUri);
        $io->table(['URI', 'Count'], array_slice(array_map(null, array_keys($byUri), array_values($byUri)), 0, 10));

        return Command::SUCCESS;
    }
}

