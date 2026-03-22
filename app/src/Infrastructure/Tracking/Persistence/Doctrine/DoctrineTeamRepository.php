<?php

declare(strict_types=1);

namespace App\Infrastructure\Tracking\Persistence\Doctrine;

use App\Domain\Tracking\Entity\Team;
use App\Domain\Tracking\Repository\TeamRepositoryInterface;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;

class DoctrineTeamRepository implements TeamRepositoryInterface
{
    private EntityRepository $repository;

    public function __construct(private readonly EntityManagerInterface $entityManager)
    {
        $this->repository = $entityManager->getRepository(Team::class);
    }

    public function save(Team $team): void
    {
        $this->entityManager->persist($team);
        $this->entityManager->flush();
    }

    public function findByExternalId(int $externalId): ?Team
    {
        return $this->repository->findOneBy(['externalId' => $externalId]);
    }
}
