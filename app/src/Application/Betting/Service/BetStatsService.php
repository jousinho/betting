<?php

declare(strict_types=1);

namespace App\Application\Betting\Service;

use App\Domain\Betting\Entity\Bet;

class BetStatsService
{
    /** @param Bet[] $bets */
    public function buildStatsData(array $bets): array
    {
        $won           = 0;
        $lost          = 0;
        $byType        = [];
        $byMatchday    = [];
        $byPerspective = [];
        $byTeam        = [];

        foreach ($bets as $bet) {
            $isWon    = $bet->status() === Bet::STATUS_WON;
            $matchday = $bet->leagueMatch()->matchday();
            $type     = $bet->betType();
            $persp    = $bet->perspective();
            $homeTeam = $bet->leagueMatch()->homeTeam()->name();
            $awayTeam = $bet->leagueMatch()->awayTeam()->name();

            $isWon ? $won++ : $lost++;

            $byType[$type]['won']   = ($byType[$type]['won']  ?? 0) + ($isWon ? 1 : 0);
            $byType[$type]['total'] = ($byType[$type]['total'] ?? 0) + 1;

            $byMatchday[$matchday]['won']   = ($byMatchday[$matchday]['won']  ?? 0) + ($isWon ? 1 : 0);
            $byMatchday[$matchday]['total'] = ($byMatchday[$matchday]['total'] ?? 0) + 1;

            $byPerspective[$persp]['won']   = ($byPerspective[$persp]['won']  ?? 0) + ($isWon ? 1 : 0);
            $byPerspective[$persp]['total'] = ($byPerspective[$persp]['total'] ?? 0) + 1;

            foreach ([$homeTeam, $awayTeam] as $teamName) {
                $byTeam[$teamName]['won']   = ($byTeam[$teamName]['won']  ?? 0) + ($isWon ? 1 : 0);
                $byTeam[$teamName]['total'] = ($byTeam[$teamName]['total'] ?? 0) + 1;
            }
        }

        $rate = fn(array $g) => $g['total'] > 0 ? round($g['won'] / $g['total'] * 100) : 0;

        foreach ($byType        as &$g) { $g['rate'] = $rate($g); } unset($g);
        foreach ($byMatchday    as &$g) { $g['rate'] = $rate($g); } unset($g);
        foreach ($byPerspective as &$g) { $g['rate'] = $rate($g); } unset($g);
        foreach ($byTeam        as &$g) { $g['rate'] = $rate($g); } unset($g);

        uasort($byType, fn($a, $b) => $b['rate'] <=> $a['rate']);
        ksort($byMatchday);
        uasort($byTeam, fn($a, $b) => $b['won'] <=> $a['won']);

        $topTeams  = array_slice($byTeam, 0, 10, true);
        $totalBets = $won + $lost;

        return [
            'won'           => $won,
            'lost'          => $lost,
            'total'         => $totalBets,
            'rate'          => $totalBets > 0 ? round($won / $totalBets * 100) : 0,
            'byType'        => $byType,
            'byMatchday'    => $byMatchday,
            'byPerspective' => $byPerspective,
            'topTeams'      => $topTeams,
        ];
    }
}
