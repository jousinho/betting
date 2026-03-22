<?php

declare(strict_types=1);

namespace App\Application\Tracking\Service;

use App\Domain\Tracking\Entity\Competition;
use App\Domain\Tracking\Entity\LeagueMatch;
use App\Domain\Tracking\Entity\NonLeagueMatch;
use App\Domain\Tracking\Entity\Team;
use App\Domain\Tracking\Repository\CompetitionRepositoryInterface;
use App\Domain\Tracking\Repository\FootballDataProviderInterface;
use App\Domain\Tracking\Repository\LeagueMatchRepositoryInterface;
use App\Domain\Tracking\Repository\NonLeagueMatchRepositoryInterface;
use App\Domain\Tracking\Repository\TeamRepositoryInterface;

class SeasonSeedService
{
    public function __construct(
        private readonly FootballDataProviderInterface $provider,
        private readonly CompetitionRepositoryInterface $competitionRepository,
        private readonly TeamRepositoryInterface $teamRepository,
        private readonly LeagueMatchRepositoryInterface $leagueMatchRepository,
        private readonly NonLeagueMatchRepositoryInterface $nonLeagueMatchRepository,
    ) {}

    public function seed(string $competitionCode): void
    {
        $competition = $this->findOrCreateCompetition($competitionCode);
        $teams = $this->seedTeams($competition);
        $this->seedLeagueMatches($competition, $teams);
        $this->seedNonLeagueMatches($competition, $teams);
    }

    private function findOrCreateCompetition(string $competitionCode): Competition
    {
        $competition = $this->competitionRepository->findByCode($competitionCode);

        if ($competition === null) {
            $data = $this->provider->fetchCompetition($competitionCode);
            $competition = Competition::create($data['code'], $data['name']);
            $this->competitionRepository->save($competition);
        }

        return $competition;
    }

    /** @return Team[] */
    private function seedTeams(Competition $competition): array
    {
        $apiTeams = $this->provider->fetchTeams($competition->code());
        $teams = [];

        foreach ($apiTeams as $apiTeam) {
            $team = $this->teamRepository->findByExternalId($apiTeam['id']);

            if ($team === null) {
                $team = Team::create($apiTeam['id'], $apiTeam['name'], $competition);
            } else {
                $team->setName($apiTeam['name']);
            }

            $this->teamRepository->save($team);
            $teams[] = $team;
        }

        return $teams;
    }

    /** @param Team[] $teams */
    private function seedLeagueMatches(Competition $competition, array $teams): void
    {
        $teamsByExternalId = [];
        foreach ($teams as $team) {
            $teamsByExternalId[$team->externalId()] = $team;
        }

        $apiMatches = $this->provider->fetchLeagueMatches($competition->code());

        foreach ($apiMatches as $apiMatch) {
            if ($this->leagueMatchRepository->findByExternalId($apiMatch['id']) !== null) {
                continue;
            }

            $homeTeam = $teamsByExternalId[$apiMatch['homeTeamId']] ?? null;
            $awayTeam = $teamsByExternalId[$apiMatch['awayTeamId']] ?? null;

            if ($homeTeam === null || $awayTeam === null) {
                continue;
            }

            $match = LeagueMatch::create(
                externalId: $apiMatch['id'],
                competition: $competition,
                homeTeam: $homeTeam,
                awayTeam: $awayTeam,
                matchday: $apiMatch['matchday'],
                playedAt: new \DateTimeImmutable($apiMatch['playedAt']),
            );

            $this->leagueMatchRepository->save($match);
        }
    }

    /** @param Team[] $teams */
    private function seedNonLeagueMatches(Competition $competition, array $teams): void
    {
        foreach ($teams as $team) {
            $apiMatches = $this->provider->fetchNonLeagueMatches($team->externalId(), $competition->code());

            foreach ($apiMatches as $apiMatch) {
                if ($this->nonLeagueMatchRepository->findByExternalIdAndTeam($apiMatch['id'], $team) !== null) {
                    continue;
                }

                $match = NonLeagueMatch::create(
                    externalId: $apiMatch['id'],
                    team: $team,
                    playedAt: new \DateTimeImmutable($apiMatch['playedAt']),
                    competitionName: $apiMatch['competitionName'],
                );

                $this->nonLeagueMatchRepository->save($match);
            }
        }
    }
}
