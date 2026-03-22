<?php

declare(strict_types=1);

namespace App\Tests\Integration\Infrastructure\Tracking;

use App\Domain\Tracking\Entity\Competition;
use App\Domain\Tracking\Entity\NonLeagueMatch;
use App\Domain\Tracking\Entity\Team;
use App\Domain\Tracking\Repository\CompetitionRepositoryInterface;
use App\Domain\Tracking\Repository\NonLeagueMatchRepositoryInterface;
use App\Domain\Tracking\Repository\TeamRepositoryInterface;
use App\Tests\Integration\IntegrationTestCase;

class DoctrineNonLeagueMatchRepositoryTest extends IntegrationTestCase
{
    private NonLeagueMatchRepositoryInterface $matchRepository;
    private CompetitionRepositoryInterface $competitionRepository;
    private TeamRepositoryInterface $teamRepository;
    private Team $team;

    protected function setUp(): void
    {
        parent::setUp();
        $this->matchRepository = static::getContainer()->get(NonLeagueMatchRepositoryInterface::class);
        $this->competitionRepository = static::getContainer()->get(CompetitionRepositoryInterface::class);
        $this->teamRepository = static::getContainer()->get(TeamRepositoryInterface::class);

        $competition = Competition::create('PD', 'Primera División');
        $this->competitionRepository->save($competition);

        $this->team = Team::create(86, 'Real Madrid CF', $competition);
        $this->teamRepository->save($this->team);
    }

    public function test_finding_pending__when_status_scheduled_and_past__should_return_them(): void
    {
        $match = NonLeagueMatch::create(
            externalId: 9001,
            team: $this->team,
            playedAt: new \DateTimeImmutable('yesterday'),
            competitionName: 'Copa del Rey',
        );
        $this->matchRepository->save($match);
        $this->entityManager->clear();

        $pending = $this->matchRepository->findPending();

        $this->assertCount(1, $pending);
        $this->assertSame(9001, $pending[0]->externalId());
    }

    public function test_finding_pending__when_already_finished__should_not_return_them(): void
    {
        $match = NonLeagueMatch::create(
            externalId: 9002,
            team: $this->team,
            playedAt: new \DateTimeImmutable('yesterday'),
            competitionName: 'Copa del Rey',
        );
        $match->finish();
        $this->matchRepository->save($match);
        $this->entityManager->clear();

        $pending = $this->matchRepository->findPending();

        $this->assertCount(0, $pending);
    }
}
