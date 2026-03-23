<?php

declare(strict_types=1);

namespace App\Domain\Betting\Repository;

use App\Domain\Betting\Entity\Bet;
use App\Domain\Tracking\Entity\Competition;
use App\Domain\Tracking\Entity\LeagueMatch;

interface BetRepositoryInterface
{
    public function save(Bet $bet): void;

    /** @return Bet[] */
    public function findByMatch(LeagueMatch $match): array;

    /** @return Bet[] */
    public function findPendingByCompetition(Competition $competition): array;

    /** @return Bet[] */
    public function findSettledByCompetition(Competition $competition): array;

    public function existsForMatchAndType(LeagueMatch $match, string $betType): bool;

    public function clearByCompetition(Competition $competition): void;

    /** @return Bet[] PENDING, sin odds, con partido a ≤ $days días */
    public function findPendingWithoutOddsWithinDays(Competition $competition, int $days): array;
}
