<?php

declare(strict_types=1);

namespace App\Infrastructure\Tracking\Http\Client;

use App\Domain\Tracking\Repository\FootballDataProviderInterface;

class FootballDataClient implements FootballDataProviderInterface
{
    public function __construct(private readonly string $apiKey)
    {
    }

    public function fetchTeams(string $competitionCode): array
    {
        throw new \LogicException('Not implemented yet.');
    }

    public function fetchLeagueMatches(string $competitionCode): array
    {
        throw new \LogicException('Not implemented yet.');
    }

    public function fetchNonLeagueMatches(int $teamExternalId): array
    {
        throw new \LogicException('Not implemented yet.');
    }

    public function fetchLeagueMatchResult(int $matchExternalId): array
    {
        throw new \LogicException('Not implemented yet.');
    }
}
