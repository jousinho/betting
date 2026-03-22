<?php

declare(strict_types=1);

namespace App\Infrastructure\Betting\Command;

use App\Application\Betting\Service\BetStatsService;
use App\Domain\Betting\Entity\Bet;
use App\Domain\Betting\Entity\SeasonStats;
use App\Domain\Betting\Repository\BetRepositoryInterface;
use App\Domain\Betting\Repository\SeasonStatsRepositoryInterface;
use App\Domain\Tracking\Repository\CompetitionRepositoryInterface;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: 'betting:archive-season', description: 'Archive season stats and optionally clean up transactional data')]
class ArchiveSeasonCommand extends Command
{
    public function __construct(
        private readonly CompetitionRepositoryInterface $competitionRepository,
        private readonly BetRepositoryInterface $betRepository,
        private readonly SeasonStatsRepositoryInterface $seasonStatsRepository,
        private readonly BetStatsService $betStatsService,
        private readonly EntityManagerInterface $em,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('competition', InputArgument::REQUIRED, 'Competition code (e.g. PD)')
            ->addArgument('season', InputArgument::REQUIRED, 'Season label (e.g. 2024-25)')
            ->addOption('cleanup', null, InputOption::VALUE_NONE, 'Delete transactional data after archiving (keeps competition + season_stats)');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io     = new SymfonyStyle($input, $output);
        $code   = $input->getArgument('competition');
        $season = $input->getArgument('season');

        $competition = $this->competitionRepository->findByCode($code);
        if ($competition === null) {
            $io->error(sprintf('Competition "%s" not found.', $code));
            return Command::FAILURE;
        }

        $io->section(sprintf('Archiving season "%s" for "%s"...', $season, $code));

        $bets = array_filter(
            $this->betRepository->findSettledByCompetition($competition),
            fn(Bet $b) => !$b->skipped(),
        );

        if (count($bets) === 0) {
            $io->warning('No settled bets found. Nothing to archive.');
            return Command::SUCCESS;
        }

        $statsData = $this->betStatsService->buildStatsData($bets);

        $existing = $this->seasonStatsRepository->findByCompetitionCodeAndSeason($code, $season);
        if ($existing !== null) {
            $io->warning(sprintf('Season "%s" already archived. Overwriting not supported — skipping.', $season));
            return Command::SUCCESS;
        }

        $seasonStats = SeasonStats::create($season, $code, $competition->name(), $statsData);
        $this->seasonStatsRepository->save($seasonStats);

        $io->success(sprintf('Season "%s" archived: %d bets (%d won, %d lost, %d%% rate).',
            $season,
            $statsData['total'],
            $statsData['won'],
            $statsData['lost'],
            $statsData['rate'],
        ));

        if ($input->getOption('cleanup')) {
            $this->runCleanup($io, $competition);
        }

        return Command::SUCCESS;
    }

    private function runCleanup(SymfonyStyle $io, \App\Domain\Tracking\Entity\Competition $competition): void
    {
        $io->section('Running cleanup...');

        $this->betRepository->clearByCompetition($competition);
        $io->text('Bets deleted.');

        $this->em->createQuery(
            'DELETE FROM App\Domain\Tracking\Entity\NonLeagueMatch m WHERE m.team IN (SELECT t FROM App\Domain\Tracking\Entity\Team t WHERE t.competition = :c)'
        )->execute(['c' => $competition]);
        $io->text('Non-league matches deleted.');

        $this->em->createQuery(
            'DELETE FROM App\Domain\Tracking\Entity\LeagueMatch m WHERE m.competition = :c'
        )->execute(['c' => $competition]);
        $io->text('League matches deleted.');

        $this->em->createQuery(
            'DELETE FROM App\Domain\Tracking\Entity\SyncState s WHERE s.competition = :c'
        )->execute(['c' => $competition]);
        $io->text('Sync state deleted.');

        $this->em->createQuery(
            'DELETE FROM App\Domain\Tracking\Entity\Team t WHERE t.competition = :c'
        )->execute(['c' => $competition]);
        $io->text('Teams deleted.');

        $io->success('Cleanup complete. Competition and season stats preserved.');
    }
}
