<?php

declare(strict_types=1);

namespace App\Tests\Unit\Domain\Betting\Criterion;

use App\Domain\Betting\Criterion\Under25Criterion;

class Under25CriterionTest extends BettingCriterionTestCase
{
    private Under25Criterion $criterion;

    protected function setUp(): void
    {
        $this->criterion = new Under25Criterion();
    }

    public function test_evaluating_under25__when_only_home_triggered__should_return_home(): void
    {
        // home: (10-4)/10 = 0.60 >= 0.60; away: (10-7)/10 = 0.30 < 0.60
        $homeStats = $this->makeStats(matchesPlayedHome: 10, over25Home: 4);
        $awayStats = $this->makeStats(matchesPlayedAway: 10, over25Away: 7);

        $this->assertSame('home', $this->criterion->evaluate($homeStats, $awayStats));
    }

    public function test_evaluating_under25__when_only_away_triggered__should_return_away(): void
    {
        // home: (10-7)/10 = 0.30; away: (10-4)/10 = 0.60
        $homeStats = $this->makeStats(matchesPlayedHome: 10, over25Home: 7);
        $awayStats = $this->makeStats(matchesPlayedAway: 10, over25Away: 4);

        $this->assertSame('away', $this->criterion->evaluate($homeStats, $awayStats));
    }

    public function test_evaluating_under25__when_both_triggered__should_return_both(): void
    {
        $homeStats = $this->makeStats(matchesPlayedHome: 10, over25Home: 4);
        $awayStats = $this->makeStats(matchesPlayedAway: 10, over25Away: 4);

        $this->assertSame('both', $this->criterion->evaluate($homeStats, $awayStats));
    }

    public function test_evaluating_under25__when_neither_triggered__should_return_null(): void
    {
        $homeStats = $this->makeStats(matchesPlayedHome: 10, over25Home: 7);
        $awayStats = $this->makeStats(matchesPlayedAway: 10, over25Away: 7);

        $this->assertNull($this->criterion->evaluate($homeStats, $awayStats));
    }

    public function test_evaluating_under25__when_not_enough_matches__should_return_null(): void
    {
        $homeStats = $this->makeStats(matchesPlayedHome: 4, over25Home: 0);
        $awayStats = $this->makeStats(matchesPlayedAway: 4, over25Away: 0);

        $this->assertNull($this->criterion->evaluate($homeStats, $awayStats));
    }

    public function test_evaluating_under25__bet_type__should_be_under_2_5(): void
    {
        $this->assertSame('under_2_5', $this->criterion->betType());
    }
}
