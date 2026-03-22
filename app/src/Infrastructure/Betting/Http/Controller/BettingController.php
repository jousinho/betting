<?php

declare(strict_types=1);

namespace App\Infrastructure\Betting\Http\Controller;

use App\Application\Betting\Service\BetStatsService;
use App\Domain\Betting\Entity\Bet;
use App\Domain\Betting\Repository\BetRepositoryInterface;
use App\Domain\Betting\Repository\SeasonStatsRepositoryInterface;
use App\Domain\Betting\Service\TeamStatsCalculator;
use App\Domain\Tracking\Repository\CompetitionRepositoryInterface;
use App\Domain\Tracking\Repository\LeagueMatchRepositoryInterface;
use App\Domain\Tracking\Repository\TeamRepositoryInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Twig\Environment;

class BettingController
{
    public function __construct(
        private readonly Environment $twig,
        private readonly CompetitionRepositoryInterface $competitionRepository,
        private readonly LeagueMatchRepositoryInterface $matchRepository,
        private readonly BetRepositoryInterface $betRepository,
        private readonly TeamRepositoryInterface $teamRepository,
        private readonly TeamStatsCalculator $statsCalculator,
        private readonly BetStatsService $betStatsService,
        private readonly SeasonStatsRepositoryInterface $seasonStatsRepository,
    ) {}

    private const LEAGUES = [
        'PD'  => ['flag' => '🇪🇸', 'name' => 'La Liga'],
        'SA'  => ['flag' => '🇮🇹', 'name' => 'Serie A'],
        'PL'  => ['flag' => '🏴󠁧󠁢󠁥󠁮󠁧󠁿', 'name' => 'Premier League'],
        'PPL' => ['flag' => '🇵🇹', 'name' => 'Primeira Liga'],
        'FL1' => ['flag' => '🇫🇷', 'name' => 'Ligue 1'],
    ];

    private function leagueContext(string $code): array
    {
        return [
            'currentLeague' => $code,
            'leagues'       => self::LEAGUES,
        ];
    }

    private function resolveLeague(Request $request): string
    {
        $code = $request->query->get('league', 'PD');
        return isset(self::LEAGUES[$code]) ? $code : 'PD';
    }

    #[Route('/dashboard', name: 'dashboard', methods: ['GET'])]
    public function dashboard(Request $request): Response
    {
        $league      = $this->resolveLeague($request);
        $competition = $this->competitionRepository->findByCode($league);

        if ($competition === null) {
            return new Response($this->twig->render('betting/dashboard.html.twig', ['byMatchday' => [], 'betsByMatchId' => [], 'summary' => null] + $this->leagueContext($league)));
        }

        $matches     = $this->matchRepository->findScheduledByCompetition($competition);
        $pendingBets = $this->betRepository->findPendingByCompetition($competition);

        $perspectiveOrder = ['home' => 0, 'both' => 1, 'away' => 2];

        $betsByMatchId = [];
        foreach ($pendingBets as $bet) {
            if (!$bet->skipped()) {
                $betsByMatchId[$bet->leagueMatch()->id()->toRfc4122()][] = $bet;
            }
        }

        foreach ($betsByMatchId as &$matchBets) {
            usort($matchBets, fn(Bet $a, Bet $b) =>
                ($perspectiveOrder[$a->perspective()] ?? 9) <=> ($perspectiveOrder[$b->perspective()] ?? 9)
            );
        }
        unset($matchBets);

        $byMatchday = [];
        foreach ($matches as $match) {
            $byMatchday[$match->matchday()][] = $match;
        }

        $summary = $this->buildDashboardSummary($competition, $byMatchday, $betsByMatchId);

        return new Response($this->twig->render('betting/dashboard.html.twig', [
            'byMatchday'    => $byMatchday,
            'betsByMatchId' => $betsByMatchId,
            'summary'       => $summary,
        ] + $this->leagueContext($league)));
    }

    #[Route('/history', name: 'history', methods: ['GET'])]
    public function history(Request $request): Response
    {
        $league      = $this->resolveLeague($request);
        $competition = $this->competitionRepository->findByCode($league);

        if ($competition === null) {
            return new Response($this->twig->render('betting/history.html.twig', ['byMatchday' => []] + $this->leagueContext($league)));
        }

        $settledBets = $this->betRepository->findSettledByCompetition($competition);

        $grouped = [];
        foreach ($settledBets as $bet) {
            $matchId = (string) $bet->leagueMatch()->id();
            if (!isset($grouped[$matchId])) {
                $grouped[$matchId] = ['match' => $bet->leagueMatch(), 'bets' => []];
            }
            $grouped[$matchId]['bets'][] = $bet;
        }

        $byMatchday = [];
        foreach ($grouped as $row) {
            $byMatchday[$row['match']->matchday()][] = $row;
        }
        krsort($byMatchday);

        return new Response($this->twig->render('betting/history.html.twig', [
            'byMatchday' => $byMatchday,
        ] + $this->leagueContext($league)));
    }

