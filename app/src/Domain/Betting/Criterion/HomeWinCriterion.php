<?php

declare(strict_types=1);

namespace App\Domain\Betting\Criterion;

use App\Domain\Betting\ValueObject\TeamMatchStats;

class HomeWinCriterion implements BetCriterionInterface
{
    public function betType(): string
    {
        return 'home_win';
    }

    public function evaluate(TeamMatchStats $homeStats, TeamMatchStats $awayStats): ?string
    {
        $homeWins       = substr_count($homeStats->formLast5Home, 'W');
        $opponentLosses = substr_count($awayStats->formLast5Away, 'L');

        return ($homeWins >= 4 && $opponentLosses >= 3) ? 'home' : null;
    }
}
