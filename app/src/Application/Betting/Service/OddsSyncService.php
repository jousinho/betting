<?php

declare(strict_types=1);

namespace App\Application\Betting\Service;

use App\Domain\Betting\Repository\BetRepositoryInterface;
use App\Domain\Betting\Repository\OddsProviderInterface;
use App\Domain\Tracking\Entity\Competition;
use App\Domain\Tracking\Repository\LeagueMatchRepositoryInterface;

class OddsSyncService
{
    private const SPORT_KEY       = 'soccer_spain_la_liga';
    private const DAYS_AHEAD      = 14;
    private const PREFERRED_BOOKS = ['bet365', 'pinnacle'];

    public function __construct(
        private readonly OddsProviderInterface $oddsProvider,
        private readonly BetRepositoryInterface $betRepository,
        private readonly LeagueMatchRepositoryInterface $leagueMatchRepository,
    ) {}

    public function syncForCompetition(Competition $competition): void
    {
        $bets = $this->betRepository->findPendingWithoutOddsWithinDays($competition, self::DAYS_AHEAD);

        if (empty($bets)) {
            return;
        }

        try {
            $bulkOdds = $this->oddsProvider->fetchBulkOdds(self::SPORT_KEY);
        } catch (\Throwable) {
            return;
        }
        $bulkByTeams = $this->indexByTeamNames($bulkOdds);

        $eventOddsCache = [];

        foreach ($bets as $bet) {
            $match    = $bet->leagueMatch();
            $homeName = $match->homeTeam()->oddsName();
            $awayName = $match->awayTeam()->oddsName();

            if ($homeName === null || $awayName === null) {
                continue;
            }

            $eventData = $bulkByTeams[$homeName][$awayName] ?? null;

            if ($eventData === null) {
                continue;
            }

            if ($match->oddsEventId() === null) {
                $match->setOddsEventId($eventData['id']);
                $this->leagueMatchRepository->save($match);
            }

            $odds = $this->extractOdds($bet->betType(), $match, $eventData, $eventOddsCache);

            if ($odds === null) {
                continue;
            }

            $bet->setOdds($odds);
            $this->betRepository->save($bet);
        }
    }

    private function extractOdds(string $betType, mixed $match, array $eventData, array &$cache): ?float
    {
        return match ($betType) {
            'home_win'       => $this->pickOdds($eventData['bookmakers'], 'h2h', $match->homeTeam()->oddsName()),
            'away_win'       => $this->pickOdds($eventData['bookmakers'], 'h2h', $match->awayTeam()->oddsName()),
            'over_2_5'       => $this->pickTotals($eventData['bookmakers'], 'Over', 2.5),
            'under_2_5'      => $this->pickTotals($eventData['bookmakers'], 'Under', 2.5),
            'btts',
            'double_chance'  => $this->pickEventMarket($betType, $match, $eventData['id'], $cache),
            default          => null,
        };
    }

    private function pickOdds(array $bookmakers, string $market, string $outcomeName): ?float
    {
        foreach (self::PREFERRED_BOOKS as $bookKey) {
            $outcomes = $bookmakers[$bookKey][$market] ?? null;
            if ($outcomes === null) {
                continue;
            }
            foreach ($outcomes as $outcome) {
                if ($outcome['name'] === $outcomeName) {
                    return (float) $outcome['price'];
                }
            }
        }

        return null;
    }

    private function pickTotals(array $bookmakers, string $side, float $point): ?float
    {
        foreach (self::PREFERRED_BOOKS as $bookKey) {
            $outcomes = $bookmakers[$bookKey]['totals'] ?? null;
            if ($outcomes === null) {
                continue;
            }
            foreach ($outcomes as $outcome) {
                if ($outcome['name'] === $side && ($outcome['point'] ?? null) === $point) {
                    return (float) $outcome['price'];
                }
            }
        }

        return null;
    }

    private function pickEventMarket(string $betType, mixed $match, string $eventId, array &$cache): ?float
    {
        if (!isset($cache[$eventId])) {
            try {
                $cache[$eventId] = $this->oddsProvider->fetchEventOdds($eventId, self::SPORT_KEY);
            } catch (\Throwable) {
                return null;
            }
        }

        $bookmakers = $cache[$eventId]['bookmakers'];

        if ($betType === 'btts') {
            return $this->pickBtts($bookmakers);
        }

        if ($betType === 'double_chance') {
            return $this->pickDoubleChance($bookmakers, $match->homeTeam()->oddsName());
        }

        return null;
    }

    private function pickBtts(array $bookmakers): ?float
    {
        foreach (self::PREFERRED_BOOKS as $bookKey) {
            $outcomes = $bookmakers[$bookKey]['btts'] ?? null;
            if ($outcomes === null) {
                continue;
            }
            foreach ($outcomes as $outcome) {
                if (strtolower($outcome['name']) === 'yes') {
                    return (float) $outcome['price'];
                }
            }
        }

        return null;
    }

    private function pickDoubleChance(array $bookmakers, string $homeName): ?float
    {
        foreach (self::PREFERRED_BOOKS as $bookKey) {
            $outcomes = $bookmakers[$bookKey]['double_chance'] ?? null;
            if ($outcomes === null) {
                continue;
            }
            foreach ($outcomes as $outcome) {
                if (str_contains($outcome['name'], $homeName)) {
                    return (float) $outcome['price'];
                }
            }
        }

        return null;
    }

    private function indexByTeamNames(array $bulkOdds): array
    {
        $indexed = [];
        foreach ($bulkOdds as $eventId => $event) {
            $indexed[$event['home_team']][$event['away_team']] = array_merge(
                $event,
                ['id' => $eventId],
            );
        }

        return $indexed;
    }
}
