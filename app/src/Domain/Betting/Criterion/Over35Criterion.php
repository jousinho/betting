<?php

declare(strict_types=1);

namespace App\Domain\Betting\Criterion;

use App\Domain\Betting\ValueObject\TeamMatchStats;

class Over35Criterion implements BetCriterionInterface
{
    public function betType(): string
    {
        return 'over_3_5';
    }

    public function evaluate(TeamMatchStats $homeStats, TeamMatchStats $awayStats): ?string
    {
        $homeTriggered = $homeStats->matchesPlayedHome >= 5
            && $homeStats->over35Home / $homeStats->matchesPlayedHome >= 0.50;

        $awayTriggered = $awayStats->matchesPlayedAway >= 5
            && $awayStats->over35Away / $awayStats->matchesPlayedAway >= 0.50;

        if ($homeTriggered && $awayTriggered) {
            return 'both';
        }

        return $homeTriggered ? 'home' : ($awayTriggered ? 'away' : null);
    }
}
