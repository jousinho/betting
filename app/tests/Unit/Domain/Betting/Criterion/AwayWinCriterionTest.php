<?php

declare(strict_types=1);

namespace App\Tests\Unit\Domain\Betting\Criterion;

use App\Domain\Betting\Criterion\AwayWinCriterion;

class AwayWinCriterionTest extends BettingCriterionTestCase
{
    private AwayWinCriterion $criterion;

    protected function setUp(): void
    {
        $this->criterion = new AwayWinCriterion();
    }

    public function test_evaluating_away_win__when_away_wins_3_and_opponent_losses_3__should_return_away(): void
    {
        $homeStats = $this->makeStats(formLast5Home: 'LLLWW');
        $awayStats = $this->makeStats(formLast5Away: 'WWWDL');

        $this->assertSame('away', $this->criterion->evaluate($homeStats, $awayStats));
    }

    public function test_evaluating_away_win__when_away_wins_below_threshold__should_return_null(): void
    {
        $homeStats = $this->makeStats(formLast5Home: 'LLLWW');
        $awayStats = $this->makeStats(formLast5Away: 'WWDDL');

        $this->assertNull($this->criterion->evaluate($homeStats, $awayStats));
    }

    public function test_evaluating_away_win__when_opponent_losses_below_threshold__should_return_null(): void
    {
        $homeStats = $this->makeStats(formLast5Home: 'LLWWW');
        $awayStats = $this->makeStats(formLast5Away: 'WWWWW');

        $this->assertNull($this->criterion->evaluate($homeStats, $awayStats));
    }

    public function test_evaluating_away_win__bet_type__should_be_away_win(): void
    {
        $this->assertSame('away_win', $this->criterion->betType());
    }
}
