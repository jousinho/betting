<?php

declare(strict_types=1);

namespace App\Tests\Unit\Domain\Betting\Criterion;

use App\Domain\Betting\Criterion\CleanSheetHomeCriterion;

class CleanSheetHomeCriterionTest extends BettingCriterionTestCase
{
    private CleanSheetHomeCriterion $criterion;

    protected function setUp(): void
    {
        $this->criterion = new CleanSheetHomeCriterion();
    }

    public function test_evaluating_clean_sheet_home__when_rate_meets_threshold__should_return_home(): void
    {
        $homeStats = $this->makeStats(matchesPlayedHome: 10, cleanSheetHome: 4);
        $awayStats = $this->makeStats();

        $this->assertSame('home', $this->criterion->evaluate($homeStats, $awayStats));
    }

    public function test_evaluating_clean_sheet_home__when_rate_below_threshold__should_return_null(): void
    {
        $homeStats = $this->makeStats(matchesPlayedHome: 10, cleanSheetHome: 3);
        $awayStats = $this->makeStats();

        $this->assertNull($this->criterion->evaluate($homeStats, $awayStats));
    }

    public function test_evaluating_clean_sheet_home__when_not_enough_matches__should_return_null(): void
    {
        $homeStats = $this->makeStats(matchesPlayedHome: 4, cleanSheetHome: 4);
        $awayStats = $this->makeStats();

        $this->assertNull($this->criterion->evaluate($homeStats, $awayStats));
    }

    public function test_evaluating_clean_sheet_home__bet_type__should_be_clean_sheet_home(): void
    {
        $this->assertSame('clean_sheet_home', $this->criterion->betType());
    }
}
