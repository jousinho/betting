<?php

declare(strict_types=1);

namespace App\Domain\Tracking\Repository;

interface FootballDataProviderInterface
{
    /** @return array{code: string, name: string} */
    public function fetchCompetition(string $competitionCode): array;

    /** @return array<int, array{id: int, name: string}> */
    public function fetchTeams(string $competitionCode): array;

    /** @return array<int, array{id: int, matchday: int, playedAt: string, status: string, homeTeamId: int, awayTeamId: int, homeGoalsFt: int|null, awayGoalsFt: int|null, homeGoalsHt: int|null, awayGoalsHt: int|null}> */
    public function fetchLeagueMatches(string $competitionCode): array;

    /** @return array<int, array{id: int, playedAt: string, status: string, competitionName: string}> */
    public function fetchNonLeagueMatches(int $teamExternalId, string $leagueCompetitionCode): array;

    /** @return array{homeGoalsFt: int, awayGoalsFt: int, homeGoalsHt: int, awayGoalsHt: int} */
    public function fetchLeagueMatchResult(int $matchExternalId): array;
}
