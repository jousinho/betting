<?php

declare(strict_types=1);

namespace App\Domain\Betting\Criterion;

use App\Domain\Betting\ValueObject\TeamMatchStats;

class AwayWinCriterion implements BetCriterionInterface
{
    public function betType(): string
    {
        return 'away_win';
    }

    public function evaluate(TeamMatchStats $homeStats, TeamMatchStats $awayStats): ?string
    {
        $awayWins       = substr_count($awayStats->formLast5Away, 'W');
        $opponentLosses = substr_count($homeStats->formLast5Home, 'L');

        return ($awayWins >= 3 && $opponentLosses >= 3) ? 'away' : null;
    }
}
