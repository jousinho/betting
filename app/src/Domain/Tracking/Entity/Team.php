<?php

declare(strict_types=1);

namespace App\Domain\Tracking\Entity;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity]
#[ORM\Table(name: 'teams')]
class Team
{
    #[ORM\Id]
    #[ORM\Column(type: 'uuid')]
    private Uuid $id;

    #[ORM\Column(type: 'integer', unique: true)]
    private int $externalId;

    #[ORM\Column(type: 'string')]
    private string $name;

    #[ORM\ManyToOne(targetEntity: Competition::class)]
    #[ORM\JoinColumn(nullable: false)]
    private Competition $competition;

    private function __construct(Uuid $id, int $externalId, string $name, Competition $competition)
    {
        $this->id = $id;
        $this->externalId = $externalId;
        $this->name = $name;
        $this->competition = $competition;
    }

    public static function create(int $externalId, string $name, Competition $competition): self
    {
        return new self(Uuid::v4(), $externalId, $name, $competition);
    }

    public function id(): Uuid
    {
        return $this->id;
    }

    public function externalId(): int
    {
        return $this->externalId;
    }

    public function name(): string
    {
        return $this->name;
    }

    public function competition(): Competition
    {
        return $this->competition;
    }

    public function setName(string $name): void
    {
        $this->name = $name;
    }
}
