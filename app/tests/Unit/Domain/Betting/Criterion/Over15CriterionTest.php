<?php

declare(strict_types=1);

namespace App\Tests\Unit\Domain\Betting\Criterion;

use App\Domain\Betting\Criterion\Over15Criterion;

class Over15CriterionTest extends BettingCriterionTestCase
{
    private Over15Criterion $criterion;

    protected function setUp(): void
    {
        $this->criterion = new Over15Criterion();
    }

    public function test_evaluating_over15__when_only_home_triggered__should_return_home(): void
    {
        $homeStats = $this->makeStats(matchesPlayedHome: 10, over15Home: 7);
        $awayStats = $this->makeStats(matchesPlayedAway: 10, over15Away: 4);

        $this->assertSame('home', $this->criterion->evaluate($homeStats, $awayStats));
    }

    public function test_evaluating_over15__when_only_away_triggered__should_return_away(): void
    {
        $homeStats = $this->makeStats(matchesPlayedHome: 10, over15Home: 4);
        $awayStats = $this->makeStats(matchesPlayedAway: 10, over15Away: 7);

        $this->assertSame('away', $this->criterion->evaluate($homeStats, $awayStats));
    }

    public function test_evaluating_over15__when_both_triggered__should_return_both(): void
    {
        $homeStats = $this->makeStats(matchesPlayedHome: 10, over15Home: 7);
        $awayStats = $this->makeStats(matchesPlayedAway: 10, over15Away: 7);

        $this->assertSame('both', $this->criterion->evaluate($homeStats, $awayStats));
    }

    public function test_evaluating_over15__when_neither_triggered__should_return_null(): void
    {
        $homeStats = $this->makeStats(matchesPlayedHome: 10, over15Home: 4);
        $awayStats = $this->makeStats(matchesPlayedAway: 10, over15Away: 4);

        $this->assertNull($this->criterion->evaluate($homeStats, $awayStats));
    }

    public function test_evaluating_over15__when_not_enough_matches__should_return_null(): void
    {
        $homeStats = $this->makeStats(matchesPlayedHome: 4, over15Home: 4);
        $awayStats = $this->makeStats(matchesPlayedAway: 4, over15Away: 4);

        $this->assertNull($this->criterion->evaluate($homeStats, $awayStats));
    }

    public function test_evaluating_over15__bet_type__should_be_over_1_5(): void
    {
        $this->assertSame('over_1_5', $this->criterion->betType());
    }
}
