<?php

declare(strict_types=1);

namespace App\Tests\Unit\Domain\Betting\Service;

use App\Domain\Betting\Service\TeamStatsCalculator;
use App\Domain\Tracking\Entity\Competition;
use App\Domain\Tracking\Entity\LeagueMatch;
use App\Domain\Tracking\Entity\Team;
use App\Domain\Tracking\Repository\LeagueMatchRepositoryInterface;
use PHPUnit\Framework\TestCase;

class TeamStatsCalculatorTest extends TestCase
{
    private Competition $competition;
    private Team $team;
    private Team $opponent;

    protected function setUp(): void
    {
        $this->competition = Competition::create('PD', 'Primera División');
        $this->team        = Team::create(1, 'Real Madrid', $this->competition);
        $this->opponent    = Team::create(2, 'Barcelona', $this->competition);
    }

    public function test_calculating_stats__should_count_home_and_away_matches_separately(): void
    {
        $homeMatch = $this->finishedMatch($this->team, $this->opponent, 2, 1, 1, 0, '2024-01-01');
        $awayMatch = $this->finishedMatch($this->opponent, $this->team, 1, 2, 0, 1, '2024-01-08');

        $repo = $this->createStub(LeagueMatchRepositoryInterface::class);
        $repo->method('findFinishedByCompetition')->willReturn([$homeMatch, $awayMatch]);

        $calculator = new TeamStatsCalculator($repo);
        $stats      = $calculator->calculate($this->team, $this->competition);

        $this->assertSame(1, $stats->matchesPlayedHome);
        $this->assertSame(1, $stats->matchesPlayedAway);
    }

    public function test_calculating_stats__should_compute_form_last5_home_as_string(): void
    {
        $matches = [
            $this->finishedMatch($this->team, $this->opponent, 2, 0, 1, 0, '2024-01-05'),
            $this->finishedMatch($this->team, $this->opponent, 1, 1, 0, 0, '2024-01-04'),
            $this->finishedMatch($this->team, $this->opponent, 0, 2, 0, 1, '2024-01-03'),
            $this->finishedMatch($this->team, $this->opponent, 3, 1, 1, 0, '2024-01-02'),
            $this->finishedMatch($this->team, $this->opponent, 1, 0, 0, 0, '2024-01-01'),
        ];

        $repo = $this->createStub(LeagueMatchRepositoryInterface::class);
        $repo->method('findFinishedByCompetition')->willReturn($matches);

        $calculator = new TeamStatsCalculator($repo);
        $stats      = $calculator->calculate($this->team, $this->competition);

        // sorted desc by date: Jan05(W), Jan04(D), Jan03(L), Jan02(W), Jan01(W)
        $this->assertSame('WDLWW', $stats->formLast5Home);
    }

    public function test_calculating_stats__should_compute_over25_counters(): void
    {
        $matches = [
            $this->finishedMatch($this->team, $this->opponent, 2, 1, 1, 0, '2024-01-01'), // 3 goals > 2.5 ✓
            $this->finishedMatch($this->team, $this->opponent, 1, 1, 0, 1, '2024-01-02'), // 2 goals
            $this->finishedMatch($this->team, $this->opponent, 3, 2, 2, 1, '2024-01-03'), // 5 goals > 2.5 ✓
        ];

        $repo = $this->createStub(LeagueMatchRepositoryInterface::class);
        $repo->method('findFinishedByCompetition')->willReturn($matches);

        $calculator = new TeamStatsCalculator($repo);
        $stats      = $calculator->calculate($this->team, $this->competition);

        $this->assertSame(2, $stats->over25Home);
    }

