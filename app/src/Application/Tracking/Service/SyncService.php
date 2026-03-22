<?php

declare(strict_types=1);

namespace App\Application\Tracking\Service;

use App\Domain\Tracking\Entity\Competition;
use App\Domain\Tracking\Entity\SyncState;
use App\Domain\Tracking\Repository\FootballDataProviderInterface;
use App\Domain\Tracking\Repository\LeagueMatchRepositoryInterface;
use App\Domain\Tracking\Repository\NonLeagueMatchRepositoryInterface;
use App\Domain\Tracking\Repository\SyncStateRepositoryInterface;

class SyncService
{
    public function __construct(
        private readonly FootballDataProviderInterface $provider,
        private readonly LeagueMatchRepositoryInterface $leagueMatchRepository,
        private readonly NonLeagueMatchRepositoryInterface $nonLeagueMatchRepository,
        private readonly SyncStateRepositoryInterface $syncStateRepository,
    ) {}

    public function sync(Competition $competition): void
    {
        $syncState = $this->syncStateRepository->findByCompetition($competition);

        if ($syncState !== null && $syncState->isSyncedToday()) {
            return;
        }

        $this->syncLeagueMatches($competition);
        $this->syncNonLeagueMatches();
        $this->updateSyncState($competition, $syncState);
    }

    private function syncLeagueMatches(Competition $competition): void
    {
        foreach ($this->leagueMatchRepository->findPendingByCompetition($competition) as $match) {
            $result = $this->provider->fetchLeagueMatchResult($match->externalId());
            $match->finish(
                $result['homeGoalsFt'],
                $result['awayGoalsFt'],
                $result['homeGoalsHt'],
                $result['awayGoalsHt'],
            );
            $this->leagueMatchRepository->save($match);
        }
    }

    private function syncNonLeagueMatches(): void
    {
        foreach ($this->nonLeagueMatchRepository->findPending() as $match) {
            $match->finish();
            $this->nonLeagueMatchRepository->save($match);
        }
    }

    private function updateSyncState(Competition $competition, ?SyncState $syncState): void
    {
        if ($syncState === null) {
            $syncState = SyncState::create($competition);
        }

        $syncState->markSynced(new \DateTimeImmutable());
        $this->syncStateRepository->save($syncState);
    }
}
