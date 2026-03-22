<?php

declare(strict_types=1);

namespace App\Domain\Tracking\Repository;

use App\Domain\Tracking\Entity\NonLeagueMatch;
use App\Domain\Tracking\Entity\Team;

interface NonLeagueMatchRepositoryInterface
{
    public function save(NonLeagueMatch $match): void;

    public function findByExternalIdAndTeam(int $externalId, Team $team): ?NonLeagueMatch;

    /** @return NonLeagueMatch[] */
    public function findPending(): array;
}
