<?php

declare(strict_types=1);

namespace App\Domain\Betting\Criterion;

use App\Domain\Betting\ValueObject\TeamMatchStats;

class WinBothHalvesCriterion implements BetCriterionInterface
{
    public function betType(): string
    {
        return 'win_both_halves';
    }

    public function evaluate(TeamMatchStats $homeStats, TeamMatchStats $awayStats): ?string
    {
        $homeTriggered = $homeStats->matchesPlayedHome >= 5
            && $homeStats->winBothHalvesHome / $homeStats->matchesPlayedHome >= 0.40;

        $awayTriggered = $awayStats->matchesPlayedAway >= 5
            && $awayStats->winBothHalvesAway / $awayStats->matchesPlayedAway >= 0.40;

        if ($homeTriggered && $awayTriggered) {
            return 'both';
        }

        return $homeTriggered ? 'home' : ($awayTriggered ? 'away' : null);
    }
}
