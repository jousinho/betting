<?php

declare(strict_types=1);

namespace App\Tests\Unit\Infrastructure\Tracking;

use App\Infrastructure\Tracking\Http\Client\FootballDataClient;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

class FootballDataClientTest extends TestCase
{
    private HttpClientInterface&MockObject $httpClient;
    private FootballDataClient $client;

    protected function setUp(): void
    {
        $this->httpClient = $this->createMock(HttpClientInterface::class);
        $this->client = new FootballDataClient($this->httpClient, 'test-api-key');
    }

    public function test_fetching_teams__should_map_api_response_to_array_with_external_id_and_name(): void
    {
        $this->httpClient->expects($this->once())
            ->method('request')
            ->with('GET', 'https://api.football-data.org/v4/competitions/PD/teams', $this->callback(
                fn(array $opts) => $opts['headers']['X-Auth-Token'] === 'test-api-key'
            ))
            ->willReturn($this->makeResponse([
                'teams' => [
                    ['id' => 86, 'name' => 'Real Madrid CF'],
                    ['id' => 81, 'name' => 'FC Barcelona'],
                ],
            ]));

        $teams = $this->client->fetchTeams('PD');

        $this->assertCount(2, $teams);
        $this->assertSame(86, $teams[0]['id']);
        $this->assertSame('Real Madrid CF', $teams[0]['name']);
        $this->assertSame(81, $teams[1]['id']);
        $this->assertSame('FC Barcelona', $teams[1]['name']);
    }

    public function test_fetching_league_matches__should_map_response_with_scores_and_matchday(): void
    {
        $this->httpClient->expects($this->once())
            ->method('request')
            ->with('GET', 'https://api.football-data.org/v4/competitions/PD/matches', $this->anything())
            ->willReturn($this->makeResponse([
                'matches' => [
                    [
                        'id'         => 1001,
                        'matchday'   => 5,
                        'utcDate'    => '2025-10-01T20:00:00Z',
                        'status'     => 'FINISHED',
                        'homeTeam'   => ['id' => 86],
                        'awayTeam'   => ['id' => 81],
                        'score'      => [
                            'fullTime' => ['home' => 2, 'away' => 1],
                            'halfTime' => ['home' => 1, 'away' => 0],
                        ],
                    ],
                    [
                        'id'         => 1002,
                        'matchday'   => 6,
                        'utcDate'    => '2025-10-20T18:00:00Z',
                        'status'     => 'SCHEDULED',
                        'homeTeam'   => ['id' => 77],
                        'awayTeam'   => ['id' => 86],
                        'score'      => [
                            'fullTime' => ['home' => null, 'away' => null],
                            'halfTime' => ['home' => null, 'away' => null],
                        ],
                    ],
                ],
            ]));

        $matches = $this->client->fetchLeagueMatches('PD');

        $this->assertCount(2, $matches);

        $this->assertSame(1001, $matches[0]['id']);
        $this->assertSame(5, $matches[0]['matchday']);
        $this->assertSame('FINISHED', $matches[0]['status']);
        $this->assertSame(86, $matches[0]['homeTeamId']);
        $this->assertSame(81, $matches[0]['awayTeamId']);
        $this->assertSame(2, $matches[0]['homeGoalsFt']);
        $this->assertSame(1, $matches[0]['awayGoalsFt']);
        $this->assertSame(1, $matches[0]['homeGoalsHt']);
        $this->assertSame(0, $matches[0]['awayGoalsHt']);

        $this->assertSame(1002, $matches[1]['id']);
        $this->assertNull($matches[1]['homeGoalsFt']);
        $this->assertNull($matches[1]['awayGoalsFt']);
        $this->assertNull($matches[1]['homeGoalsHt']);
        $this->assertNull($matches[1]['awayGoalsHt']);
    }

    public function test_fetching_non_league_matches__for_a_team__should_filter_out_league_competition_matches(): void
    {
        $this->httpClient->expects($this->once())
            ->method('request')
            ->with('GET', 'https://api.football-data.org/v4/teams/86/matches', $this->anything())
            ->willReturn($this->makeResponse([
                'matches' => [
                    [
                        'id'         => 9001,
                        'utcDate'    => '2025-10-15T20:00:00Z',
                        'status'     => 'SCHEDULED',
                        'competition' => ['code' => 'CDR', 'name' => 'Copa del Rey'],
                    ],
                    [
                        'id'         => 9002,
                        'utcDate'    => '2025-10-22T20:00:00Z',
                        'status'     => 'SCHEDULED',
                        'competition' => ['code' => 'PD', 'name' => 'Primera División'],
                    ],
                    [
                        'id'         => 9003,
                        'utcDate'    => '2025-10-29T20:00:00Z',
                        'status'     => 'SCHEDULED',
                        'competition' => ['code' => 'UCL', 'name' => 'UEFA Champions League'],
                    ],
                ],
            ]));

        $matches = $this->client->fetchNonLeagueMatches(86, 'PD');

        $this->assertCount(2, $matches);
        $this->assertSame(9001, $matches[0]['id']);
        $this->assertSame(9003, $matches[1]['id']);
    }

    public function test_fetching_non_league_matches__should_map_competition_name_and_date(): void
    {
        $this->httpClient->expects($this->once())
            ->method('request')
            ->willReturn($this->makeResponse([
                'matches' => [
                    [
                        'id'          => 9001,
                        'utcDate'     => '2025-10-15T20:00:00Z',
                        'status'      => 'FINISHED',
                        'competition' => ['code' => 'CDR', 'name' => 'Copa del Rey'],
                    ],
                ],
            ]));

        $matches = $this->client->fetchNonLeagueMatches(86, 'PD');

        $this->assertCount(1, $matches);
        $this->assertSame(9001, $matches[0]['id']);
        $this->assertSame('2025-10-15T20:00:00Z', $matches[0]['playedAt']);
        $this->assertSame('FINISHED', $matches[0]['status']);
        $this->assertSame('Copa del Rey', $matches[0]['competitionName']);
    }

    public function test_fetching_league_match_result__should_return_full_time_and_half_time_goals(): void
    {
        $this->httpClient->expects($this->once())
            ->method('request')
            ->with('GET', 'https://api.football-data.org/v4/matches/1001', $this->anything())
            ->willReturn($this->makeResponse([
                'score' => [
                    'fullTime' => ['home' => 3, 'away' => 1],
                    'halfTime' => ['home' => 2, 'away' => 0],
                ],
            ]));

        $result = $this->client->fetchLeagueMatchResult(1001);

        $this->assertSame(3, $result['homeGoalsFt']);
        $this->assertSame(1, $result['awayGoalsFt']);
        $this->assertSame(2, $result['homeGoalsHt']);
        $this->assertSame(0, $result['awayGoalsHt']);
    }

    private function makeResponse(array $data): ResponseInterface
    {
        $response = $this->createStub(ResponseInterface::class);
        $response->method('toArray')->willReturn($data);

        return $response;
    }
}
