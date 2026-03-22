<?php

declare(strict_types=1);

namespace App\Tests\Integration\Infrastructure\Tracking;

use App\Domain\Tracking\Entity\Competition;
use App\Domain\Tracking\Entity\SyncState;
use App\Domain\Tracking\Repository\CompetitionRepositoryInterface;
use App\Domain\Tracking\Repository\SyncStateRepositoryInterface;
use App\Tests\Integration\IntegrationTestCase;

class DoctrineSyncStateRepositoryTest extends IntegrationTestCase
{
    private SyncStateRepositoryInterface $syncStateRepository;
    private CompetitionRepositoryInterface $competitionRepository;

    protected function setUp(): void
    {
        parent::setUp();
        $this->syncStateRepository = static::getContainer()->get(SyncStateRepositoryInterface::class);
        $this->competitionRepository = static::getContainer()->get(CompetitionRepositoryInterface::class);
    }

    public function test_saving_sync_state__should_be_retrievable_by_competition(): void
    {
        $competition = Competition::create('PD', 'Primera División');
        $this->competitionRepository->save($competition);

        $syncState = SyncState::create($competition);
        $this->syncStateRepository->save($syncState);
        $this->entityManager->clear();

        $found = $this->syncStateRepository->findByCompetition($competition);

        $this->assertNotNull($found);
        $this->assertNull($found->lastSyncedAt());
    }

    public function test_saving_sync_state__when_updated__should_persist_new_last_synced_at(): void
    {
        $competition = Competition::create('PD', 'Primera División');
        $this->competitionRepository->save($competition);

        $syncState = SyncState::create($competition);
        $this->syncStateRepository->save($syncState);

        $now = new \DateTimeImmutable('2026-03-22 10:00:00');
        $syncState->markSynced($now);
        $this->syncStateRepository->save($syncState);
        $this->entityManager->clear();

        $found = $this->syncStateRepository->findByCompetition($competition);

        $this->assertNotNull($found);
        $this->assertSame('2026-03-22', $found->lastSyncedAt()->format('Y-m-d'));
    }
}
