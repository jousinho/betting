<?php

declare(strict_types=1);

namespace App\Domain\Betting\Criterion;

use App\Domain\Betting\ValueObject\TeamMatchStats;

class Over15Criterion implements BetCriterionInterface
{
    public function betType(): string
    {
        return 'over_1_5';
    }

    public function evaluate(TeamMatchStats $homeStats, TeamMatchStats $awayStats): ?string
    {
        $homeTriggered = $homeStats->matchesPlayedHome >= 5
            && $homeStats->over15Home / $homeStats->matchesPlayedHome >= 0.70;

        $awayTriggered = $awayStats->matchesPlayedAway >= 5
            && $awayStats->over15Away / $awayStats->matchesPlayedAway >= 0.70;

        if ($homeTriggered && $awayTriggered) {
            return 'both';
        }

        return $homeTriggered ? 'home' : ($awayTriggered ? 'away' : null);
    }
}
