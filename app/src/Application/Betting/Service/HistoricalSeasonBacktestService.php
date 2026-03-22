<?php

declare(strict_types=1);

namespace App\Application\Betting\Service;

use App\Domain\Betting\Criterion\AwayWinCriterion;
use App\Domain\Betting\Criterion\BetCriterionInterface;
use App\Domain\Betting\Criterion\BttsCriterion;
use App\Domain\Betting\Criterion\CleanSheetHomeCriterion;
use App\Domain\Betting\Criterion\DoubleChanceCriterion;
use App\Domain\Betting\Criterion\HomeWinCriterion;
use App\Domain\Betting\Criterion\Over05HalfTimeCriterion;
use App\Domain\Betting\Criterion\Over15Criterion;
use App\Domain\Betting\Criterion\Over25Criterion;
use App\Domain\Betting\Criterion\Over35Criterion;
use App\Domain\Betting\Criterion\Under25Criterion;
use App\Domain\Betting\Criterion\WinBothHalvesCriterion;
use App\Domain\Betting\ValueObject\TeamMatchStats;

class HistoricalSeasonBacktestService
{
    private const CONFLICTS_WITH_HOME = [
        'under_2_5' => ['over_3_5', 'over_2_5'],
        'away_win'  => ['home_win'],
        'btts'      => ['clean_sheet_home'],
    ];

    /** @var BetCriterionInterface[] */
    private array $criteria;

    public function __construct()
    {
        $this->criteria = [
            new HomeWinCriterion(),
            new AwayWinCriterion(),
            new DoubleChanceCriterion(),
            new CleanSheetHomeCriterion(),
            new BttsCriterion(),
            new Over05HalfTimeCriterion(),
            new Over15Criterion(),
            new Over25Criterion(),
            new Over35Criterion(),
            new Under25Criterion(),
            new WinBothHalvesCriterion(),
        ];
    }

