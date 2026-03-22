<?php

declare(strict_types=1);

namespace App\Tests\Integration\Infrastructure\Tracking;

use App\Application\Tracking\Service\SyncService;
use App\Domain\Tracking\Entity\Competition;
use App\Domain\Tracking\Entity\LeagueMatch;
use App\Domain\Tracking\Entity\NonLeagueMatch;
use App\Domain\Tracking\Entity\SyncState;
use App\Domain\Tracking\Entity\Team;
use App\Domain\Tracking\Repository\CompetitionRepositoryInterface;
use App\Domain\Tracking\Repository\FootballDataProviderInterface;
use App\Domain\Tracking\Repository\LeagueMatchRepositoryInterface;
use App\Domain\Tracking\Repository\NonLeagueMatchRepositoryInterface;
use App\Domain\Tracking\Repository\SyncStateRepositoryInterface;
use App\Domain\Tracking\Repository\TeamRepositoryInterface;
use App\Tests\Integration\IntegrationTestCase;
use PHPUnit\Framework\MockObject\MockObject;

class SyncServiceTest extends IntegrationTestCase
{
    private FootballDataProviderInterface&MockObject $provider;
    private SyncService $syncService;
    private Competition $competition;
    private Team $homeTeam;
    private Team $awayTeam;
    private CompetitionRepositoryInterface $competitionRepository;
    private TeamRepositoryInterface $teamRepository;
    private LeagueMatchRepositoryInterface $leagueMatchRepository;
    private NonLeagueMatchRepositoryInterface $nonLeagueMatchRepository;
    private SyncStateRepositoryInterface $syncStateRepository;

    protected function setUp(): void
    {
        parent::setUp();

        $this->provider = $this->createMock(FootballDataProviderInterface::class);
        static::getContainer()->set(FootballDataProviderInterface::class, $this->provider);

        $this->syncService = static::getContainer()->get(SyncService::class);
        $this->competitionRepository = static::getContainer()->get(CompetitionRepositoryInterface::class);
        $this->teamRepository = static::getContainer()->get(TeamRepositoryInterface::class);
        $this->leagueMatchRepository = static::getContainer()->get(LeagueMatchRepositoryInterface::class);
        $this->nonLeagueMatchRepository = static::getContainer()->get(NonLeagueMatchRepositoryInterface::class);
        $this->syncStateRepository = static::getContainer()->get(SyncStateRepositoryInterface::class);

        $this->competition = Competition::create('PD', 'Primera División');
        $this->competitionRepository->save($this->competition);

        $this->homeTeam = Team::create(86, 'Real Madrid CF', $this->competition);
        $this->awayTeam = Team::create(81, 'FC Barcelona', $this->competition);
        $this->teamRepository->save($this->homeTeam);
        $this->teamRepository->save($this->awayTeam);
    }

    public function test_syncing__when_no_pending_matches__should_not_call_api(): void
    {
        $this->provider->expects($this->never())->method('fetchLeagueMatches');

        $this->syncService->sync($this->competition);
    }

    public function test_syncing__when_league_match_is_pending__should_update_scores_and_set_finished(): void
    {
        $this->createPastLeagueMatch(1001);

        $this->provider->expects($this->once())
            ->method('fetchLeagueMatches')
            ->with('PD')
            ->willReturn([$this->matchResult(1001, 2, 1, 1, 0)]);

        $this->syncService->sync($this->competition);
        $this->entityManager->clear();

        $updated = $this->leagueMatchRepository->findByExternalId(1001);
        $this->assertSame('FINISHED', $updated->status());
        $this->assertSame(2, $updated->homeGoalsFt());
        $this->assertSame(1, $updated->awayGoalsFt());
        $this->assertSame(1, $updated->homeGoalsHt());
        $this->assertSame(0, $updated->awayGoalsHt());
    }

