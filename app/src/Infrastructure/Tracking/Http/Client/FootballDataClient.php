<?php

declare(strict_types=1);

namespace App\Infrastructure\Tracking\Http\Client;

use App\Domain\Tracking\Repository\FootballDataProviderInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class FootballDataClient implements FootballDataProviderInterface
{
    private const BASE_URL = 'https://api.football-data.org/v4';

    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly string $apiKey,
    ) {}

    public function fetchCompetition(string $competitionCode): array
    {
        $data = $this->get(sprintf('/competitions/%s', $competitionCode));

        return [
            'code' => $data['code'],
            'name' => $data['name'],
        ];
    }

    public function fetchTeams(string $competitionCode): array
    {
        $data = $this->get(sprintf('/competitions/%s/teams', $competitionCode));

        return array_map(fn(array $team) => [
            'id'   => $team['id'],
            'name' => $team['name'],
        ], $data['teams'] ?? []);
    }

    public function fetchLeagueMatches(string $competitionCode): array
    {
        $data = $this->get(sprintf('/competitions/%s/matches', $competitionCode));

        return array_map(fn(array $match) => [
            'id'          => $match['id'],
            'matchday'    => $match['matchday'],
            'playedAt'    => $match['utcDate'],
            'status'      => $match['status'],
            'homeTeamId'  => $match['homeTeam']['id'],
            'awayTeamId'  => $match['awayTeam']['id'],
            'homeGoalsFt' => $match['score']['fullTime']['home'] ?? null,
            'awayGoalsFt' => $match['score']['fullTime']['away'] ?? null,
            'homeGoalsHt' => $match['score']['halfTime']['home'] ?? null,
            'awayGoalsHt' => $match['score']['halfTime']['away'] ?? null,
        ], $data['matches'] ?? []);
    }

    public function fetchLeagueMatchesBySeason(string $competitionCode, int $season): array
    {
        $data = $this->get(sprintf('/competitions/%s/matches?season=%d', $competitionCode, $season));

        return array_map(fn(array $match) => [
            'id'           => $match['id'],
            'matchday'     => $match['matchday'],
            'playedAt'     => $match['utcDate'],
            'status'       => $match['status'],
            'homeTeamId'   => $match['homeTeam']['id'],
            'homeTeamName' => $match['homeTeam']['name'],
            'awayTeamId'   => $match['awayTeam']['id'],
            'awayTeamName' => $match['awayTeam']['name'],
            'homeGoalsFt'  => $match['score']['fullTime']['home'] ?? null,
            'awayGoalsFt'  => $match['score']['fullTime']['away'] ?? null,
            'homeGoalsHt'  => $match['score']['halfTime']['home'] ?? null,
            'awayGoalsHt'  => $match['score']['halfTime']['away'] ?? null,
        ], $data['matches'] ?? []);
    }

    public function fetchNonLeagueMatches(int $teamExternalId, string $leagueCompetitionCode): array
    {
        $data = $this->get(sprintf('/teams/%d/matches', $teamExternalId));

        $matches = array_filter(
            $data['matches'] ?? [],
            fn(array $m) => ($m['competition']['code'] ?? '') !== $leagueCompetitionCode,
        );

        return array_values(array_map(fn(array $match) => [
            'id'              => $match['id'],
            'playedAt'        => $match['utcDate'],
            'status'          => $match['status'],
            'competitionName' => $match['competition']['name'],
        ], $matches));
    }

    public function fetchLeagueMatchResult(int $matchExternalId): array
    {
        $data = $this->get(sprintf('/matches/%d', $matchExternalId));

        return [
            'homeGoalsFt' => $data['score']['fullTime']['home'],
            'awayGoalsFt' => $data['score']['fullTime']['away'],
            'homeGoalsHt' => $data['score']['halfTime']['home'],
            'awayGoalsHt' => $data['score']['halfTime']['away'],
        ];
    }

    private function get(string $path): array
    {
        $response = $this->httpClient->request('GET', self::BASE_URL . $path, [
            'headers' => ['X-Auth-Token' => $this->apiKey],
        ]);

        return $response->toArray();
    }
}
