<?php

declare(strict_types=1);

namespace App\Domain\Betting\Criterion;

use App\Domain\Betting\ValueObject\TeamMatchStats;

class BttsCriterion implements BetCriterionInterface
{
    public function betType(): string
    {
        return 'btts';
    }

    public function evaluate(TeamMatchStats $homeStats, TeamMatchStats $awayStats): ?string
    {
        $homeTriggered = $homeStats->matchesPlayedHome >= 3
            && $homeStats->bttsHome / $homeStats->matchesPlayedHome >= 0.65;

        $awayTriggered = $awayStats->matchesPlayedAway >= 3
            && $awayStats->bttsAway / $awayStats->matchesPlayedAway >= 0.65;

        if ($homeTriggered && $awayTriggered) {
            return 'both';
        }

        if ($homeTriggered) {
            return 'home';
        }

        if ($awayTriggered) {
            return 'away';
        }

        return null;
    }
}
