<?php

declare(strict_types=1);

namespace App\Tests\Unit\Application\Betting;

use App\Application\Betting\Service\OddsSyncService;
use App\Domain\Betting\Entity\Bet;
use App\Domain\Betting\Repository\BetRepositoryInterface;
use App\Domain\Betting\Repository\OddsProviderInterface;
use App\Domain\Tracking\Entity\Competition;
use App\Domain\Tracking\Entity\LeagueMatch;
use App\Domain\Tracking\Entity\Team;
use App\Domain\Tracking\Repository\LeagueMatchRepositoryInterface;
use PHPUnit\Framework\TestCase;

class OddsSyncServiceTest extends TestCase
{
    private Competition $competition;

    protected function setUp(): void
    {
        $this->competition = Competition::create('PD', 'Primera División');
    }

    public function test_syncing_odds__when_no_eligible_bets__should_not_call_api(): void
    {
        $provider = $this->createMock(OddsProviderInterface::class);
        $provider->expects($this->never())->method('fetchBulkOdds');

        $betRepo = $this->createStub(BetRepositoryInterface::class);
        $betRepo->method('findPendingWithoutOddsWithinDays')->willReturn([]);

        $service = $this->makeService($provider, $betRepo);
        $service->syncForCompetition($this->competition);
    }

    public function test_syncing_odds__when_team_odds_name_is_null__should_skip(): void
    {
        $match = $this->makeMatch(homeName: null, awayName: 'Elche CF');
        $bet   = Bet::create($match, 'home_win', 'home');

        $provider = $this->createStub(OddsProviderInterface::class);
        $provider->method('fetchBulkOdds')->willReturn($this->bulkResponse());

        $betRepo = $this->createMock(BetRepositoryInterface::class);
        $betRepo->method('findPendingWithoutOddsWithinDays')->willReturn([$bet]);
        $betRepo->expects($this->never())->method('save');

        $this->makeService($provider, $betRepo)->syncForCompetition($this->competition);

        $this->assertNull($bet->odds());
    }

    public function test_syncing_odds__when_h2h_market_available__should_set_odds_on_home_win_bet(): void
    {
        $match = $this->makeMatch('Rayo Vallecano', 'Elche CF');
        $bet   = Bet::create($match, 'home_win', 'home');

        $service = $this->makeServiceWithBulk([$bet], $this->bulkResponse());
        $service->syncForCompetition($this->competition);

        $this->assertSame(1.87, $bet->odds());
    }

    public function test_syncing_odds__when_totals_available__should_set_odds_on_over25_bet(): void
    {
        $match = $this->makeMatch('Rayo Vallecano', 'Elche CF');
        $bet   = Bet::create($match, 'over_2_5', 'home');

        $this->makeServiceWithBulk([$bet], $this->bulkResponse())->syncForCompetition($this->competition);

        $this->assertSame(2.07, $bet->odds());
    }

    public function test_syncing_odds__when_market_not_available__should_leave_odds_null(): void
    {
        $match = $this->makeMatch('Rayo Vallecano', 'Elche CF');
        $bet   = Bet::create($match, 'over_1_5', 'home');

        $this->makeServiceWithBulk([$bet], $this->bulkResponse())->syncForCompetition($this->competition);

        $this->assertNull($bet->odds());
    }

    public function test_syncing_odds__when_bet365_available__should_use_bet365_odds(): void
    {
        $match = $this->makeMatch('Rayo Vallecano', 'Elche CF');
        $bet   = Bet::create($match, 'home_win', 'home');

        $bulk = $this->bulkResponse();
        $bulk['event1']['bookmakers']['bet365'] = [
            'h2h' => [
                ['name' => 'Rayo Vallecano', 'price' => 1.75],
                ['name' => 'Elche CF', 'price' => 4.50],
                ['name' => 'Draw', 'price' => 3.40],
            ],
        ];

        $this->makeServiceWithBulk([$bet], $bulk)->syncForCompetition($this->competition);

        $this->assertSame(1.75, $bet->odds());
    }

    public function test_syncing_odds__when_bet365_not_available_but_pinnacle_is__should_use_pinnacle(): void
    {
        $match = $this->makeMatch('Rayo Vallecano', 'Elche CF');
        $bet   = Bet::create($match, 'home_win', 'home');

        $this->makeServiceWithBulk([$bet], $this->bulkResponse())->syncForCompetition($this->competition);

        $this->assertSame(1.87, $bet->odds());
    }

