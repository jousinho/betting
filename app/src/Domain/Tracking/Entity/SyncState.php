<?php

declare(strict_types=1);

namespace App\Domain\Tracking\Entity;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity]
#[ORM\Table(name: 'sync_states')]
class SyncState
{
    #[ORM\Id]
    #[ORM\Column(type: 'uuid')]
    private Uuid $id;

    #[ORM\OneToOne(targetEntity: Competition::class)]
    #[ORM\JoinColumn(nullable: false)]
    private Competition $competition;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $lastSyncedAt = null;

    private function __construct(Uuid $id, Competition $competition)
    {
        $this->id = $id;
        $this->competition = $competition;
    }

    public static function create(Competition $competition): self
    {
        return new self(Uuid::v4(), $competition);
    }

    public function markSynced(\DateTimeImmutable $at): void
    {
        $this->lastSyncedAt = $at;
    }

    public function isSyncedToday(): bool
    {
        if ($this->lastSyncedAt === null) {
            return false;
        }

        return $this->lastSyncedAt->format('Y-m-d') === (new \DateTimeImmutable())->format('Y-m-d');
    }

    public function id(): Uuid
    {
        return $this->id;
    }

    public function competition(): Competition
    {
        return $this->competition;
    }

    public function lastSyncedAt(): ?\DateTimeImmutable
    {
        return $this->lastSyncedAt;
    }
}
