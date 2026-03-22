<?php

declare(strict_types=1);

namespace App\Infrastructure\Betting\Persistence\Doctrine;

use App\Domain\Betting\Entity\SeasonStats;
use App\Domain\Betting\Repository\SeasonStatsRepositoryInterface;
use Doctrine\ORM\EntityManagerInterface;

class DoctrineSeasonStatsRepository implements SeasonStatsRepositoryInterface
{
    public function __construct(private readonly EntityManagerInterface $em) {}

    public function save(SeasonStats $seasonStats): void
    {
        $this->em->persist($seasonStats);
        $this->em->flush();
    }

    public function findByCompetitionCode(string $competitionCode): array
    {
        return $this->em->getRepository(SeasonStats::class)->findBy(
            ['competitionCode' => $competitionCode],
            ['season' => 'DESC'],
        );
    }

    public function findByCompetitionCodeAndSeason(string $competitionCode, string $season): ?SeasonStats
    {
        return $this->em->getRepository(SeasonStats::class)->findOneBy([
            'competitionCode' => $competitionCode,
            'season'          => $season,
        ]);
    }
}
