<?php

declare(strict_types=1);

namespace App\Tests\Integration\Infrastructure\Tracking;

use App\Application\Tracking\Service\SeasonSeedService;
use App\Domain\Tracking\Repository\CompetitionRepositoryInterface;
use App\Domain\Tracking\Repository\FootballDataProviderInterface;
use App\Domain\Tracking\Repository\LeagueMatchRepositoryInterface;
use App\Domain\Tracking\Repository\NonLeagueMatchRepositoryInterface;
use App\Domain\Tracking\Repository\TeamRepositoryInterface;
use App\Tests\Integration\IntegrationTestCase;
use PHPUnit\Framework\MockObject\Stub;

class SeasonSeedServiceTest extends IntegrationTestCase
{
    private FootballDataProviderInterface&Stub $provider;
    private SeasonSeedService $seedService;
    private CompetitionRepositoryInterface $competitionRepository;
    private TeamRepositoryInterface $teamRepository;
    private LeagueMatchRepositoryInterface $leagueMatchRepository;
    private NonLeagueMatchRepositoryInterface $nonLeagueMatchRepository;

    protected function setUp(): void
    {
        parent::setUp();

        $this->provider = $this->createStub(FootballDataProviderInterface::class);
        static::getContainer()->set(FootballDataProviderInterface::class, $this->provider);

        $this->seedService = static::getContainer()->get(SeasonSeedService::class);
        $this->competitionRepository = static::getContainer()->get(CompetitionRepositoryInterface::class);
        $this->teamRepository = static::getContainer()->get(TeamRepositoryInterface::class);
        $this->leagueMatchRepository = static::getContainer()->get(LeagueMatchRepositoryInterface::class);
        $this->nonLeagueMatchRepository = static::getContainer()->get(NonLeagueMatchRepositoryInterface::class);
    }

    public function test_seeding_season__should_persist_all_teams_from_api_response(): void
    {
        $this->stubCompetitionAndTeams();
        $this->provider->method('fetchLeagueMatches')->willReturn([]);
        $this->provider->method('fetchNonLeagueMatches')->willReturn([]);

        $this->seedService->seed('PD');
        $this->entityManager->clear();

        $this->assertNotNull($this->teamRepository->findByExternalId(86));
        $this->assertNotNull($this->teamRepository->findByExternalId(81));
    }

    public function test_seeding_season__should_persist_all_league_matches_as_scheduled(): void
    {
        $this->stubCompetitionAndTeams();
        $this->provider->method('fetchLeagueMatches')->willReturn([
            [
                'id'          => 1001,
                'matchday'    => 1,
                'playedAt'    => '2025-08-17T18:00:00Z',
                'status'      => 'SCHEDULED',
                'homeTeamId'  => 86,
                'awayTeamId'  => 81,
                'homeGoalsFt' => null,
                'awayGoalsFt' => null,
                'homeGoalsHt' => null,
                'awayGoalsHt' => null,
            ],
        ]);
        $this->provider->method('fetchNonLeagueMatches')->willReturn([]);

        $this->seedService->seed('PD');
        $this->entityManager->clear();

        $match = $this->leagueMatchRepository->findByExternalId(1001);
        $this->assertNotNull($match);
        $this->assertSame('SCHEDULED', $match->status());
        $this->assertNull($match->homeGoalsFt());
    }

    public function test_seeding_season__should_persist_non_league_matches_for_each_team(): void
    {
        $this->stubCompetitionAndTeams();
        $this->provider->method('fetchLeagueMatches')->willReturn([]);
        $this->provider->method('fetchNonLeagueMatches')
            ->willReturnCallback(fn(int $teamId) => match ($teamId) {
                86 => [
                    [
                        'id'              => 9001,
                        'playedAt'        => '2025-10-15T20:00:00Z',
                        'status'          => 'SCHEDULED',
                        'competitionName' => 'Copa del Rey',
                    ],
                ],
                default => [],
            });

        $this->seedService->seed('PD');
        $this->entityManager->clear();

        $realMadrid = $this->teamRepository->findByExternalId(86);
        $barca = $this->teamRepository->findByExternalId(81);

        $this->assertNotNull($this->nonLeagueMatchRepository->findByExternalIdAndTeam(9001, $realMadrid));
        $this->assertNull($this->nonLeagueMatchRepository->findByExternalIdAndTeam(9001, $barca));
    }

    public function test_seeding_season__when_called_twice__should_not_duplicate_teams(): void
    {
        $this->stubCompetitionAndTeams();
        $this->provider->method('fetchLeagueMatches')->willReturn([]);
        $this->provider->method('fetchNonLeagueMatches')->willReturn([]);

        $this->seedService->seed('PD');
        $this->seedService->seed('PD');
        $this->entityManager->clear();

        $realMadrid = $this->teamRepository->findByExternalId(86);
        $this->assertNotNull($realMadrid);
        $this->assertSame('Real Madrid CF', $realMadrid->name());
    }

    public function test_seeding_season__when_called_twice__should_not_duplicate_matches(): void
    {
        $this->stubCompetitionAndTeams();
        $this->provider->method('fetchLeagueMatches')->willReturn([
            [
                'id'          => 1001,
                'matchday'    => 1,
                'playedAt'    => '2025-08-17T18:00:00Z',
                'status'      => 'SCHEDULED',
                'homeTeamId'  => 86,
                'awayTeamId'  => 81,
                'homeGoalsFt' => null,
                'awayGoalsFt' => null,
                'homeGoalsHt' => null,
                'awayGoalsHt' => null,
            ],
        ]);
        $this->provider->method('fetchNonLeagueMatches')->willReturn([]);

        $this->seedService->seed('PD');
        $this->seedService->seed('PD');
        $this->entityManager->clear();

        $competition = $this->competitionRepository->findByCode('PD');
        $this->assertCount(1, $this->entityManager->getRepository(\App\Domain\Tracking\Entity\LeagueMatch::class)->findBy(['competition' => $competition]));
    }

    private function stubCompetitionAndTeams(): void
    {
        $this->provider->method('fetchCompetition')->willReturn(['code' => 'PD', 'name' => 'Primera División']);
        $this->provider->method('fetchTeams')->willReturn([
            ['id' => 86, 'name' => 'Real Madrid CF'],
            ['id' => 81, 'name' => 'FC Barcelona'],
        ]);
    }
}
