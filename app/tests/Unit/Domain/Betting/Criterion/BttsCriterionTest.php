<?php

declare(strict_types=1);

namespace App\Tests\Unit\Domain\Betting\Criterion;

use App\Domain\Betting\Criterion\BttsCriterion;

class BttsCriterionTest extends BettingCriterionTestCase
{
    private BttsCriterion $criterion;

    protected function setUp(): void
    {
        $this->criterion = new BttsCriterion();
    }

    public function test_evaluating_btts__when_only_home_triggered__should_return_home(): void
    {
        $homeStats = $this->makeStats(matchesPlayedHome: 10, bttsHome: 7);
        $awayStats = $this->makeStats(matchesPlayedAway: 10, bttsAway: 3);

        $this->assertSame('home', $this->criterion->evaluate($homeStats, $awayStats));
    }

    public function test_evaluating_btts__when_only_away_triggered__should_return_away(): void
    {
        $homeStats = $this->makeStats(matchesPlayedHome: 10, bttsHome: 3);
        $awayStats = $this->makeStats(matchesPlayedAway: 10, bttsAway: 7);

        $this->assertSame('away', $this->criterion->evaluate($homeStats, $awayStats));
    }

    public function test_evaluating_btts__when_both_triggered__should_return_both(): void
    {
        $homeStats = $this->makeStats(matchesPlayedHome: 10, bttsHome: 7);
        $awayStats = $this->makeStats(matchesPlayedAway: 10, bttsAway: 7);

        $this->assertSame('both', $this->criterion->evaluate($homeStats, $awayStats));
    }

    public function test_evaluating_btts__when_neither_triggered__should_return_null(): void
    {
        $homeStats = $this->makeStats(matchesPlayedHome: 10, bttsHome: 3);
        $awayStats = $this->makeStats(matchesPlayedAway: 10, bttsAway: 3);

        $this->assertNull($this->criterion->evaluate($homeStats, $awayStats));
    }

    public function test_evaluating_btts__when_not_enough_matches__should_return_null(): void
    {
        $homeStats = $this->makeStats(matchesPlayedHome: 4, bttsHome: 4);
        $awayStats = $this->makeStats(matchesPlayedAway: 4, bttsAway: 4);

        $this->assertNull($this->criterion->evaluate($homeStats, $awayStats));
    }

    public function test_evaluating_btts__bet_type__should_be_btts(): void
    {
        $this->assertSame('btts', $this->criterion->betType());
    }
}
