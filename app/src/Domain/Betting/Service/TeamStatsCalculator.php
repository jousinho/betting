<?php

declare(strict_types=1);

namespace App\Domain\Betting\Service;

use App\Domain\Betting\ValueObject\TeamMatchStats;
use App\Domain\Tracking\Entity\Competition;
use App\Domain\Tracking\Entity\LeagueMatch;
use App\Domain\Tracking\Entity\Team;
use App\Domain\Tracking\Repository\LeagueMatchRepositoryInterface;

class TeamStatsCalculator
{
    public function __construct(private readonly LeagueMatchRepositoryInterface $leagueMatchRepository) {}

    public function calculate(Team $team, Competition $competition): TeamMatchStats
    {
        $finished = $this->leagueMatchRepository->findFinishedByCompetition($competition);

        $homeMatches = array_values(array_filter($finished, fn(LeagueMatch $m) => $m->homeTeam()->id() == $team->id()));
        $awayMatches = array_values(array_filter($finished, fn(LeagueMatch $m) => $m->awayTeam()->id() == $team->id()));

        usort($homeMatches, fn(LeagueMatch $a, LeagueMatch $b) => $b->playedAt() <=> $a->playedAt());
        usort($awayMatches, fn(LeagueMatch $a, LeagueMatch $b) => $b->playedAt() <=> $a->playedAt());

        return new TeamMatchStats(
            formLast5Home: $this->buildForm($homeMatches, 5, true),
            formLast5Away: $this->buildForm($awayMatches, 5, false),
            matchesPlayedHome: count($homeMatches),
            over15Home: $this->countOver($homeMatches, 1.5, true),
            over25Home: $this->countOver($homeMatches, 2.5, true),
            over35Home: $this->countOver($homeMatches, 3.5, true),
            over05HtHome: $this->countOverHt($homeMatches, 0.5, true),
            winBothHalvesHome: $this->countWinBothHalves($homeMatches, true),
            bttsHome: $this->countBtts($homeMatches),
            cleanSheetHome: $this->countCleanSheet($homeMatches, true),
            matchesPlayedAway: count($awayMatches),
            over15Away: $this->countOver($awayMatches, 1.5, false),
            over25Away: $this->countOver($awayMatches, 2.5, false),
            over35Away: $this->countOver($awayMatches, 3.5, false),
            over05HtAway: $this->countOverHt($awayMatches, 0.5, false),
            winBothHalvesAway: $this->countWinBothHalves($awayMatches, false),
            bttsAway: $this->countBtts($awayMatches),
        );
    }

    /** @param LeagueMatch[] $matches already sorted desc */
    private function buildForm(array $matches, int $last, bool $isHome): string
    {
        $form = '';
        foreach (array_slice($matches, 0, $last) as $m) {
            $scored   = $isHome ? $m->homeGoalsFt() : $m->awayGoalsFt();
            $conceded = $isHome ? $m->awayGoalsFt() : $m->homeGoalsFt();
            $form .= match(true) {
                $scored > $conceded => 'W',
                $scored < $conceded => 'L',
                default             => 'D',
            };
        }
        return $form;
    }

    /** @param LeagueMatch[] $matches */
    private function countOver(array $matches, float $line, bool $isHome): int
    {
        return count(array_filter($matches, fn(LeagueMatch $m) =>
            ($m->homeGoalsFt() + $m->awayGoalsFt()) > $line
        ));
    }

    /** @param LeagueMatch[] $matches */
    private function countOverHt(array $matches, float $line, bool $isHome): int
    {
        return count(array_filter($matches, fn(LeagueMatch $m) =>
            ($m->homeGoalsHt() + $m->awayGoalsHt()) > $line
        ));
    }

    /** @param LeagueMatch[] $matches */
    private function countWinBothHalves(array $matches, bool $isHome): int
    {
        return count(array_filter($matches, function (LeagueMatch $m) use ($isHome) {
            if ($isHome) {
                return $m->homeGoalsHt() > $m->awayGoalsHt()
                    && ($m->homeGoalsFt() - $m->homeGoalsHt()) > ($m->awayGoalsFt() - $m->awayGoalsHt());
            }
            return $m->awayGoalsHt() > $m->homeGoalsHt()
                && ($m->awayGoalsFt() - $m->awayGoalsHt()) > ($m->homeGoalsFt() - $m->homeGoalsHt());
        }));
    }

    /** @param LeagueMatch[] $matches */
    private function countBtts(array $matches): int
    {
        return count(array_filter($matches, fn(LeagueMatch $m) =>
            $m->homeGoalsFt() > 0 && $m->awayGoalsFt() > 0
        ));
    }

    /** @param LeagueMatch[] $matches */
    private function countCleanSheet(array $matches, bool $isHome): int
    {
        return count(array_filter($matches, fn(LeagueMatch $m) =>
            $isHome ? $m->awayGoalsFt() === 0 : $m->homeGoalsFt() === 0
        ));
    }
}
