<?php

declare(strict_types=1);

namespace App\Infrastructure\Betting\Command;

use App\Application\Betting\Service\HistoricalSeasonBacktestService;
use App\Domain\Betting\Entity\SeasonStats;
use App\Domain\Betting\Repository\SeasonStatsRepositoryInterface;
use App\Domain\Tracking\Repository\FootballDataProviderInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: 'betting:seed-historical-season', description: 'Fetch a past season from football-data.org, run a backtest and save aggregated stats')]
class SeedHistoricalSeasonCommand extends Command
{
    public function __construct(
        private readonly FootballDataProviderInterface $footballDataProvider,
        private readonly SeasonStatsRepositoryInterface $seasonStatsRepository,
        private readonly HistoricalSeasonBacktestService $backtestService,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('competition', InputArgument::REQUIRED, 'Competition code (e.g. PD)')
            ->addArgument('year', InputArgument::REQUIRED, 'Start year of the season (e.g. 2023 for 2023-24)')
            ->addArgument('label', InputArgument::REQUIRED, 'Season label to store (e.g. 2023-24)');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io          = new SymfonyStyle($input, $output);
        $code        = $input->getArgument('competition');
        $year        = (int) $input->getArgument('year');
        $label       = $input->getArgument('label');

        $existing = $this->seasonStatsRepository->findByCompetitionCodeAndSeason($code, $label);
        if ($existing !== null) {
            $io->error(sprintf('Season "%s" for "%s" is already archived.', $label, $code));
            return Command::FAILURE;
        }

        $io->section(sprintf('Fetching competition info for "%s"...', $code));
        $competitionData = $this->footballDataProvider->fetchCompetition($code);

        $io->section(sprintf('Fetching matches for %s season %d...', $code, $year));
        $matches = $this->footballDataProvider->fetchLeagueMatchesBySeason($code, $year);

        $finished = count(array_filter($matches, fn($m) => $m['status'] === 'FINISHED'));
        $io->text(sprintf('Found %d matches (%d finished).', count($matches), $finished));

        if ($finished === 0) {
            $io->warning('No finished matches found. Nothing to archive.');
            return Command::SUCCESS;
        }

        $io->section('Running backtest...');
        $statsData = $this->backtestService->process($matches);

        $io->section('Saving to season_stats...');
        $seasonStats = SeasonStats::create($label, $code, $competitionData['name'], $statsData);
        $this->seasonStatsRepository->save($seasonStats);

        $io->success(sprintf(
            'Season "%s" archived: %d bets (%d won, %d lost, %d%% hit rate).',
            $label,
            $statsData['total'],
            $statsData['won'],
            $statsData['lost'],
            $statsData['rate'],
        ));

        return Command::SUCCESS;
    }
}
