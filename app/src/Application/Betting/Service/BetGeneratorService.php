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
use App\Domain\Betting\Entity\Bet;
use App\Domain\Betting\Repository\BetRepositoryInterface;
use App\Domain\Betting\Service\TeamStatsCalculator;
use App\Domain\Tracking\Entity\Competition;
use App\Domain\Tracking\Entity\LeagueMatch;
use App\Domain\Tracking\Repository\LeagueMatchRepositoryInterface;

class BetGeneratorService
{
    /**
     * If an away-only bet type conflicts with an existing home bet type, the away bet is marked skipped.
     * Key = away bet type, Value = the home bet type it conflicts with.
     */
    private const CONFLICTS_WITH_HOME = [
        'under_2_5' => ['over_3_5', 'over_2_5'],
        'away_win'  => ['home_win'],
        'btts'      => ['clean_sheet_home'],
    ];

    /** @var BetCriterionInterface[] */
    private array $criteria;

    public function __construct(
        private readonly LeagueMatchRepositoryInterface $leagueMatchRepository,
        private readonly BetRepositoryInterface $betRepository,
        private readonly TeamStatsCalculator $statsCalculator,
    ) {
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

    public function generate(Competition $competition): void
    {
        $matches = $this->leagueMatchRepository->findScheduledByCompetition($competition);

        foreach ($matches as $match) {
            $this->generateForMatch($match, $competition);
        }
    }

    private function generateForMatch(LeagueMatch $match, Competition $competition): void
    {
        $homeStats = $this->statsCalculator->calculate($match->homeTeam(), $competition);
        $awayStats = $this->statsCalculator->calculate($match->awayTeam(), $competition);

        $homeBetTypes = [];

        foreach ($this->criteria as $criterion) {
            $perspective = $criterion->evaluate($homeStats, $awayStats);

            if ($perspective === null) {
                continue;
            }

            if ($this->betRepository->existsForMatchAndType($match, $criterion->betType())) {
                continue;
            }

            $skipped = $this->isAwayConflicting($criterion->betType(), $perspective, $homeBetTypes);

            $this->betRepository->save(Bet::create($match, $criterion->betType(), $perspective, $skipped));

            if (in_array($perspective, ['home', 'both'], true)) {
                $homeBetTypes[] = $criterion->betType();
            }
        }
    }

    private function isAwayConflicting(string $betType, string $perspective, array $homeBetTypes): bool
    {
        if ($perspective !== 'away') {
            return false;
        }

        $conflictsWith = self::CONFLICTS_WITH_HOME[$betType] ?? [];

        foreach ($conflictsWith as $conflictingType) {
            if (in_array($conflictingType, $homeBetTypes, true)) {
                return true;
            }
        }

        return false;
    }
}
