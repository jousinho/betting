<?php

declare(strict_types=1);

namespace App\Tests\Integration\Infrastructure\Betting;

use App\Application\Betting\Service\BetGeneratorService;
use App\Domain\Betting\Entity\Bet;
use App\Domain\Betting\Repository\BetRepositoryInterface;
use App\Domain\Tracking\Entity\Competition;
use App\Domain\Tracking\Entity\LeagueMatch;
use App\Domain\Tracking\Entity\Team;
use App\Domain\Tracking\Repository\CompetitionRepositoryInterface;
use App\Domain\Tracking\Repository\LeagueMatchRepositoryInterface;
use App\Domain\Tracking\Repository\TeamRepositoryInterface;
use App\Tests\Integration\IntegrationTestCase;

class BetGeneratorServiceTest extends IntegrationTestCase
{
    private BetGeneratorService $generatorService;
    private BetRepositoryInterface $betRepository;
    private LeagueMatchRepositoryInterface $matchRepository;
    private Competition $competition;
    private Team $home;
    private Team $away;

    protected function setUp(): void
    {
        parent::setUp();

        $this->generatorService = static::getContainer()->get(BetGeneratorService::class);
        $this->betRepository    = static::getContainer()->get(BetRepositoryInterface::class);
        $this->matchRepository  = static::getContainer()->get(LeagueMatchRepositoryInterface::class);

        $competitionRepo = static::getContainer()->get(CompetitionRepositoryInterface::class);
        $teamRepo        = static::getContainer()->get(TeamRepositoryInterface::class);

        $this->competition = Competition::create('PD', 'Primera División');
        $competitionRepo->save($this->competition);

        $this->home = Team::create(1, 'Real Madrid', $this->competition);
        $this->away = Team::create(2, 'Barcelona', $this->competition);
        $teamRepo->save($this->home);
        $teamRepo->save($this->away);
    }

    public function test_generating_bets__when_criterion_not_met__should_not_create_bet(): void
    {
        $match = LeagueMatch::create(101, $this->competition, $this->home, $this->away, 1, new \DateTimeImmutable('+7 days'));
        $this->matchRepository->save($match);

        // No finished matches → no stats → no criteria met
        $this->generatorService->generate($this->competition);

        $bets = $this->betRepository->findByMatch($match);
        $this->assertCount(0, $bets);
    }

    public function test_generating_bets__when_criterion_met__should_create_pending_bet(): void
    {
        // Build enough home history for over_2_5 home trigger (8/10 games over 2.5 → 0.80 >= 0.75)
        $this->persistFinishedHomeMatches(10, 8);

        $match = LeagueMatch::create(201, $this->competition, $this->home, $this->away, 11, new \DateTimeImmutable('+7 days'));
        $this->matchRepository->save($match);

        $this->generatorService->generate($this->competition);

        $bets     = $this->betRepository->findByMatch($match);
        $betTypes = array_map(fn(Bet $b) => $b->betType(), $bets);

        $this->assertContains('over_2_5', $betTypes);

        $over25 = array_values(array_filter($bets, fn(Bet $b) => $b->betType() === 'over_2_5'))[0];
        $this->assertSame(Bet::STATUS_PENDING, $over25->status());
        $this->assertFalse($over25->skipped());
    }

    public function test_generating_bets__when_called_twice__should_not_duplicate_bets(): void
    {
        $this->persistFinishedHomeMatches(10, 8);

        $match = LeagueMatch::create(301, $this->competition, $this->home, $this->away, 11, new \DateTimeImmutable('+7 days'));
        $this->matchRepository->save($match);

        $this->generatorService->generate($this->competition);
        $this->generatorService->generate($this->competition);

        $bets     = $this->betRepository->findByMatch($match);
        $betTypes = array_map(fn(Bet $b) => $b->betType(), $bets);

        $this->assertSame(array_unique($betTypes), $betTypes, 'No duplicate bet types should exist');
    }

    public function test_generating_bets__when_conflicting_home_and_away__should_skip_away_bet(): void
    {
        // Home team: over_3_5 triggers home (5/10 >= 0.50) and under_2_5 would trigger away
        // Away team: under_2_5 triggers away (many low-scoring away games)
        // Setup: home team has 5/10 over 3.5 games → over_3_5 home bet created
        //        away team has 6/10 under 2.5 away games → under_2_5 away bet would be created but skipped

        $this->persistFinishedHomeMatches(10, 5, goalsPerMatch: 4); // 5 matches with 4 goals → over 3.5
        $this->persistFinishedAwayMatches(10, 0, goalsPerMatch: 2); // 10 away matches with 2 goals → under 2.5

        $match = LeagueMatch::create(401, $this->competition, $this->home, $this->away, 11, new \DateTimeImmutable('+7 days'));
        $this->matchRepository->save($match);

        $this->generatorService->generate($this->competition);

        $bets = $this->betRepository->findByMatch($match);

        $over35bet   = $this->findBetByType($bets, 'over_3_5');
        $under25bet  = $this->findBetByType($bets, 'under_2_5');

        $this->assertNotNull($over35bet, 'over_3_5 bet should exist');
        $this->assertFalse($over35bet->skipped());

        if ($under25bet !== null) {
            $this->assertTrue($under25bet->skipped(), 'under_2_5 away bet should be skipped due to over_3_5 home conflict');
        }
    }

    private function persistFinishedHomeMatches(int $total, int $highScoringCount, int $goalsPerMatch = 3): void
    {
        $opponent = Team::create(99, 'Opponent', $this->competition);
        static::getContainer()->get(TeamRepositoryInterface::class)->save($opponent);

        static $matchId = 1000;
        for ($i = 0; $i < $total; $i++) {
            $highScoring = $i < $highScoringCount;
            $home        = $highScoring ? $goalsPerMatch : 1;
            $away        = $highScoring ? 1 : 0;
            $match       = LeagueMatch::create(++$matchId, $this->competition, $this->home, $opponent, $i + 1, new \DateTimeImmutable("-$i days -1 hour"));
            $match->finish($home, $away, intdiv($home, 2), intdiv($away, 2));
            $this->matchRepository->save($match);
        }
    }

    private function persistFinishedAwayMatches(int $total, int $highScoringCount, int $goalsPerMatch = 1): void
    {
        $opponent = Team::create(98, 'Opponent2', $this->competition);
        static::getContainer()->get(TeamRepositoryInterface::class)->save($opponent);

        static $awayMatchId = 2000;
        for ($i = 0; $i < $total; $i++) {
            $highScoring = $i < $highScoringCount;
            $home        = $highScoring ? $goalsPerMatch : 0;
            $away        = $highScoring ? $goalsPerMatch : 1;
            $match       = LeagueMatch::create(++$awayMatchId, $this->competition, $opponent, $this->away, $i + 1, new \DateTimeImmutable("-$i days -2 hour"));
            $match->finish($home, $away, intdiv($home, 2), intdiv($away, 2));
            $this->matchRepository->save($match);
        }
    }

    /** @param Bet[] $bets */
    private function findBetByType(array $bets, string $betType): ?Bet
    {
        foreach ($bets as $bet) {
            if ($bet->betType() === $betType) {
                return $bet;
            }
        }
        return null;
    }
}
