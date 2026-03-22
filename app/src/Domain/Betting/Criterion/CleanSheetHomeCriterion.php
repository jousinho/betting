<?php

declare(strict_types=1);

namespace App\Domain\Betting\Criterion;

use App\Domain\Betting\ValueObject\TeamMatchStats;

class CleanSheetHomeCriterion implements BetCriterionInterface
{
    public function betType(): string
    {
        return 'clean_sheet_home';
    }

    public function evaluate(TeamMatchStats $homeStats, TeamMatchStats $awayStats): ?string
    {
        if ($homeStats->matchesPlayedHome < 3) {
            return null;
        }

        return ($homeStats->cleanSheetHome / $homeStats->matchesPlayedHome >= 0.40) ? 'home' : null;
    }
}
