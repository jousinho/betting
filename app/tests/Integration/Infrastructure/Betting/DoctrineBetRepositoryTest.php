<?php

declare(strict_types=1);

namespace App\Tests\Integration\Infrastructure\Betting;

use App\Domain\Betting\Entity\Bet;
use App\Domain\Betting\Repository\BetRepositoryInterface;
use App\Domain\Tracking\Entity\Competition;
use App\Domain\Tracking\Entity\LeagueMatch;
use App\Domain\Tracking\Entity\Team;
use App\Domain\Tracking\Repository\CompetitionRepositoryInterface;
use App\Domain\Tracking\Repository\LeagueMatchRepositoryInterface;
use App\Domain\Tracking\Repository\TeamRepositoryInterface;
use App\Tests\Integration\IntegrationTestCase;

class DoctrineBetRepositoryTest extends IntegrationTestCase
{
    private BetRepositoryInterface $betRepository;
    private Competition $competition;
    private LeagueMatch $match;

    protected function setUp(): void
    {
        parent::setUp();

        $this->betRepository = static::getContainer()->get(BetRepositoryInterface::class);

        $competitionRepo = static::getContainer()->get(CompetitionRepositoryInterface::class);
        $teamRepo        = static::getContainer()->get(TeamRepositoryInterface::class);
        $matchRepo       = static::getContainer()->get(LeagueMatchRepositoryInterface::class);

        $this->competition = Competition::create('PD', 'Primera División');
        $competitionRepo->save($this->competition);

        $home = Team::create(1, 'Real Madrid', $this->competition);
        $away = Team::create(2, 'Barcelona', $this->competition);
        $teamRepo->save($home);
        $teamRepo->save($away);

        $this->match = LeagueMatch::create(101, $this->competition, $home, $away, 1, new \DateTimeImmutable('yesterday'));
        $matchRepo->save($this->match);
    }

    public function test_saving_bet__should_be_retrievable_by_match(): void
    {
        $bet = Bet::create($this->match, 'over_2_5', 'home');
        $this->betRepository->save($bet);
        $this->entityManager->clear();

        $found = $this->betRepository->findByMatch($this->match);

        $this->assertCount(1, $found);
        $this->assertSame('over_2_5', $found[0]->betType());
        $this->assertSame('home', $found[0]->perspective());
        $this->assertSame(Bet::STATUS_PENDING, $found[0]->status());
        $this->assertFalse($found[0]->skipped());
    }

    public function test_finding_pending__should_only_return_pending_bets(): void
    {
        $pending = Bet::create($this->match, 'over_2_5', 'home');
        $won     = Bet::create($this->match, 'btts', 'away');
        $won->settle(new \DateTimeImmutable(), true);

        $this->betRepository->save($pending);
        $this->betRepository->save($won);
        $this->entityManager->clear();

        $result = $this->betRepository->findPendingByCompetition($this->competition);

        $this->assertCount(1, $result);
        $this->assertSame('over_2_5', $result[0]->betType());
    }

    public function test_finding_settled__should_only_return_won_and_lost(): void
    {
        $pending = Bet::create($this->match, 'over_2_5', 'home');
        $won     = Bet::create($this->match, 'btts', 'away');
        $lost    = Bet::create($this->match, 'over_3_5', 'home');
        $won->settle(new \DateTimeImmutable(), true);
        $lost->settle(new \DateTimeImmutable(), false);

        $this->betRepository->save($pending);
        $this->betRepository->save($won);
        $this->betRepository->save($lost);
        $this->entityManager->clear();

        $result = $this->betRepository->findSettledByCompetition($this->competition);

        $this->assertCount(2, $result);
        $statuses = array_map(fn(Bet $b) => $b->status(), $result);
        $this->assertContains(Bet::STATUS_WON, $statuses);
        $this->assertContains(Bet::STATUS_LOST, $statuses);
    }

    public function test_exists_for_match_and_type__when_exists__should_return_true(): void
    {
        $bet = Bet::create($this->match, 'over_2_5', 'home');
        $this->betRepository->save($bet);
        $this->entityManager->clear();

        $this->assertTrue($this->betRepository->existsForMatchAndType($this->match, 'over_2_5'));
    }

    public function test_exists_for_match_and_type__when_not_exists__should_return_false(): void
    {
        $this->assertFalse($this->betRepository->existsForMatchAndType($this->match, 'over_2_5'));
    }
}
