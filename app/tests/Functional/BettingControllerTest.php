<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Domain\Betting\Entity\Bet;
use App\Domain\Betting\Repository\BetRepositoryInterface;
use App\Domain\Tracking\Entity\Competition;
use App\Domain\Tracking\Entity\LeagueMatch;
use App\Domain\Tracking\Entity\Team;
use App\Domain\Tracking\Repository\CompetitionRepositoryInterface;
use App\Domain\Tracking\Repository\LeagueMatchRepositoryInterface;
use App\Domain\Tracking\Repository\TeamRepositoryInterface;

class BettingControllerTest extends FunctionalTestCase
{
    private CompetitionRepositoryInterface $competitionRepository;
    private TeamRepositoryInterface $teamRepository;
    private LeagueMatchRepositoryInterface $matchRepository;
    private BetRepositoryInterface $betRepository;
    private Competition $competition;
    private Team $home;
    private Team $away;

    protected function setUp(): void
    {
        parent::setUp();
        $this->competitionRepository = static::getContainer()->get(CompetitionRepositoryInterface::class);
        $this->teamRepository        = static::getContainer()->get(TeamRepositoryInterface::class);
        $this->matchRepository       = static::getContainer()->get(LeagueMatchRepositoryInterface::class);
        $this->betRepository         = static::getContainer()->get(BetRepositoryInterface::class);

        $this->competition = Competition::create('PD', 'Primera División');
        $this->competitionRepository->save($this->competition);

        $this->home = Team::create(86, 'Real Madrid', $this->competition);
        $this->away = Team::create(81, 'Barcelona', $this->competition);
        $this->teamRepository->save($this->home);
        $this->teamRepository->save($this->away);
    }

    public function test_dashboard__should_list_upcoming_matches_with_active_bets(): void
    {
        $match = LeagueMatch::create(101, $this->competition, $this->home, $this->away, 1, new \DateTimeImmutable('+7 days'));
        $this->matchRepository->save($match);

        $bet = Bet::create($match, 'over_2_5', 'home');
        $this->betRepository->save($bet);
        $this->entityManager->clear();

        $this->client->request('GET', '/dashboard');

        $this->assertResponseIsSuccessful();
        $content = $this->client->getResponse()->getContent();
        $this->assertStringContainsString('Real Madrid', $content);
        $this->assertStringContainsString('Barcelona', $content);
        $this->assertStringContainsString('OVER 2 5', $content);
    }

    public function test_dashboard__when_no_bets__should_show_empty_state(): void
    {
        $this->client->request('GET', '/dashboard');

        $this->assertResponseIsSuccessful();
    }

    public function test_history__should_list_finished_matches_with_outcomes(): void
    {
        $match = LeagueMatch::create(201, $this->competition, $this->home, $this->away, 2, new \DateTimeImmutable('yesterday'));
        $match->finish(3, 1, 1, 0);
        $this->matchRepository->save($match);

        $bet = Bet::create($match, 'over_2_5', 'home');
        $bet->settle(new \DateTimeImmutable(), true);
        $this->betRepository->save($bet);
        $this->entityManager->clear();

        $this->client->request('GET', '/history');

        $this->assertResponseIsSuccessful();
        $content = $this->client->getResponse()->getContent();
        $this->assertStringContainsString('Real Madrid', $content);
        $this->assertStringContainsString('3', $content);
        $this->assertStringContainsString('Ganada', $content);
    }

    public function test_team_stats__should_show_stats_for_team(): void
    {
        $this->client->request('GET', '/team/86');

        $this->assertResponseIsSuccessful();
        $content = $this->client->getResponse()->getContent();
        $this->assertStringContainsString('Real Madrid', $content);
    }

    public function test_team_stats__when_team_not_found__should_return_404(): void
    {
        $this->client->request('GET', '/team/99999');

        $this->assertResponseStatusCodeSame(404);
    }
}
