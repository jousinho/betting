<?php

declare(strict_types=1);

namespace App\Domain\Betting\Repository;

use App\Domain\Betting\Entity\SeasonStats;

interface SeasonStatsRepositoryInterface
{
    public function save(SeasonStats $seasonStats): void;

    /** @return SeasonStats[] */
    public function findByCompetitionCode(string $competitionCode): array;

    public function findByCompetitionCodeAndSeason(string $competitionCode, string $season): ?SeasonStats;
}
