<?php

declare(strict_types=1);

namespace App\Infrastructure\Tracking\Persistence\Doctrine;

use App\Domain\Tracking\Entity\Competition;
use App\Domain\Tracking\Entity\SyncState;
use App\Domain\Tracking\Repository\SyncStateRepositoryInterface;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;

class DoctrineSyncStateRepository implements SyncStateRepositoryInterface
{
    private EntityRepository $repository;

    public function __construct(private readonly EntityManagerInterface $entityManager)
    {
        $this->repository = $entityManager->getRepository(SyncState::class);
    }

    public function save(SyncState $syncState): void
    {
        $this->entityManager->persist($syncState);
        $this->entityManager->flush();
    }

    public function findByCompetition(Competition $competition): ?SyncState
    {
        return $this->repository->findOneBy(['competition' => $competition]);
    }
}
