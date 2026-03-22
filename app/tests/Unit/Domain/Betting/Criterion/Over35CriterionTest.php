<?php

declare(strict_types=1);

namespace App\Tests\Unit\Domain\Betting\Criterion;

use App\Domain\Betting\Criterion\Over35Criterion;

class Over35CriterionTest extends BettingCriterionTestCase
{
    private Over35Criterion $criterion;

    protected function setUp(): void
    {
        $this->criterion = new Over35Criterion();
    }

    public function test_evaluating_over35__when_only_home_triggered__should_return_home(): void
    {
        $homeStats = $this->makeStats(matchesPlayedHome: 10, over35Home: 5);
        $awayStats = $this->makeStats(matchesPlayedAway: 10, over35Away: 3);

        $this->assertSame('home', $this->criterion->evaluate($homeStats, $awayStats));
    }

    public function test_evaluating_over35__when_only_away_triggered__should_return_away(): void
    {
        $homeStats = $this->makeStats(matchesPlayedHome: 10, over35Home: 3);
        $awayStats = $this->makeStats(matchesPlayedAway: 10, over35Away: 5);

        $this->assertSame('away', $this->criterion->evaluate($homeStats, $awayStats));
    }

    public function test_evaluating_over35__when_both_triggered__should_return_both(): void
    {
        $homeStats = $this->makeStats(matchesPlayedHome: 10, over35Home: 5);
        $awayStats = $this->makeStats(matchesPlayedAway: 10, over35Away: 5);

        $this->assertSame('both', $this->criterion->evaluate($homeStats, $awayStats));
    }

    public function test_evaluating_over35__when_neither_triggered__should_return_null(): void
    {
        $homeStats = $this->makeStats(matchesPlayedHome: 10, over35Home: 3);
        $awayStats = $this->makeStats(matchesPlayedAway: 10, over35Away: 3);

        $this->assertNull($this->criterion->evaluate($homeStats, $awayStats));
    }

    public function test_evaluating_over35__when_not_enough_matches__should_return_null(): void
    {
        $homeStats = $this->makeStats(matchesPlayedHome: 4, over35Home: 4);
        $awayStats = $this->makeStats(matchesPlayedAway: 4, over35Away: 4);

        $this->assertNull($this->criterion->evaluate($homeStats, $awayStats));
    }

    public function test_evaluating_over35__bet_type__should_be_over_3_5(): void
    {
        $this->assertSame('over_3_5', $this->criterion->betType());
    }
}
