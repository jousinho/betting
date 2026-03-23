<?php

declare(strict_types=1);

namespace App\Application\Tracking\Service;

use App\Application\Betting\Service\BetGeneratorService;
use App\Application\Betting\Service\BetSettlementService;
use App\Application\Betting\Service\OddsSyncService;
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
        private readonly BetSettlementService $settlementService,
        private readonly BetGeneratorService $generatorService,
        private readonly OddsSyncService $oddsSyncService,
    ) {}

    public function sync(Competition $competition, bool $force = false): void
    {
        $syncState = $this->syncStateRepository->findByCompetition($competition);

        if (!$force && $syncState !== null && $syncState->isSyncedToday()) {
            return;
        }

        $this->syncLeagueMatches($competition);
        $this->syncNonLeagueMatches();
        $this->settlementService->settleAll($competition);
        $this->generatorService->generate($competition);
        $this->oddsSyncService->syncForCompetition($competition);
        $this->updateSyncState($competition, $syncState);
    }

    private function syncLeagueMatches(Competition $competition): void
    {
        $pendingMatches = $this->leagueMatchRepository->findPendingByCompetition($competition);

        foreach ($pendingMatches as $match) {
            $result = $this->provider->fetchLeagueMatchResult($match->externalId());

            if ($result['homeGoalsFt'] === null) {
                continue;
            }

            $match->finish(
                $result['homeGoalsFt'],
                $result['awayGoalsFt'],
                $result['homeGoalsHt'] ?? 0,
                $result['awayGoalsHt'] ?? 0,
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
