<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Domain\Tracking\Entity\Competition;
use App\Domain\Tracking\Entity\SyncState;
use App\Domain\Tracking\Repository\CompetitionRepositoryInterface;
use App\Domain\Tracking\Repository\SyncStateRepositoryInterface;

class DashboardControllerTest extends FunctionalTestCase
{
    private CompetitionRepositoryInterface $competitionRepository;
    private SyncStateRepositoryInterface $syncStateRepository;

    protected function setUp(): void
    {
        parent::setUp();
        $this->competitionRepository = static::getContainer()->get(CompetitionRepositoryInterface::class);
        $this->syncStateRepository = static::getContainer()->get(SyncStateRepositoryInterface::class);
    }

    public function test_visiting_root__should_return_html_with_spinner(): void
    {
        $this->client->request('GET', '/');

        $this->assertResponseIsSuccessful();
        $this->assertStringContainsString('text/html', $this->client->getResponse()->headers->get('Content-Type'));
        $this->assertStringContainsString('spinner', $this->client->getResponse()->getContent());
        $this->assertStringContainsString("fetch('/sync')", $this->client->getResponse()->getContent());
    }

    public function test_sync_endpoint__when_not_synced_today__should_call_sync_service_and_return_ok(): void
    {
        $competition = Competition::create('PD', 'Primera División');
        $this->competitionRepository->save($competition);
        $this->entityManager->clear();

        $this->client->request('GET', '/sync');

        $this->assertResponseIsSuccessful();
        $this->assertJson($this->client->getResponse()->getContent());
        $this->assertSame('{"status":"ok"}', $this->client->getResponse()->getContent());

        $syncState = $this->syncStateRepository->findByCompetition($competition);
        $this->assertNotNull($syncState);
        $this->assertSame(date('Y-m-d'), $syncState->lastSyncedAt()->format('Y-m-d'));
    }

    public function test_sync_endpoint__when_already_synced_today__should_not_call_sync_service_and_return_ok(): void
    {
        $competition = Competition::create('PD', 'Primera División');
        $this->competitionRepository->save($competition);

        $syncState = SyncState::create($competition);
        $syncState->markSynced(new \DateTimeImmutable('today 08:00:00'));
        $this->syncStateRepository->save($syncState);
        $this->entityManager->clear();

        $this->client->request('GET', '/sync');

        $this->assertResponseIsSuccessful();
        $this->assertSame('{"status":"ok"}', $this->client->getResponse()->getContent());

        $updatedState = $this->syncStateRepository->findByCompetition($competition);
        $this->assertSame('08:00:00', $updatedState->lastSyncedAt()->format('H:i:s'));
    }

    public function test_sync_endpoint__when_no_competition_seeded__should_return_ok(): void
    {
        $this->client->request('GET', '/sync');

        $this->assertResponseIsSuccessful();
        $this->assertSame('{"status":"ok"}', $this->client->getResponse()->getContent());
    }

    public function test_dashboard__should_return_200(): void
    {
        $this->client->request('GET', '/dashboard');

        $this->assertResponseIsSuccessful();
    }
}
