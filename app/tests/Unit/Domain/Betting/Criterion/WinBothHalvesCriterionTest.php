<?php

declare(strict_types=1);

namespace App\Tests\Unit\Domain\Betting\Criterion;

use App\Domain\Betting\Criterion\WinBothHalvesCriterion;

class WinBothHalvesCriterionTest extends BettingCriterionTestCase
{
    private WinBothHalvesCriterion $criterion;

    protected function setUp(): void
    {
        $this->criterion = new WinBothHalvesCriterion();
    }

    public function test_evaluating_win_both_halves__when_only_home_triggered__should_return_home(): void
    {
        $homeStats = $this->makeStats(matchesPlayedHome: 10, winBothHalvesHome: 4);
        $awayStats = $this->makeStats(matchesPlayedAway: 10, winBothHalvesAway: 2);

        $this->assertSame('home', $this->criterion->evaluate($homeStats, $awayStats));
    }

    public function test_evaluating_win_both_halves__when_only_away_triggered__should_return_away(): void
    {
        $homeStats = $this->makeStats(matchesPlayedHome: 10, winBothHalvesHome: 2);
        $awayStats = $this->makeStats(matchesPlayedAway: 10, winBothHalvesAway: 4);

        $this->assertSame('away', $this->criterion->evaluate($homeStats, $awayStats));
    }

    public function test_evaluating_win_both_halves__when_both_triggered__should_return_both(): void
    {
        $homeStats = $this->makeStats(matchesPlayedHome: 10, winBothHalvesHome: 4);
        $awayStats = $this->makeStats(matchesPlayedAway: 10, winBothHalvesAway: 4);

        $this->assertSame('both', $this->criterion->evaluate($homeStats, $awayStats));
    }

    public function test_evaluating_win_both_halves__when_neither_triggered__should_return_null(): void
    {
        $homeStats = $this->makeStats(matchesPlayedHome: 10, winBothHalvesHome: 2);
        $awayStats = $this->makeStats(matchesPlayedAway: 10, winBothHalvesAway: 2);

        $this->assertNull($this->criterion->evaluate($homeStats, $awayStats));
    }

    public function test_evaluating_win_both_halves__when_not_enough_matches__should_return_null(): void
    {
        $homeStats = $this->makeStats(matchesPlayedHome: 4, winBothHalvesHome: 4);
        $awayStats = $this->makeStats(matchesPlayedAway: 4, winBothHalvesAway: 4);

        $this->assertNull($this->criterion->evaluate($homeStats, $awayStats));
    }

    public function test_evaluating_win_both_halves__bet_type__should_be_win_both_halves(): void
    {
        $this->assertSame('win_both_halves', $this->criterion->betType());
    }
}
