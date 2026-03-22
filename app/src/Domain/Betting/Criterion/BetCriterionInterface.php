<?php

declare(strict_types=1);

namespace App\Domain\Betting\Criterion;

use App\Domain\Betting\ValueObject\TeamMatchStats;

interface BetCriterionInterface
{
    public function betType(): string;

    /**
     * Returns 'home', 'away', 'both', or null if the criterion is not met.
     * 'home'  — triggered by home team stats
     * 'away'  — triggered by away team stats
     * 'both'  — triggered by both teams simultaneously
     * null    — criterion not met, no bet should be placed
     */
    public function evaluate(TeamMatchStats $homeStats, TeamMatchStats $awayStats): ?string;
}
