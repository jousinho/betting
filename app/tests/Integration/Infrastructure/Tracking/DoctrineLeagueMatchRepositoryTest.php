<?php

declare(strict_types=1);

namespace App\Tests\Integration\Infrastructure\Tracking;

use App\Domain\Tracking\Entity\Competition;
use App\Domain\Tracking\Entity\LeagueMatch;
use App\Domain\Tracking\Entity\Team;
use App\Domain\Tracking\Repository\CompetitionRepositoryInterface;
use App\Domain\Tracking\Repository\LeagueMatchRepositoryInterface;
use App\Domain\Tracking\Repository\TeamRepositoryInterface;
use App\Tests\Integration\IntegrationTestCase;

class DoctrineLeagueMatchRepositoryTest extends IntegrationTestCase
{
    private LeagueMatchRepositoryInterface $matchRepository;
    private CompetitionRepositoryInterface $competitionRepository;
    private TeamRepositoryInterface $teamRepository;
    private Competition $competition;
    private Team $homeTeam;
    private Team $awayTeam;

    protected function setUp(): void
    {
        parent::setUp();
        $this->matchRepository = static::getContainer()->get(LeagueMatchRepositoryInterface::class);
        $this->competitionRepository = static::getContainer()->get(CompetitionRepositoryInterface::class);
        $this->teamRepository = static::getContainer()->get(TeamRepositoryInterface::class);

        $this->competition = Competition::create('PD', 'Primera División');
        $this->competitionRepository->save($this->competition);

        $this->homeTeam = Team::create(86, 'Real Madrid CF', $this->competition);
        $this->awayTeam = Team::create(81, 'FC Barcelona', $this->competition);
        $this->teamRepository->save($this->homeTeam);
        $this->teamRepository->save($this->awayTeam);
    }

    public function test_finding_pending_matches__when_match_is_scheduled_and_past__should_return_it(): void
    {
        $match = LeagueMatch::create(
            externalId: 1001,
            competition: $this->competition,
            homeTeam: $this->homeTeam,
            awayTeam: $this->awayTeam,
            matchday: 1,
            playedAt: new \DateTimeImmutable('yesterday'),
        );
        $this->matchRepository->save($match);
        $this->entityManager->clear();

        $pending = $this->matchRepository->findPendingByCompetition($this->competition);

        $this->assertCount(1, $pending);
        $this->assertSame(1001, $pending[0]->externalId());
    }

    public function test_finding_pending_matches__when_match_is_scheduled_but_future__should_not_return_it(): void
    {
        $match = LeagueMatch::create(
            externalId: 1002,
            competition: $this->competition,
            homeTeam: $this->homeTeam,
            awayTeam: $this->awayTeam,
            matchday: 2,
            playedAt: new \DateTimeImmutable('+7 days'),
        );
        $this->matchRepository->save($match);
        $this->entityManager->clear();

        $pending = $this->matchRepository->findPendingByCompetition($this->competition);

        $this->assertCount(0, $pending);
    }

    public function test_finding_pending_matches__when_match_is_already_finished__should_not_return_it(): void
    {
        $match = LeagueMatch::create(
            externalId: 1003,
            competition: $this->competition,
            homeTeam: $this->homeTeam,
            awayTeam: $this->awayTeam,
            matchday: 3,
            playedAt: new \DateTimeImmutable('yesterday'),
        );
        $match->finish(2, 1, 1, 0);
        $this->matchRepository->save($match);
        $this->entityManager->clear();

        $pending = $this->matchRepository->findPendingByCompetition($this->competition);

        $this->assertCount(0, $pending);
    }
}
