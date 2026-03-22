<?php

declare(strict_types=1);

namespace App\Infrastructure\Tracking\Persistence\Doctrine;

use App\Domain\Tracking\Entity\Competition;
use App\Domain\Tracking\Entity\LeagueMatch;
use App\Domain\Tracking\Repository\LeagueMatchRepositoryInterface;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;

class DoctrineLeagueMatchRepository implements LeagueMatchRepositoryInterface
{
    private EntityRepository $repository;

    public function __construct(private readonly EntityManagerInterface $entityManager)
    {
        $this->repository = $entityManager->getRepository(LeagueMatch::class);
    }

    public function save(LeagueMatch $match): void
    {
        $this->entityManager->persist($match);
        $this->entityManager->flush();
    }

    public function findByExternalId(int $externalId): ?LeagueMatch
    {
        return $this->repository->findOneBy(['externalId' => $externalId]);
    }

    public function findPendingByCompetition(Competition $competition): array
    {
        return $this->entityManager->createQueryBuilder()
            ->select('m')
            ->from(LeagueMatch::class, 'm')
            ->where('m.competition = :competition')
            ->andWhere('m.status = :status')
            ->andWhere('m.playedAt < :now')
            ->setParameter('competition', $competition)
            ->setParameter('status', LeagueMatch::STATUS_SCHEDULED)
            ->setParameter('now', new \DateTimeImmutable())
            ->getQuery()
            ->getResult();
    }
}