    public function test_syncing_odds__when_btts_market__should_call_event_endpoint(): void
    {
        $match = $this->makeMatch('Rayo Vallecano', 'Elche CF', oddsEventId: 'event1');
        $bet   = Bet::create($match, 'btts', 'home');

        $provider = $this->createMock(OddsProviderInterface::class);
        $provider->method('fetchBulkOdds')->willReturn($this->bulkResponse());
        $provider->expects($this->once())
            ->method('fetchEventOdds')
            ->with('event1')
            ->willReturn($this->eventResponse());

        $betRepo = $this->createStub(BetRepositoryInterface::class);
        $betRepo->method('findPendingWithoutOddsWithinDays')->willReturn([$bet]);

        $this->makeService($provider, $betRepo)->syncForCompetition($this->competition);

        $this->assertSame(1.85, $bet->odds());
    }

    public function test_syncing_odds__when_multiple_event_market_bets__should_call_event_endpoint_once(): void
    {
        $match = $this->makeMatch('Rayo Vallecano', 'Elche CF', oddsEventId: 'event1');
        $bet1  = Bet::create($match, 'btts', 'home');
        $bet2  = Bet::create($match, 'double_chance', 'home');

        $provider = $this->createMock(OddsProviderInterface::class);
        $provider->method('fetchBulkOdds')->willReturn($this->bulkResponse());
        $provider->expects($this->once())->method('fetchEventOdds')->willReturn($this->eventResponse());

        $betRepo = $this->createStub(BetRepositoryInterface::class);
        $betRepo->method('findPendingWithoutOddsWithinDays')->willReturn([$bet1, $bet2]);

        $this->makeService($provider, $betRepo)->syncForCompetition($this->competition);
    }

    private function makeService(
        OddsProviderInterface $provider,
        BetRepositoryInterface $betRepo,
    ): OddsSyncService {
        return new OddsSyncService(
            $provider,
            $betRepo,
            $this->createStub(LeagueMatchRepositoryInterface::class),
        );
    }

    private function makeServiceWithBulk(array $bets, array $bulkResponse): OddsSyncService
    {
        $provider = $this->createStub(OddsProviderInterface::class);
        $provider->method('fetchBulkOdds')->willReturn($bulkResponse);

        $betRepo = $this->createStub(BetRepositoryInterface::class);
        $betRepo->method('findPendingWithoutOddsWithinDays')->willReturn($bets);

        return $this->makeService($provider, $betRepo);
    }

    private function makeMatch(
        ?string $homeName,
        ?string $awayName,
        ?string $oddsEventId = null,
    ): LeagueMatch {
        $home = Team::create(1, 'Home Team', $this->competition);
        $away = Team::create(2, 'Away Team', $this->competition);
        $home->setOddsName($homeName);
        $away->setOddsName($awayName);

        $match = LeagueMatch::create(
            externalId: 999,
            competition: $this->competition,
            homeTeam: $home,
            awayTeam: $away,
            matchday: 1,
            playedAt: new \DateTimeImmutable('+3 days'),
        );

        if ($oddsEventId !== null) {
            $match->setOddsEventId($oddsEventId);
        }

        return $match;
    }

    private function bulkResponse(): array
    {
        return [
            'event1' => [
                'home_team'  => 'Rayo Vallecano',
                'away_team'  => 'Elche CF',
                'bookmakers' => [
                    'pinnacle' => [
                        'h2h' => [
                            ['name' => 'Rayo Vallecano', 'price' => 1.87],
                            ['name' => 'Elche CF', 'price' => 4.83],
                            ['name' => 'Draw', 'price' => 3.65],
                        ],
                        'totals' => [
                            ['name' => 'Over', 'price' => 2.07, 'point' => 2.5],
                            ['name' => 'Under', 'price' => 1.88, 'point' => 2.5],
                        ],
                    ],
                ],
            ],
        ];
    }

    private function eventResponse(): array
    {
        return [
            'bookmakers' => [
                'pinnacle' => [
                    'btts' => [
                        ['name' => 'Yes', 'price' => 1.85],
                        ['name' => 'No', 'price' => 1.87],
                    ],
                    'double_chance' => [
                        ['name' => 'Rayo Vallecano or Draw', 'price' => 1.22],
                        ['name' => 'Elche CF or Draw', 'price' => 2.04],
                        ['name' => 'Rayo Vallecano or Elche CF', 'price' => 1.33],
                    ],
                ],
            ],
        ];
    }
}
