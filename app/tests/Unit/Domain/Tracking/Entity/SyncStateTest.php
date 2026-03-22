<?php

declare(strict_types=1);

namespace App\Tests\Unit\Domain\Tracking\Entity;

use App\Domain\Tracking\Entity\Competition;
use App\Domain\Tracking\Entity\SyncState;
use PHPUnit\Framework\TestCase;

class SyncStateTest extends TestCase
{
    private Competition $competition;

    protected function setUp(): void
    {
        $this->competition = Competition::create('PD', 'Primera División');
    }

    public function test_creating_sync_state__should_have_null_last_synced_at(): void
    {
        $syncState = SyncState::create($this->competition);

        $this->assertNull($syncState->lastSyncedAt());
        $this->assertNotNull($syncState->id());
    }

    public function test_marking_synced__should_store_datetime(): void
    {
        $syncState = SyncState::create($this->competition);
        $now = new \DateTimeImmutable('2026-03-22 10:00:00');

        $syncState->markSynced($now);

        $this->assertSame($now, $syncState->lastSyncedAt());
    }

    public function test_is_synced_today__when_synced_today__should_return_true(): void
    {
        $syncState = SyncState::create($this->competition);
        $syncState->markSynced(new \DateTimeImmutable());

        $this->assertTrue($syncState->isSyncedToday());
    }

    public function test_is_synced_today__when_synced_yesterday__should_return_false(): void
    {
        $syncState = SyncState::create($this->competition);
        $syncState->markSynced(new \DateTimeImmutable('yesterday'));

        $this->assertFalse($syncState->isSyncedToday());
    }

    public function test_is_synced_today__when_never_synced__should_return_false(): void
    {
        $syncState = SyncState::create($this->competition);

        $this->assertFalse($syncState->isSyncedToday());
    }
}
