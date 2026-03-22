<?php

declare(strict_types=1);

namespace App\Infrastructure\Betting\Http\Controller;

use App\Domain\Betting\Entity\Bet;
use App\Domain\Betting\Repository\BetRepositoryInterface;
use App\Domain\Betting\Service\TeamStatsCalculator;
use App\Domain\Tracking\Repository\CompetitionRepositoryInterface;
use App\Domain\Tracking\Repository\LeagueMatchRepositoryInterface;
use App\Domain\Tracking\Repository\TeamRepositoryInterface;
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
    ) {}

    #[Route('/dashboard', name: 'dashboard', methods: ['GET'])]
    public function dashboard(): Response
    {
        $competition = $this->competitionRepository->findByCode('PD');

        if ($competition === null) {
            return new Response($this->twig->render('betting/dashboard.html.twig', ['byMatchday' => [], 'betsByMatchId' => []]));
        }

        $matches     = $this->matchRepository->findScheduledByCompetition($competition);
        $pendingBets = $this->betRepository->findPendingByCompetition($competition);

        $betsByMatchId = [];
        foreach ($pendingBets as $bet) {
            if (!$bet->skipped()) {
                $betsByMatchId[$bet->leagueMatch()->id()->toRfc4122()][] = $bet;
            }
        }

        $byMatchday = [];
        foreach ($matches as $match) {
            $byMatchday[$match->matchday()][] = $match;
        }

        return new Response($this->twig->render('betting/dashboard.html.twig', [
            'byMatchday'   => $byMatchday,
            'betsByMatchId' => $betsByMatchId,
        ]));
    }

    #[Route('/history', name: 'history', methods: ['GET'])]
    public function history(): Response
    {
        $competition = $this->competitionRepository->findByCode('PD');

        if ($competition === null) {
            return new Response($this->twig->render('betting/history.html.twig', ['byMatchday' => []]));
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
        ]));
    }

    #[Route('/stats', name: 'stats', methods: ['GET'])]
    public function stats(): Response
    {
        $competition = $this->competitionRepository->findByCode('PD');

        if ($competition === null) {
            return new Response($this->twig->render('betting/stats.html.twig', ['data' => null]));
        }

        $bets = array_filter(
            $this->betRepository->findSettledByCompetition($competition),
            fn(Bet $b) => !$b->skipped()
        );

        $data = $this->buildStatsData($bets);

        return new Response($this->twig->render('betting/stats.html.twig', ['data' => $data]));
    }

    /** @param Bet[] $bets */
    private function buildStatsData(array $bets): array
    {
        $won  = 0;
        $lost = 0;
        $byType        = [];
        $byMatchday    = [];
        $byPerspective = [];
        $byTeam        = [];

        foreach ($bets as $bet) {
            $isWon     = $bet->status() === Bet::STATUS_WON;
            $matchday  = $bet->leagueMatch()->matchday();
            $type      = $bet->betType();
            $persp     = $bet->perspective();
            $homeTeam  = $bet->leagueMatch()->homeTeam()->name();
            $awayTeam  = $bet->leagueMatch()->awayTeam()->name();

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

        foreach ($byType     as &$g) { $g['rate'] = $rate($g); } unset($g);
        foreach ($byMatchday as &$g) { $g['rate'] = $rate($g); } unset($g);
        foreach ($byPerspective as &$g) { $g['rate'] = $rate($g); } unset($g);
        foreach ($byTeam     as &$g) { $g['rate'] = $rate($g); } unset($g);

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

    #[Route('/team/{externalId}', name: 'team_stats', methods: ['GET'])]
    public function teamStats(int $externalId): Response
    {
        $team        = $this->teamRepository->findByExternalId($externalId);
        $competition = $this->competitionRepository->findByCode('PD');

        if ($team === null || $competition === null) {
            return new Response('', Response::HTTP_NOT_FOUND);
        }

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
        ]));
    }
}
