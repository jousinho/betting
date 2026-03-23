<?php

declare(strict_types=1);

namespace App\Infrastructure\Betting\Persistence\Doctrine;

use App\Domain\Betting\Entity\Bet;
use App\Domain\Betting\Repository\BetRepositoryInterface;
use App\Domain\Tracking\Entity\Competition;
use App\Domain\Tracking\Entity\LeagueMatch;
use Doctrine\ORM\EntityManagerInterface;

class DoctrineBetRepository implements BetRepositoryInterface
{
    public function __construct(private readonly EntityManagerInterface $entityManager) {}

    public function save(Bet $bet): void
    {
        $this->entityManager->persist($bet);
        $this->entityManager->flush();
    }

    public function findByMatch(LeagueMatch $match): array
    {
        return $this->entityManager->createQueryBuilder()
            ->select('b')
            ->from(Bet::class, 'b')
            ->where('b.leagueMatch = :match')
            ->setParameter('match', $match)
            ->getQuery()
            ->getResult();
    }

    public function findPendingByCompetition(Competition $competition): array
    {
        return $this->entityManager->createQueryBuilder()
            ->select('b')
            ->from(Bet::class, 'b')
            ->join('b.leagueMatch', 'm')
            ->where('m.competition = :competition')
            ->andWhere('b.status = :status')
            ->setParameter('competition', $competition)
            ->setParameter('status', Bet::STATUS_PENDING)
            ->getQuery()
            ->getResult();
    }

    public function findSettledByCompetition(Competition $competition): array
    {
        return $this->entityManager->createQueryBuilder()
            ->select('b')
            ->from(Bet::class, 'b')
            ->join('b.leagueMatch', 'm')
            ->where('m.competition = :competition')
            ->andWhere('b.status IN (:statuses)')
            ->setParameter('competition', $competition)
            ->setParameter('statuses', [Bet::STATUS_WON, Bet::STATUS_LOST])
            ->getQuery()
            ->getResult();
    }

    public function clearByCompetition(Competition $competition): void
    {
        $this->entityManager->createQuery(
            'DELETE FROM App\Domain\Betting\Entity\Bet b
             WHERE b.leagueMatch IN (
                 SELECT m FROM App\Domain\Tracking\Entity\LeagueMatch m
                 WHERE m.competition = :competition
             )'
        )->setParameter('competition', $competition)->execute();
    }

    public function findPendingWithoutOddsWithinDays(Competition $competition, int $days): array
    {
        $cutoff = new \DateTimeImmutable(sprintf('+%d days', $days));

        return $this->entityManager->createQueryBuilder()
            ->select('b')
            ->from(Bet::class, 'b')
            ->join('b.leagueMatch', 'm')
            ->where('m.competition = :competition')
            ->andWhere('b.status = :status')
            ->andWhere('b.odds IS NULL')
            ->andWhere('b.skipped = false')
            ->andWhere('m.playedAt <= :cutoff')
            ->setParameter('competition', $competition)
            ->setParameter('status', Bet::STATUS_PENDING)
            ->setParameter('cutoff', $cutoff)
            ->getQuery()
            ->getResult();
    }

    public function existsForMatchAndType(LeagueMatch $match, string $betType): bool
    {
        $count = (int) $this->entityManager->createQueryBuilder()
            ->select('COUNT(b.id)')
            ->from(Bet::class, 'b')
            ->where('b.leagueMatch = :match')
            ->andWhere('b.betType = :betType')
            ->setParameter('match', $match)
            ->setParameter('betType', $betType)
            ->getQuery()
            ->getSingleScalarResult();

        return $count > 0;
    }
}
