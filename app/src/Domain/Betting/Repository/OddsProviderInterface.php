<?php

declare(strict_types=1);

namespace App\Domain\Betting\Repository;

interface OddsProviderInterface
{
    /**
     * Returns upcoming matches with h2h + totals odds, indexed by odds event ID.
     * Shape: [ eventId => ['home_team' => string, 'away_team' => string, 'bookmakers' => [...]] ]
     */
    public function fetchBulkOdds(string $sportKey): array;

    /**
     * Returns btts + double_chance odds for a single event.
     * Shape: [ 'bookmakers' => [ bookmakerKey => [ marketKey => [...outcomes] ] ] ]
     */
    public function fetchEventOdds(string $eventId, string $sportKey): array;
}
