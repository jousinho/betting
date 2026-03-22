<?php

declare(strict_types=1);

namespace App\Tests\Unit\Domain\Betting\Criterion;

use App\Domain\Betting\Criterion\Over25Criterion;

class Over25CriterionTest extends BettingCriterionTestCase
{
    private Over25Criterion $criterion;

    protected function setUp(): void
    {
        $this->criterion = new Over25Criterion();
    }

    public function test_evaluating_over25__when_only_home_triggered__should_return_home(): void
    {
        // home: 6/8 = 0.75 >= 0.75; away: 4/8 = 0.50 < 0.625
        $homeStats = $this->makeStats(matchesPlayedHome: 8, over25Home: 6);
        $awayStats = $this->makeStats(matchesPlayedAway: 8, over25Away: 4);

        $this->assertSame('home', $this->criterion->evaluate($homeStats, $awayStats));
    }

    public function test_evaluating_over25__when_only_away_triggered__should_return_away(): void
    {
        // home: 4/8 = 0.50 < 0.75; away: 5/8 = 0.625 >= 0.625
        $homeStats = $this->makeStats(matchesPlayedHome: 8, over25Home: 4);
        $awayStats = $this->makeStats(matchesPlayedAway: 8, over25Away: 5);

        $this->assertSame('away', $this->criterion->evaluate($homeStats, $awayStats));
    }

    public function test_evaluating_over25__when_both_triggered__should_return_both(): void
    {
        // home: 6/8 = 0.75; away: 5/8 = 0.625
        $homeStats = $this->makeStats(matchesPlayedHome: 8, over25Home: 6);
        $awayStats = $this->makeStats(matchesPlayedAway: 8, over25Away: 5);

        $this->assertSame('both', $this->criterion->evaluate($homeStats, $awayStats));
    }

    public function test_evaluating_over25__when_neither_triggered__should_return_null(): void
    {
        $homeStats = $this->makeStats(matchesPlayedHome: 8, over25Home: 3);
        $awayStats = $this->makeStats(matchesPlayedAway: 8, over25Away: 3);

        $this->assertNull($this->criterion->evaluate($homeStats, $awayStats));
    }

    public function test_evaluating_over25__when_not_enough_matches__should_return_null(): void
    {
        $homeStats = $this->makeStats(matchesPlayedHome: 4, over25Home: 4);
        $awayStats = $this->makeStats(matchesPlayedAway: 4, over25Away: 4);

        $this->assertNull($this->criterion->evaluate($homeStats, $awayStats));
    }

    public function test_evaluating_over25__bet_type__should_be_over_2_5(): void
    {
        $this->assertSame('over_2_5', $this->criterion->betType());
    }
}
