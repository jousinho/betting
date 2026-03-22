<?php

declare(strict_types=1);

namespace App\Tests\Unit\Domain\Betting\Criterion;

use App\Domain\Betting\Criterion\Over05HalfTimeCriterion;

class Over05HalfTimeCriterionTest extends BettingCriterionTestCase
{
    private Over05HalfTimeCriterion $criterion;

    protected function setUp(): void
    {
        $this->criterion = new Over05HalfTimeCriterion();
    }

    public function test_evaluating_over05ht__when_only_home_triggered__should_return_home(): void
    {
        $homeStats = $this->makeStats(matchesPlayedHome: 10, over05HtHome: 7);
        $awayStats = $this->makeStats(matchesPlayedAway: 10, over05HtAway: 4);

        $this->assertSame('home', $this->criterion->evaluate($homeStats, $awayStats));
    }

    public function test_evaluating_over05ht__when_only_away_triggered__should_return_away(): void
    {
        $homeStats = $this->makeStats(matchesPlayedHome: 10, over05HtHome: 4);
        $awayStats = $this->makeStats(matchesPlayedAway: 10, over05HtAway: 7);

        $this->assertSame('away', $this->criterion->evaluate($homeStats, $awayStats));
    }

    public function test_evaluating_over05ht__when_both_triggered__should_return_both(): void
    {
        $homeStats = $this->makeStats(matchesPlayedHome: 10, over05HtHome: 7);
        $awayStats = $this->makeStats(matchesPlayedAway: 10, over05HtAway: 7);

        $this->assertSame('both', $this->criterion->evaluate($homeStats, $awayStats));
    }

    public function test_evaluating_over05ht__when_neither_triggered__should_return_null(): void
    {
        $homeStats = $this->makeStats(matchesPlayedHome: 10, over05HtHome: 4);
        $awayStats = $this->makeStats(matchesPlayedAway: 10, over05HtAway: 4);

        $this->assertNull($this->criterion->evaluate($homeStats, $awayStats));
    }

    public function test_evaluating_over05ht__when_not_enough_matches__should_return_null(): void
    {
        $homeStats = $this->makeStats(matchesPlayedHome: 4, over05HtHome: 4);
        $awayStats = $this->makeStats(matchesPlayedAway: 4, over05HtAway: 4);

        $this->assertNull($this->criterion->evaluate($homeStats, $awayStats));
    }

    public function test_evaluating_over05ht__bet_type__should_be_over_05_ht(): void
    {
        $this->assertSame('over_05_ht', $this->criterion->betType());
    }
}
