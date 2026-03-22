<?php

declare(strict_types=1);

namespace App\Domain\Tracking\Repository;

use App\Domain\Tracking\Entity\Competition;
use App\Domain\Tracking\Entity\LeagueMatch;

interface LeagueMatchRepositoryInterface
{
    public function save(LeagueMatch $match): void;

    public function findByExternalId(int $externalId): ?LeagueMatch;

    /** @return LeagueMatch[] */
    public function findPendingByCompetition(Competition $competition): array;

    /** @return LeagueMatch[] */
    public function findFinishedByCompetition(Competition $competition): array;

    /** @return LeagueMatch[] */
    public function findFinishedByCompetitionBefore(Competition $competition, \DateTimeImmutable $before): array;

    /** @return LeagueMatch[] */
    public function findScheduledByCompetition(Competition $competition): array;
}
