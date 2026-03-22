<?php

declare(strict_types=1);

namespace App\Tests\Unit\Domain\Betting\Criterion;

use App\Domain\Betting\Criterion\HomeWinCriterion;

class HomeWinCriterionTest extends BettingCriterionTestCase
{
    private HomeWinCriterion $criterion;

    protected function setUp(): void
    {
        $this->criterion = new HomeWinCriterion();
    }

    public function test_evaluating_home_win__when_home_wins_4_and_opponent_losses_3__should_return_home(): void
    {
        $homeStats = $this->makeStats(formLast5Home: 'WWWWL');
        $awayStats = $this->makeStats(formLast5Away: 'LLLWW');

        $this->assertSame('home', $this->criterion->evaluate($homeStats, $awayStats));
    }

    public function test_evaluating_home_win__when_home_wins_below_threshold__should_return_null(): void
    {
        $homeStats = $this->makeStats(formLast5Home: 'WWWDL');
        $awayStats = $this->makeStats(formLast5Away: 'LLLWW');

        $this->assertNull($this->criterion->evaluate($homeStats, $awayStats));
    }

    public function test_evaluating_home_win__when_opponent_losses_below_threshold__should_return_null(): void
    {
        $homeStats = $this->makeStats(formLast5Home: 'WWWWW');
        $awayStats = $this->makeStats(formLast5Away: 'LLWWW');

        $this->assertNull($this->criterion->evaluate($homeStats, $awayStats));
    }

    public function test_evaluating_home_win__bet_type__should_be_home_win(): void
    {
        $this->assertSame('home_win', $this->criterion->betType());
    }
}