    public function test_calculating_stats__should_compute_over05ht_from_halftime_goals(): void
    {
        $matches = [
            $this->finishedMatch($this->team, $this->opponent, 2, 1, 1, 0, '2024-01-01'), // HT: 1 goal > 0.5 ✓
            $this->finishedMatch($this->team, $this->opponent, 1, 1, 0, 0, '2024-01-02'), // HT: 0 goals
            $this->finishedMatch($this->team, $this->opponent, 0, 0, 1, 1, '2024-01-03'), // HT: 2 goals > 0.5 ✓
        ];

        $repo = $this->createStub(LeagueMatchRepositoryInterface::class);
        $repo->method('findFinishedByCompetition')->willReturn($matches);

        $calculator = new TeamStatsCalculator($repo);
        $stats      = $calculator->calculate($this->team, $this->competition);

        $this->assertSame(2, $stats->over05HtHome);
    }

    public function test_calculating_stats__should_compute_btts_for_home_matches(): void
    {
        $matches = [
            $this->finishedMatch($this->team, $this->opponent, 2, 1, 1, 0, '2024-01-01'), // both scored ✓
            $this->finishedMatch($this->team, $this->opponent, 2, 0, 1, 0, '2024-01-02'), // only home scored
            $this->finishedMatch($this->team, $this->opponent, 1, 2, 0, 1, '2024-01-03'), // both scored ✓
        ];

        $repo = $this->createStub(LeagueMatchRepositoryInterface::class);
        $repo->method('findFinishedByCompetition')->willReturn($matches);

        $calculator = new TeamStatsCalculator($repo);
        $stats      = $calculator->calculate($this->team, $this->competition);

        $this->assertSame(2, $stats->bttsHome);
    }

    public function test_calculating_stats__should_compute_clean_sheet_home(): void
    {
        $matches = [
            $this->finishedMatch($this->team, $this->opponent, 1, 0, 1, 0, '2024-01-01'), // away scored 0 ✓
            $this->finishedMatch($this->team, $this->opponent, 2, 1, 1, 0, '2024-01-02'), // away scored 1
            $this->finishedMatch($this->team, $this->opponent, 3, 0, 2, 0, '2024-01-03'), // away scored 0 ✓
        ];

        $repo = $this->createStub(LeagueMatchRepositoryInterface::class);
        $repo->method('findFinishedByCompetition')->willReturn($matches);

        $calculator = new TeamStatsCalculator($repo);
        $stats      = $calculator->calculate($this->team, $this->competition);

        $this->assertSame(2, $stats->cleanSheetHome);
    }

    public function test_calculating_stats__should_only_use_last5_for_form(): void
    {
        $matches = [
            $this->finishedMatch($this->team, $this->opponent, 2, 0, 1, 0, '2024-01-06'),
            $this->finishedMatch($this->team, $this->opponent, 2, 0, 1, 0, '2024-01-05'),
            $this->finishedMatch($this->team, $this->opponent, 2, 0, 1, 0, '2024-01-04'),
            $this->finishedMatch($this->team, $this->opponent, 2, 0, 1, 0, '2024-01-03'),
            $this->finishedMatch($this->team, $this->opponent, 2, 0, 1, 0, '2024-01-02'),
            $this->finishedMatch($this->team, $this->opponent, 0, 2, 0, 1, '2024-01-01'), // older — not in form
        ];

        $repo = $this->createStub(LeagueMatchRepositoryInterface::class);
        $repo->method('findFinishedByCompetition')->willReturn($matches);

        $calculator = new TeamStatsCalculator($repo);
        $stats      = $calculator->calculate($this->team, $this->competition);

        $this->assertSame('WWWWW', $stats->formLast5Home);
    }

    private function finishedMatch(
        Team $home,
        Team $away,
        int $homeGoalsFt,
        int $awayGoalsFt,
        int $homeGoalsHt,
        int $awayGoalsHt,
        string $date,
    ): LeagueMatch {
        static $id = 1;
        $match = LeagueMatch::create(
            $id++,
            $this->competition,
            $home,
            $away,
            1,
            new \DateTimeImmutable($date),
        );
        $match->finish($homeGoalsFt, $awayGoalsFt, $homeGoalsHt, $awayGoalsHt);
        return $match;
    }
}
