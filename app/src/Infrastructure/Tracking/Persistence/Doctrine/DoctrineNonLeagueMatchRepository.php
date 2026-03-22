<?php

declare(strict_types=1);

namespace App\Infrastructure\Tracking\Persistence\Doctrine;

use App\Domain\Tracking\Entity\NonLeagueMatch;
use App\Domain\Tracking\Entity\Team;
use App\Domain\Tracking\Repository\NonLeagueMatchRepositoryInterface;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;

class DoctrineNonLeagueMatchRepository implements NonLeagueMatchRepositoryInterface
{
    private EntityRepository $repository;

    public function __construct(private readonly EntityManagerInterface $entityManager)
    {
        $this->repository = $entityManager->getRepository(NonLeagueMatch::class);
    }

    public function save(NonLeagueMatch $match): void
    {
        $this->entityManager->persist($match);
        $this->entityManager->flush();
    }

    public function findByExternalIdAndTeam(int $externalId, Team $team): ?NonLeagueMatch
    {
        return $this->repository->findOneBy(['externalId' => $externalId, 'team' => $team]);
    }

    public function findPending(): array
    {
        return $this->entityManager->createQueryBuilder()
            ->select('m')
            ->from(NonLeagueMatch::class, 'm')
            ->where('m.status = :status')
            ->andWhere('m.playedAt < :now')
            ->setParameter('status', NonLeagueMatch::STATUS_SCHEDULED)
            ->setParameter('now', new \DateTimeImmutable())
            ->getQuery()
            ->getResult();
    }
}
