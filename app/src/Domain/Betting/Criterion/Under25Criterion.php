<?php

declare(strict_types=1);

namespace App\Domain\Betting\Criterion;

use App\Domain\Betting\ValueObject\TeamMatchStats;

class Under25Criterion implements BetCriterionInterface
{
    public function betType(): string
    {
        return 'under_2_5';
    }

    public function evaluate(TeamMatchStats $homeStats, TeamMatchStats $awayStats): ?string
    {
        $homeTriggered = $homeStats->matchesPlayedHome >= 5
            && ($homeStats->matchesPlayedHome - $homeStats->over25Home) / $homeStats->matchesPlayedHome >= 0.60;

        $awayTriggered = $awayStats->matchesPlayedAway >= 5
            && ($awayStats->matchesPlayedAway - $awayStats->over25Away) / $awayStats->matchesPlayedAway >= 0.60;

        if ($homeTriggered && $awayTriggered) {
            return 'both';
        }

        return $homeTriggered ? 'home' : ($awayTriggered ? 'away' : null);
    }
}
