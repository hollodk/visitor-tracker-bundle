<?php

namespace Beast\VisitorTrackerBundle\Command;

use Beast\VisitorTrackerBundle\Service\VisitorLogFetcher;
use Beast\VisitorTrackerBundle\Service\VisitorRenderHelper;
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
    public function __construct(private VisitorLogFetcher $fetcher, private VisitorRenderHelper $render) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $logData = $this->fetcher->fetch([
            'from' => '-30 days',
            'to' => 'now',
        ]);

        $parsed = $logData['parsed'];

        if (empty($parsed)) {
            $io->warning('No log entries found for given criteria.');
            return Command::SUCCESS;
        }

        $this->render->title($io, '📈 Visitor Statistics (Last 30 Days)');
        $this->render->success($io, "Total visits: {$parsed['total']}");
        $this->render->line($io, "🧍 Unique visitors: {$parsed['unique']}");
        $this->render->line($io, "🔁 Returning visitors: {$parsed['returning']}");

        $this->render->barChart($io, '📊 Hourly Traffic (last 24h)', $parsed['byHour'], 40, 24);
        $this->render->barChart($io, '🌍 Country Traffic Chart', $parsed['byCountry'], 40, 10);
        $this->render->barChart($io, '📱 Devices in Use', $parsed['byDevice'], 30, 10);

        $this->render->barChart($io, '📅 Daily Unique Visitors', $parsed['daily_uniques'], 40, 10);
        $this->render->barChart($io, '🔁 Daily Returning Visitors', $parsed['daily_returning'], 40, 10);

        $this->render->barChart($io, '📊 Weekly Unique Visitors', $parsed['weekly_uniques'], 40, 6);
        $this->render->barChart($io, '🔄 Weekly Returning Visitors', $parsed['weekly_returning'], 40, 6);

        $this->render->table($io, '🗓  Visits Per Day', $parsed['byDate'], ['Date', 'Visits']);
        $this->render->table($io, '⏱  Visits by Hour (last 24h)', $parsed['byHour'], ['Hour', 'Visits']);
        $this->render->table($io, '🌍 Top Countries', $parsed['byCountry'], ['Country', 'Count'], 10);
        $this->render->table($io, '🏙️ Top Cities', $parsed['byCity'], ['City', 'Count'], 10);
        $this->render->table($io, '🧑‍💻 Browsers', $parsed['byBrowser'], ['Browser', 'Count'], 10);
        $this->render->table($io, '💻 Operating Systems', $parsed['byOS'], ['OS', 'Count'], 10);
        $this->render->table($io, '📱 Device Types', $parsed['byDevice'], ['Device', 'Count']);
        $this->render->table($io, '📣 UTM Sources', $parsed['utmSources'], ['Source', 'Count']);
        $this->render->table($io, '🎯 UTM Campaigns', $parsed['utmCampaigns'], ['Campaign', 'Count']);
        $this->render->table($io, '🔗 Referrers', $parsed['byReferrer'], ['Referrer', 'Count'], 10);
        $this->render->table($io, '📄 Top Visited Pages', $parsed['byUri'], ['URI', 'Count'], 10);

        $io->note("🤖 Bot visits: {$parsed['bots']}");

        return Command::SUCCESS;
    }
}
