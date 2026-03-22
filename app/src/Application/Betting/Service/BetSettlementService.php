<?php

declare(strict_types=1);

namespace App\Application\Betting\Service;

use App\Domain\Betting\Entity\Bet;
use App\Domain\Betting\Repository\BetRepositoryInterface;
use App\Domain\Tracking\Entity\Competition;
use App\Domain\Tracking\Entity\LeagueMatch;

class BetSettlementService
{
    public function __construct(private readonly BetRepositoryInterface $betRepository) {}

    public function settleAll(Competition $competition): void
    {
        $pending = $this->betRepository->findPendingByCompetition($competition);
        $now     = new \DateTimeImmutable();

        foreach ($pending as $bet) {
            if ($bet->leagueMatch()->status() !== LeagueMatch::STATUS_FINISHED) {
                continue;
            }

            $bet->settle($now, $this->evaluate($bet));
            $this->betRepository->save($bet);
        }
    }

    private function evaluate(Bet $bet): bool
    {
        $match = $bet->leagueMatch();
        $hFt   = $match->homeGoalsFt();
        $aFt   = $match->awayGoalsFt();
        $hHt   = $match->homeGoalsHt();
        $aHt   = $match->awayGoalsHt();
        $total = $hFt + $aFt;
        $p     = $bet->perspective();

        return match ($bet->betType()) {
            'home_win'         => $hFt > $aFt,
            'away_win'         => $aFt > $hFt,
            'double_chance'    => $hFt >= $aFt,
            'btts'             => $hFt > 0 && $aFt > 0,
            'clean_sheet_home' => $aFt === 0,
            'over_05_ht'       => ($hHt + $aHt) > 0,
            'over_1_5'         => $total > 1,
            'over_2_5'         => $total > 2,
            'over_3_5'         => $total > 3,
            'under_2_5'        => $total < 3,
            'win_both_halves'  => $this->evaluateWinBothHalves($hFt, $aFt, $hHt, $aHt, $p),
            default            => false,
        };
    }

    private function evaluateWinBothHalves(int $hFt, int $aFt, int $hHt, int $aHt, string $perspective): bool
    {
        $homeWonBoth = $hHt > $aHt && ($hFt - $hHt) > ($aFt - $aHt);
        $awayWonBoth = $aHt > $hHt && ($aFt - $aHt) > ($hFt - $hHt);

        return match ($perspective) {
            'home'  => $homeWonBoth,
            'away'  => $awayWonBoth,
            'both'  => $homeWonBoth || $awayWonBoth,
            default => false,
        };
    }
}
