<?php

declare(strict_types=1);

namespace App\Domain\Betting\Criterion;

use App\Domain\Betting\ValueObject\TeamMatchStats;

class Over25Criterion implements BetCriterionInterface
{
    public function betType(): string
    {
        return 'over_2_5';
    }

    public function evaluate(TeamMatchStats $homeStats, TeamMatchStats $awayStats): ?string
    {
        $homeTriggered = $homeStats->matchesPlayedHome >= 3
            && $homeStats->over25Home / $homeStats->matchesPlayedHome >= 0.75;

        $awayTriggered = $awayStats->matchesPlayedAway >= 3
            && $awayStats->over25Away / $awayStats->matchesPlayedAway >= 0.625;

        if ($homeTriggered && $awayTriggered) {
            return 'both';
        }

        return $homeTriggered ? 'home' : ($awayTriggered ? 'away' : null);
    }
}
