<?php

declare(strict_types=1);

namespace App\Tests\Integration\Infrastructure\Betting;

use App\Application\Betting\Service\BetSettlementService;
use App\Domain\Betting\Entity\Bet;
use App\Domain\Betting\Repository\BetRepositoryInterface;
use App\Domain\Tracking\Entity\Competition;
use App\Domain\Tracking\Entity\LeagueMatch;
use App\Domain\Tracking\Entity\Team;
use App\Domain\Tracking\Repository\CompetitionRepositoryInterface;
use App\Domain\Tracking\Repository\LeagueMatchRepositoryInterface;
use App\Domain\Tracking\Repository\TeamRepositoryInterface;
use App\Tests\Integration\IntegrationTestCase;

class BetSettlementServiceTest extends IntegrationTestCase
{
    private BetSettlementService $settlementService;
    private BetRepositoryInterface $betRepository;
    private LeagueMatchRepositoryInterface $matchRepository;
    private Competition $competition;
    private Team $home;
    private Team $away;

    protected function setUp(): void
    {
        parent::setUp();

        $this->settlementService = static::getContainer()->get(BetSettlementService::class);
        $this->betRepository     = static::getContainer()->get(BetRepositoryInterface::class);
        $this->matchRepository   = static::getContainer()->get(LeagueMatchRepositoryInterface::class);

        $competitionRepo = static::getContainer()->get(CompetitionRepositoryInterface::class);
        $teamRepo        = static::getContainer()->get(TeamRepositoryInterface::class);

        $this->competition = Competition::create('PD', 'Primera División');
        $competitionRepo->save($this->competition);

        $this->home = Team::create(1, 'Real Madrid', $this->competition);
        $this->away = Team::create(2, 'Barcelona', $this->competition);
        $teamRepo->save($this->home);
        $teamRepo->save($this->away);
    }

    public function test_settling__when_over25_wins__should_mark_won(): void
    {
        $match = $this->finishedMatch(3, 1, 1, 0);
        $bet   = Bet::create($match, 'over_2_5', 'home');
        $this->betRepository->save($bet);
        $this->entityManager->clear();

        $this->settlementService->settleAll($this->competition);

        $settled = $this->betRepository->findSettledByCompetition($this->competition);
        $this->assertCount(1, $settled);
        $this->assertSame(Bet::STATUS_WON, $settled[0]->status());
    }

    public function test_settling__when_over25_loses__should_mark_lost(): void
    {
        $match = $this->finishedMatch(1, 1, 0, 1);
        $bet   = Bet::create($match, 'over_2_5', 'home');
        $this->betRepository->save($bet);
        $this->entityManager->clear();

        $this->settlementService->settleAll($this->competition);

        $settled = $this->betRepository->findSettledByCompetition($this->competition);
        $this->assertCount(1, $settled);
        $this->assertSame(Bet::STATUS_LOST, $settled[0]->status());
    }

    public function test_settling__when_home_win_and_home_wins__should_mark_won(): void
    {
        $match = $this->finishedMatch(2, 0, 1, 0);
        $bet   = Bet::create($match, 'home_win', 'home');
        $this->betRepository->save($bet);
        $this->entityManager->clear();

        $this->settlementService->settleAll($this->competition);

        $settled = $this->betRepository->findSettledByCompetition($this->competition);
        $this->assertSame(Bet::STATUS_WON, $settled[0]->status());
    }

    public function test_settling__when_skipped_bet__should_still_settle_for_stats(): void
    {
        $match = $this->finishedMatch(1, 1, 0, 0);
        $bet   = Bet::create($match, 'under_2_5', 'away', skipped: true);
        $this->betRepository->save($bet);
        $this->entityManager->clear();

        $this->settlementService->settleAll($this->competition);

        $settled = $this->betRepository->findSettledByCompetition($this->competition);
        $this->assertCount(1, $settled);
        $this->assertTrue($settled[0]->skipped());
        $this->assertSame(Bet::STATUS_WON, $settled[0]->status());
    }

    public function test_settling__when_match_not_finished__should_not_settle(): void
    {
        $match = LeagueMatch::create(501, $this->competition, $this->home, $this->away, 1, new \DateTimeImmutable('+7 days'));
        $this->matchRepository->save($match);
        $bet = Bet::create($match, 'over_2_5', 'home');
        $this->betRepository->save($bet);
        $this->entityManager->clear();

        $this->settlementService->settleAll($this->competition);

        $pending = $this->betRepository->findPendingByCompetition($this->competition);
        $this->assertCount(1, $pending);
    }

    private function finishedMatch(int $hFt, int $aFt, int $hHt, int $aHt): LeagueMatch
    {
        static $id = 1001;
        $match = LeagueMatch::create(++$id, $this->competition, $this->home, $this->away, 1, new \DateTimeImmutable('yesterday'));
        $match->finish($hFt, $aFt, $hHt, $aHt);
        $this->matchRepository->save($match);
        return $match;
    }
}
