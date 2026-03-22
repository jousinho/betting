<?php

declare(strict_types=1);

namespace App\Domain\Betting\ValueObject;

final class TeamMatchStats
{
    public function __construct(
        public readonly string $formLast5Home,
        public readonly string $formLast5Away,
        public readonly int $matchesPlayedHome,
        public readonly int $over15Home,
        public readonly int $over25Home,
        public readonly int $over35Home,
        public readonly int $over05HtHome,
        public readonly int $winBothHalvesHome,
        public readonly int $bttsHome,
        public readonly int $cleanSheetHome,
        public readonly int $matchesPlayedAway,
        public readonly int $over15Away,
        public readonly int $over25Away,
        public readonly int $over35Away,
        public readonly int $over05HtAway,
        public readonly int $winBothHalvesAway,
        public readonly int $bttsAway,
    ) {}
}
