<?php

declare(strict_types=1);

namespace App\Tests\Unit\Domain\Betting\Criterion;

use App\Domain\Betting\Criterion\DoubleChanceCriterion;

class DoubleChanceCriterionTest extends BettingCriterionTestCase
{
    private DoubleChanceCriterion $criterion;

    protected function setUp(): void
    {
        $this->criterion = new DoubleChanceCriterion();
    }

    public function test_evaluating_double_chance__when_home_not_lost_4_and_opponent_not_won_4__should_return_home(): void
    {
        $homeStats = $this->makeStats(formLast5Home: 'WWWDL');
        $awayStats = $this->makeStats(formLast5Away: 'DDDLL');

        $this->assertSame('home', $this->criterion->evaluate($homeStats, $awayStats));
    }

    public function test_evaluating_double_chance__when_home_not_lost_below_threshold__should_return_null(): void
    {
        $homeStats = $this->makeStats(formLast5Home: 'WWDLL');
        $awayStats = $this->makeStats(formLast5Away: 'DDDLL');

        $this->assertNull($this->criterion->evaluate($homeStats, $awayStats));
    }

    public function test_evaluating_double_chance__when_opponent_not_won_below_threshold__should_return_null(): void
    {
        $homeStats = $this->makeStats(formLast5Home: 'WWWWW');
        $awayStats = $this->makeStats(formLast5Away: 'WWWDL');

        $this->assertNull($this->criterion->evaluate($homeStats, $awayStats));
    }

    public function test_evaluating_double_chance__bet_type__should_be_double_chance(): void
    {
        $this->assertSame('double_chance', $this->criterion->betType());
    }
}