    #[Route('/stats', name: 'stats', methods: ['GET'])]
    public function stats(Request $request): Response
    {
        $league      = $this->resolveLeague($request);
        $competition = $this->competitionRepository->findByCode($league);

        if ($competition === null) {
            return new Response($this->twig->render('betting/stats.html.twig', ['data' => null, 'historicalList' => []] + $this->leagueContext($league)));
        }

        $bets = array_filter(
            $this->betRepository->findSettledByCompetition($competition),
            fn(Bet $b) => !$b->skipped()
        );

        $currentData    = count($bets) > 0 ? $this->betStatsService->buildStatsData($bets) : null;
        $historicalList = $this->seasonStatsRepository->findByCompetitionCode($competition->code());

        return new Response($this->twig->render('betting/stats.html.twig', [
            'data'           => $currentData,
            'historicalList' => $historicalList,
        ] + $this->leagueContext($league)));
    }

    private function buildDashboardSummary(
        \App\Domain\Tracking\Entity\Competition $competition,
        array $byMatchday,
        array $betsByMatchId,
    ): array {
        $settledBets = array_filter(
            $this->betRepository->findSettledByCompetition($competition),
            fn(Bet $b) => !$b->skipped(),
        );

        $won          = 0;
        $totalSettled = 0;
        $byMdSettled  = [];

        foreach ($settledBets as $bet) {
            $isWon = $bet->status() === Bet::STATUS_WON;
            $md    = $bet->leagueMatch()->matchday();
            $isWon ? $won++ : null;
            $totalSettled++;
            $byMdSettled[$md]['won']   = ($byMdSettled[$md]['won']   ?? 0) + ($isWon ? 1 : 0);
            $byMdSettled[$md]['total'] = ($byMdSettled[$md]['total'] ?? 0) + 1;
        }

        $nextMatchday      = array_key_first($byMatchday) ?? null;
        $perspectiveCounts = ['home' => 0, 'both' => 0, 'away' => 0];
        $matchesWithBets   = 0;
        $betTypeCounts     = [];

        if ($nextMatchday !== null) {
            foreach ($byMatchday[$nextMatchday] as $match) {
                $bets = $betsByMatchId[$match->id()->toRfc4122()] ?? [];
                if (!empty($bets)) {
                    $matchesWithBets++;
                    foreach ($bets as $bet) {
                        $perspectiveCounts[$bet->perspective()] = ($perspectiveCounts[$bet->perspective()] ?? 0) + 1;
                        $betTypeCounts[$bet->betType()]         = ($betTypeCounts[$bet->betType()]         ?? 0) + 1;
                    }
                }
            }
            arsort($betTypeCounts);
        }

        // Current matchday already-settled bets (partial results)
        $curMdWon   = $byMdSettled[$nextMatchday]['won']   ?? 0;
        $curMdTotal = $byMdSettled[$nextMatchday]['total'] ?? 0;

        $curMdMatchIds = [];
        foreach ($settledBets as $bet) {
            if ($bet->leagueMatch()->matchday() === $nextMatchday) {
                $curMdMatchIds[$bet->leagueMatch()->id()->toRfc4122()] = true;
            }
        }
        $curMdMatchesPlayed = count($curMdMatchIds);

        // Chart: current matchday (grey, leftmost) + last 4 completed matchdays newest→oldest
        krsort($byMdSettled);
        $completedMds   = array_filter($byMdSettled, fn($k) => $k !== $nextMatchday, ARRAY_FILTER_USE_KEY);
        $recentCompleted = array_slice($completedMds, 0, 4, true); // newest first
        $chartMatchdays  = ($nextMatchday !== null ? [$nextMatchday => ['won' => $curMdWon, 'total' => $curMdTotal, 'current' => true]] : [])
                         + $recentCompleted;

        return [
            'nextMatchday'      => $nextMatchday,
            'totalMatches'      => $nextMatchday !== null ? count($byMatchday[$nextMatchday]) : 0,
            'matchesWithBets'   => $matchesWithBets,
            'totalBets'         => array_sum($perspectiveCounts),
            'perspectiveCounts' => $perspectiveCounts,
            'topBetTypes'       => array_slice($betTypeCounts, 0, 3, true),
            'curMdWon'           => $curMdWon,
            'curMdLost'          => $curMdTotal - $curMdWon,
            'curMdTotal'         => $curMdTotal,
            'curMdMatchesPlayed' => $curMdMatchesPlayed,
            'won'               => $won,
            'totalSettled'      => $totalSettled,
            'rate'              => $totalSettled > 0 ? round($won / $totalSettled * 100) : 0,
            'chartMatchdays'    => $chartMatchdays,
        ];
    }

    #[Route('/team/{externalId}', name: 'team_stats', methods: ['GET'])]
    public function teamStats(int $externalId): Response
    {
        $team = $this->teamRepository->findByExternalId($externalId);

        if ($team === null) {
            return new Response('', Response::HTTP_NOT_FOUND);
        }

        $competition = $team->competition();
        $league      = $competition->code();

        $stats       = $this->statsCalculator->calculate($team, $competition);
        $settledBets = $this->betRepository->findSettledByCompetition($competition);

        $teamBets = array_filter(
            $settledBets,
            fn(Bet $b) => (string) $b->leagueMatch()->homeTeam()->id() === (string) $team->id()
                       || (string) $b->leagueMatch()->awayTeam()->id() === (string) $team->id()
        );

        $betsByType = [];
        foreach ($teamBets as $bet) {
            $betsByType[$bet->betType()][] = $bet;
        }
        ksort($betsByType);

        return new Response($this->twig->render('betting/team_stats.html.twig', [
            'team'       => $team,
            'stats'      => $stats,
            'betsByType' => $betsByType,
        ] + $this->leagueContext($league)));
    }
}
