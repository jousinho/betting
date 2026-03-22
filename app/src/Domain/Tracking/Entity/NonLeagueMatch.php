<?php

declare(strict_types=1);

namespace App\Domain\Tracking\Entity;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity]
#[ORM\Table(name: 'non_league_matches')]
class NonLeagueMatch
{
    public const STATUS_SCHEDULED = 'SCHEDULED';
    public const STATUS_FINISHED = 'FINISHED';

    #[ORM\Id]
    #[ORM\Column(type: 'uuid')]
    private Uuid $id;

    #[ORM\Column(type: 'integer')]
    private int $externalId;

    #[ORM\ManyToOne(targetEntity: Team::class)]
    #[ORM\JoinColumn(nullable: false)]
    private Team $team;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $playedAt;

    #[ORM\Column(type: 'string')]
    private string $status;

    #[ORM\Column(type: 'string')]
    private string $competitionName;

    private function __construct(
        Uuid $id,
        int $externalId,
        Team $team,
        \DateTimeImmutable $playedAt,
        string $competitionName,
    ) {
        $this->id = $id;
        $this->externalId = $externalId;
        $this->team = $team;
        $this->playedAt = $playedAt;
        $this->competitionName = $competitionName;
        $this->status = self::STATUS_SCHEDULED;
    }

    public static function create(
        int $externalId,
        Team $team,
        \DateTimeImmutable $playedAt,
        string $competitionName,
    ): self {
        return new self(Uuid::v4(), $externalId, $team, $playedAt, $competitionName);
    }

    public function finish(): void
    {
        $this->status = self::STATUS_FINISHED;
    }

    public function id(): Uuid
    {
        return $this->id;
    }

    public function externalId(): int
    {
        return $this->externalId;
    }

    public function team(): Team
    {
        return $this->team;
    }

    public function playedAt(): \DateTimeImmutable
    {
        return $this->playedAt;
    }

    public function status(): string
    {
        return $this->status;
    }

    public function competitionName(): string
    {
        return $this->competitionName;
    }
}
