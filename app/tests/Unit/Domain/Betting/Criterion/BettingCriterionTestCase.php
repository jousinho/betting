<?php

declare(strict_types=1);

namespace App\Tests\Unit\Domain\Betting\Criterion;

use App\Domain\Betting\ValueObject\TeamMatchStats;
use PHPUnit\Framework\TestCase;

abstract class BettingCriterionTestCase extends TestCase
{
    protected function makeStats(
        string $formLast5Home = '',
        string $formLast5Away = '',
        int $matchesPlayedHome = 0,
        int $over15Home = 0,
        int $over25Home = 0,
        int $over35Home = 0,
        int $over05HtHome = 0,
        int $winBothHalvesHome = 0,
        int $bttsHome = 0,
        int $cleanSheetHome = 0,
        int $matchesPlayedAway = 0,
        int $over15Away = 0,
        int $over25Away = 0,
        int $over35Away = 0,
        int $over05HtAway = 0,
        int $winBothHalvesAway = 0,
        int $bttsAway = 0,
    ): TeamMatchStats {
        return new TeamMatchStats(
            formLast5Home: $formLast5Home,
            formLast5Away: $formLast5Away,
            matchesPlayedHome: $matchesPlayedHome,
            over15Home: $over15Home,
            over25Home: $over25Home,
            over35Home: $over35Home,
            over05HtHome: $over05HtHome,
            winBothHalvesHome: $winBothHalvesHome,
            bttsHome: $bttsHome,
            cleanSheetHome: $cleanSheetHome,
            matchesPlayedAway: $matchesPlayedAway,
            over15Away: $over15Away,
            over25Away: $over25Away,
            over35Away: $over35Away,
            over05HtAway: $over05HtAway,
            winBothHalvesAway: $winBothHalvesAway,
            bttsAway: $bttsAway,
        );
    }
}
