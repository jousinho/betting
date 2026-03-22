<?php

declare(strict_types=1);

namespace App\Domain\Betting\Criterion;

use App\Domain\Betting\ValueObject\TeamMatchStats;

class DoubleChanceCriterion implements BetCriterionInterface
{
    public function betType(): string
    {
        return 'double_chance';
    }

    public function evaluate(TeamMatchStats $homeStats, TeamMatchStats $awayStats): ?string
    {
        $homeNotLost    = substr_count($homeStats->formLast5Home, 'W') + substr_count($homeStats->formLast5Home, 'D');
        $opponentNotWon = substr_count($awayStats->formLast5Away, 'D') + substr_count($awayStats->formLast5Away, 'L');

        return ($homeNotLost >= 4 && $opponentNotWon >= 4) ? 'home' : null;
    }
}