    /**
     * @param array[] $rawMatches  output of fetchLeagueMatchesBySeason (includes homeTeamName/awayTeamName)
     * @return array  same structure as BetStatsService::buildStatsData
     */
    public function process(array $rawMatches): array
    {
        $finished = array_values(array_filter($rawMatches, fn(array $m) =>
            $m['status'] === 'FINISHED'
            && $m['homeGoalsFt'] !== null
            && $m['homeGoalsHt'] !== null,
        ));

        usort($finished, fn(array $a, array $b) => $a['playedAt'] <=> $b['playedAt']);

        $homeMatchesByTeam = [];
        $awayMatchesByTeam = [];

        $won           = 0;
        $lost          = 0;
        $byType        = [];
        $byMatchday    = [];
        $byPerspective = [];
        $byTeam        = [];

        foreach ($finished as $match) {
            $homeId       = $match['homeTeamId'];
            $awayId       = $match['awayTeamId'];
            $homeTeamName = $match['homeTeamName'];
            $awayTeamName = $match['awayTeamName'];
            $matchday     = $match['matchday'];

            $homeStats = $this->buildStats(
                $homeMatchesByTeam[$homeId] ?? [],
                $awayMatchesByTeam[$homeId] ?? [],
            );
            $awayStats = $this->buildStats(
                $homeMatchesByTeam[$awayId] ?? [],
                $awayMatchesByTeam[$awayId] ?? [],
            );

            $homeBetTypes = [];

            foreach ($this->criteria as $criterion) {
                $perspective = $criterion->evaluate($homeStats, $awayStats);

                if ($perspective === null) {
                    continue;
                }

                $skipped = $this->isAwayConflicting($criterion->betType(), $perspective, $homeBetTypes);

                if (!$skipped) {
                    $isWon = $this->settleBet($criterion->betType(), $perspective, $match);
                    $type  = $criterion->betType();

                    $isWon ? $won++ : $lost++;

                    $byType[$type]['won']   = ($byType[$type]['won']   ?? 0) + ($isWon ? 1 : 0);
                    $byType[$type]['total'] = ($byType[$type]['total'] ?? 0) + 1;

                    $byMatchday[$matchday]['won']   = ($byMatchday[$matchday]['won']   ?? 0) + ($isWon ? 1 : 0);
                    $byMatchday[$matchday]['total'] = ($byMatchday[$matchday]['total'] ?? 0) + 1;

                    $byPerspective[$perspective]['won']   = ($byPerspective[$perspective]['won']   ?? 0) + ($isWon ? 1 : 0);
                    $byPerspective[$perspective]['total'] = ($byPerspective[$perspective]['total'] ?? 0) + 1;

                    foreach ([$homeTeamName, $awayTeamName] as $teamName) {
                        $byTeam[$teamName]['won']   = ($byTeam[$teamName]['won']   ?? 0) + ($isWon ? 1 : 0);
                        $byTeam[$teamName]['total'] = ($byTeam[$teamName]['total'] ?? 0) + 1;
                    }
                }

                if (in_array($perspective, ['home', 'both'], true)) {
                    $homeBetTypes[] = $criterion->betType();
                }
            }

            $homeMatchesByTeam[$homeId][] = $match;
            $awayMatchesByTeam[$awayId][] = $match;
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

    /** @param array[] $homeMatches  matches where this team was HOME (oldest first, as accumulated)
     *  @param array[] $awayMatches  matches where this team was AWAY (oldest first, as accumulated)
     */
    private function buildStats(array $homeMatches, array $awayMatches): TeamMatchStats
    {
        $homeDesc = array_reverse($homeMatches);
        $awayDesc = array_reverse($awayMatches);

        return new TeamMatchStats(
            formLast5Home:    $this->buildForm($homeDesc, 5, true),
            formLast5Away:    $this->buildForm($awayDesc, 5, false),
            matchesPlayedHome: count($homeMatches),
            over15Home:        $this->countOver($homeMatches, 1.5),
            over25Home:        $this->countOver($homeMatches, 2.5),
            over35Home:        $this->countOver($homeMatches, 3.5),
            over05HtHome:      $this->countOverHt($homeMatches, 0.5),
            winBothHalvesHome: $this->countWinBothHalves($homeMatches, true),
            bttsHome:          $this->countBtts($homeMatches),
            cleanSheetHome:    $this->countCleanSheet($homeMatches),
            matchesPlayedAway: count($awayMatches),
            over15Away:        $this->countOver($awayMatches, 1.5),
            over25Away:        $this->countOver($awayMatches, 2.5),
            over35Away:        $this->countOver($awayMatches, 3.5),
            over05HtAway:      $this->countOverHt($awayMatches, 0.5),
            winBothHalvesAway: $this->countWinBothHalves($awayMatches, false),
            bttsAway:          $this->countBtts($awayMatches),
        );
    }

    /** @param array[] $matches sorted desc */
    private function buildForm(array $matches, int $last, bool $isHome): string
    {
        $form = '';
        foreach (array_slice($matches, 0, $last) as $m) {
            $scored   = $isHome ? $m['homeGoalsFt'] : $m['awayGoalsFt'];
            $conceded = $isHome ? $m['awayGoalsFt'] : $m['homeGoalsFt'];
            $form .= match(true) {
                $scored > $conceded => 'W',
                $scored < $conceded => 'L',
                default             => 'D',
            };
        }
        return $form;
    }

    /** @param array[] $matches */
    private function countOver(array $matches, float $line): int
    {
        return count(array_filter($matches, fn($m) => ($m['homeGoalsFt'] + $m['awayGoalsFt']) > $line));
    }

    /** @param array[] $matches */
    private function countOverHt(array $matches, float $line): int
    {
        return count(array_filter($matches, fn($m) => ($m['homeGoalsHt'] + $m['awayGoalsHt']) > $line));
    }

    /** @param array[] $matches */
    private function countWinBothHalves(array $matches, bool $isHome): int
    {
        return count(array_filter($matches, function (array $m) use ($isHome) {
            if ($isHome) {
                return $m['homeGoalsHt'] > $m['awayGoalsHt']
                    && ($m['homeGoalsFt'] - $m['homeGoalsHt']) > ($m['awayGoalsFt'] - $m['awayGoalsHt']);
            }
            return $m['awayGoalsHt'] > $m['homeGoalsHt']
                && ($m['awayGoalsFt'] - $m['awayGoalsHt']) > ($m['homeGoalsFt'] - $m['homeGoalsHt']);
        }));
    }

    /** @param array[] $matches */
    private function countBtts(array $matches): int
    {
        return count(array_filter($matches, fn($m) => $m['homeGoalsFt'] > 0 && $m['awayGoalsFt'] > 0));
    }

    /** @param array[] $matches where team is HOME — clean sheet = away scored 0 */
    private function countCleanSheet(array $matches): int
    {
        return count(array_filter($matches, fn($m) => $m['awayGoalsFt'] === 0));
    }

    private function settleBet(string $betType, string $perspective, array $match): bool
    {
        $hFt   = $match['homeGoalsFt'];
        $aFt   = $match['awayGoalsFt'];
        $hHt   = $match['homeGoalsHt'];
        $aHt   = $match['awayGoalsHt'];
        $total = $hFt + $aFt;

        return match ($betType) {
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
            'win_both_halves'  => $this->settleWinBothHalves($hFt, $aFt, $hHt, $aHt, $perspective),
            default            => false,
        };
    }

    private function settleWinBothHalves(int $hFt, int $aFt, int $hHt, int $aHt, string $perspective): bool
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

    private function isAwayConflicting(string $betType, string $perspective, array $homeBetTypes): bool
    {
        if ($perspective !== 'away') {
            return false;
        }

        foreach (self::CONFLICTS_WITH_HOME[$betType] ?? [] as $conflictingType) {
            if (in_array($conflictingType, $homeBetTypes, true)) {
                return true;
            }
        }

        return false;
    }
}