    public function test_syncing__when_non_league_match_is_pending__should_set_finished_without_scores(): void
    {
        $nlMatch = NonLeagueMatch::create(9001, $this->homeTeam, new \DateTimeImmutable('yesterday'), 'Copa del Rey');
        $this->nonLeagueMatchRepository->save($nlMatch);

        $this->provider->expects($this->never())->method('fetchLeagueMatches');

        $this->syncService->sync($this->competition);
        $this->entityManager->clear();

        $updated = $this->nonLeagueMatchRepository->findByExternalIdAndTeam(9001, $this->homeTeam);
        $this->assertSame('FINISHED', $updated->status());
    }

    public function test_syncing__when_multiple_pending_matches__should_update_all_of_them(): void
    {
        $this->createPastLeagueMatch(1001);
        $this->createPastLeagueMatch(1002, homeId: 81, awayId: 86);

        $this->provider->expects($this->once())
            ->method('fetchLeagueMatches')
            ->willReturn([
                $this->matchResult(1001, 1, 0, 1, 0),
                $this->matchResult(1002, 0, 1, 0, 0),
            ]);

        $this->syncService->sync($this->competition);
        $this->entityManager->clear();

        $this->assertSame('FINISHED', $this->leagueMatchRepository->findByExternalId(1001)->status());
        $this->assertSame('FINISHED', $this->leagueMatchRepository->findByExternalId(1002)->status());
    }

    public function test_syncing__when_already_synced_today__should_skip_and_not_call_api(): void
    {
        $this->createPastLeagueMatch(1001);

        $syncState = SyncState::create($this->competition);
        $syncState->markSynced(new \DateTimeImmutable());
        $this->syncStateRepository->save($syncState);

        $this->provider->expects($this->never())->method('fetchLeagueMatches');

        $this->syncService->sync($this->competition);
    }

    public function test_syncing__when_never_synced__should_sync_and_save_sync_state(): void
    {
        $this->provider->expects($this->never())->method('fetchLeagueMatches');

        $this->syncService->sync($this->competition);
        $this->entityManager->clear();

        $syncState = $this->syncStateRepository->findByCompetition($this->competition);
        $this->assertNotNull($syncState);
        $this->assertNotNull($syncState->lastSyncedAt());
        $this->assertSame(date('Y-m-d'), $syncState->lastSyncedAt()->format('Y-m-d'));
    }

    public function test_syncing__when_last_sync_was_yesterday__should_sync_and_update_sync_state(): void
    {
        $this->provider->expects($this->never())->method('fetchLeagueMatches');

        $syncState = SyncState::create($this->competition);
        $syncState->markSynced(new \DateTimeImmutable('yesterday'));
        $this->syncStateRepository->save($syncState);

        $this->syncService->sync($this->competition);
        $this->entityManager->clear();

        $updated = $this->syncStateRepository->findByCompetition($this->competition);
        $this->assertSame(date('Y-m-d'), $updated->lastSyncedAt()->format('Y-m-d'));
    }

    private function createPastLeagueMatch(int $externalId, int $homeId = 86, int $awayId = 81): LeagueMatch
    {
        $home = $homeId === 86 ? $this->homeTeam : $this->awayTeam;
        $away = $awayId === 81 ? $this->awayTeam : $this->homeTeam;

        $match = LeagueMatch::create(
            externalId: $externalId,
            competition: $this->competition,
            homeTeam: $home,
            awayTeam: $away,
            matchday: 1,
            playedAt: new \DateTimeImmutable('yesterday'),
        );
        $this->leagueMatchRepository->save($match);

        return $match;
    }

    private function matchResult(int $id, int $hFt, int $aFt, int $hHt, int $aHt): array
    {
        return [
            'id'          => $id,
            'matchday'    => 1,
            'playedAt'    => 'yesterday',
            'status'      => 'FINISHED',
            'homeTeamId'  => 86,
            'awayTeamId'  => 81,
            'homeGoalsFt' => $hFt,
            'awayGoalsFt' => $aFt,
            'homeGoalsHt' => $hHt,
            'awayGoalsHt' => $aHt,
        ];
    }
}
