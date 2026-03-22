<?php

declare(strict_types=1);

namespace App\Tests\Unit\Domain\Tracking\Entity;

use App\Domain\Tracking\Entity\Competition;
use App\Domain\Tracking\Entity\LeagueMatch;
use App\Domain\Tracking\Entity\Team;
use PHPUnit\Framework\TestCase;

class LeagueMatchTest extends TestCase
{
    private Competition $competition;
    private Team $homeTeam;
    private Team $awayTeam;

    protected function setUp(): void
    {
        $this->competition = Competition::create('PD', 'Primera División');
        $this->homeTeam = Team::create(86, 'Real Madrid CF', $this->competition);
        $this->awayTeam = Team::create(81, 'FC Barcelona', $this->competition);
    }

    public function test_creating_league_match__with_valid_data__should_have_scheduled_status(): void
    {
        $match = LeagueMatch::create(
            externalId: 12345,
            competition: $this->competition,
            homeTeam: $this->homeTeam,
            awayTeam: $this->awayTeam,
            matchday: 1,
            playedAt: new \DateTimeImmutable('2025-08-17 20:00:00'),
        );

        $this->assertSame(LeagueMatch::STATUS_SCHEDULED, $match->status());
        $this->assertSame(12345, $match->externalId());
        $this->assertSame(1, $match->matchday());
        $this->assertNotNull($match->id());
    }

    public function test_creating_league_match__should_have_null_scores_by_default(): void
    {
        $match = LeagueMatch::create(
            externalId: 12345,
            competition: $this->competition,
            homeTeam: $this->homeTeam,
            awayTeam: $this->awayTeam,
            matchday: 1,
            playedAt: new \DateTimeImmutable('2025-08-17 20:00:00'),
        );

        $this->assertNull($match->homeGoalsFt());
        $this->assertNull($match->awayGoalsFt());
        $this->assertNull($match->homeGoalsHt());
        $this->assertNull($match->awayGoalsHt());
    }

    public function test_finishing_league_match__with_scores__should_store_all_goals_and_set_finished_status(): void
    {
        $match = LeagueMatch::create(
            externalId: 12345,
            competition: $this->competition,
            homeTeam: $this->homeTeam,
            awayTeam: $this->awayTeam,
            matchday: 1,
            playedAt: new \DateTimeImmutable('2025-08-17 20:00:00'),
        );

        $match->finish(homeGoalsFt: 2, awayGoalsFt: 1, homeGoalsHt: 1, awayGoalsHt: 0);

        $this->assertSame(LeagueMatch::STATUS_FINISHED, $match->status());
        $this->assertSame(2, $match->homeGoalsFt());
        $this->assertSame(1, $match->awayGoalsFt());
        $this->assertSame(1, $match->homeGoalsHt());
        $this->assertSame(0, $match->awayGoalsHt());
    }
}
