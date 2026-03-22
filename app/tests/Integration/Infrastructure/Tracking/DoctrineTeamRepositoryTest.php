<?php

declare(strict_types=1);

namespace App\Tests\Integration\Infrastructure\Tracking;

use App\Domain\Tracking\Entity\Competition;
use App\Domain\Tracking\Entity\Team;
use App\Domain\Tracking\Repository\CompetitionRepositoryInterface;
use App\Domain\Tracking\Repository\TeamRepositoryInterface;
use App\Tests\Integration\IntegrationTestCase;

class DoctrineTeamRepositoryTest extends IntegrationTestCase
{
    private TeamRepositoryInterface $teamRepository;
    private CompetitionRepositoryInterface $competitionRepository;

    protected function setUp(): void
    {
        parent::setUp();
        $this->teamRepository = static::getContainer()->get(TeamRepositoryInterface::class);
        $this->competitionRepository = static::getContainer()->get(CompetitionRepositoryInterface::class);
    }

    public function test_saving_team__should_persist_and_be_retrievable_by_external_id(): void
    {
        $competition = Competition::create('PD', 'Primera División');
        $this->competitionRepository->save($competition);

        $team = Team::create(86, 'Real Madrid CF', $competition);
        $this->teamRepository->save($team);
        $this->entityManager->clear();

        $found = $this->teamRepository->findByExternalId(86);

        $this->assertNotNull($found);
        $this->assertSame(86, $found->externalId());
        $this->assertSame('Real Madrid CF', $found->name());
    }

    public function test_saving_team__when_already_exists__should_update_name(): void
    {
        $competition = Competition::create('PD', 'Primera División');
        $this->competitionRepository->save($competition);

        $team = Team::create(86, 'Real Madrid CF', $competition);
        $this->teamRepository->save($team);

        $team->setName('Real Madrid');
        $this->teamRepository->save($team);
        $this->entityManager->clear();

        $found = $this->teamRepository->findByExternalId(86);

        $this->assertNotNull($found);
        $this->assertSame('Real Madrid', $found->name());
    }
}
