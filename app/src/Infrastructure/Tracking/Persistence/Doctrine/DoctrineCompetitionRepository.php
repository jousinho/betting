<?php

declare(strict_types=1);

namespace App\Infrastructure\Tracking\Persistence\Doctrine;

use App\Domain\Tracking\Entity\Competition;
use App\Domain\Tracking\Repository\CompetitionRepositoryInterface;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;

class DoctrineCompetitionRepository implements CompetitionRepositoryInterface
{
    private EntityRepository $repository;

    public function __construct(private readonly EntityManagerInterface $entityManager)
    {
        $this->repository = $entityManager->getRepository(Competition::class);
    }

    public function save(Competition $competition): void
    {
        $this->entityManager->persist($competition);
        $this->entityManager->flush();
    }

    public function findByCode(string $code): ?Competition
    {
        return $this->repository->findOneBy(['code' => $code]);
    }

    public function findAll(): array
    {
        return $this->repository->findAll();
    }
}
