<?php

declare(strict_types=1);

namespace App\Tests\Unit\Domain\Tracking\Entity;

use App\Domain\Tracking\Entity\Competition;
use App\Domain\Tracking\Entity\NonLeagueMatch;
use App\Domain\Tracking\Entity\Team;
use PHPUnit\Framework\TestCase;

class NonLeagueMatchTest extends TestCase
{
    private Team $team;

    protected function setUp(): void
    {
        $competition = Competition::create('PD', 'Primera División');
        $this->team = Team::create(86, 'Real Madrid CF', $competition);
    }

    public function test_creating_non_league_match__with_valid_data__should_have_scheduled_status(): void
    {
        $match = NonLeagueMatch::create(
            externalId: 99001,
            team: $this->team,
            playedAt: new \DateTimeImmutable('2025-10-22 21:00:00'),
            competitionName: 'Copa del Rey',
        );

        $this->assertSame(NonLeagueMatch::STATUS_SCHEDULED, $match->status());
        $this->assertSame(99001, $match->externalId());
        $this->assertSame('Copa del Rey', $match->competitionName());
        $this->assertSame($this->team, $match->team());
        $this->assertNotNull($match->id());
    }

    public function test_finishing_non_league_match__should_set_finished_status(): void
    {
        $match = NonLeagueMatch::create(
            externalId: 99001,
            team: $this->team,
            playedAt: new \DateTimeImmutable('2025-10-22 21:00:00'),
            competitionName: 'Copa del Rey',
        );

        $match->finish();

        $this->assertSame(NonLeagueMatch::STATUS_FINISHED, $match->status());
    }
}
