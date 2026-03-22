<?php

declare(strict_types=1);

namespace App\Domain\Tracking\Entity;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity]
#[ORM\Table(name: 'league_matches')]
class LeagueMatch
{
    public const STATUS_SCHEDULED = 'SCHEDULED';
    public const STATUS_FINISHED = 'FINISHED';

    #[ORM\Id]
    #[ORM\Column(type: 'uuid')]
    private Uuid $id;

    #[ORM\Column(type: 'integer', unique: true)]
    private int $externalId;

    #[ORM\ManyToOne(targetEntity: Competition::class)]
    #[ORM\JoinColumn(nullable: false)]
    private Competition $competition;

    #[ORM\ManyToOne(targetEntity: Team::class)]
    #[ORM\JoinColumn(nullable: false)]
    private Team $homeTeam;

    #[ORM\ManyToOne(targetEntity: Team::class)]
    #[ORM\JoinColumn(nullable: false)]
    private Team $awayTeam;

    #[ORM\Column(type: 'integer')]
    private int $matchday;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $playedAt;

    #[ORM\Column(type: 'string')]
    private string $status;

    #[ORM\Column(type: 'integer', nullable: true)]
    private ?int $homeGoalsFt = null;

    #[ORM\Column(type: 'integer', nullable: true)]
    private ?int $awayGoalsFt = null;

    #[ORM\Column(type: 'integer', nullable: true)]
    private ?int $homeGoalsHt = null;

    #[ORM\Column(type: 'integer', nullable: true)]
    private ?int $awayGoalsHt = null;

    private function __construct(
        Uuid $id,
        int $externalId,
        Competition $competition,
        Team $homeTeam,
        Team $awayTeam,
        int $matchday,
        \DateTimeImmutable $playedAt,
    ) {
        $this->id = $id;
        $this->externalId = $externalId;
        $this->competition = $competition;
        $this->homeTeam = $homeTeam;
        $this->awayTeam = $awayTeam;
        $this->matchday = $matchday;
        $this->playedAt = $playedAt;
        $this->status = self::STATUS_SCHEDULED;
    }

    public static function create(
        int $externalId,
        Competition $competition,
        Team $homeTeam,
        Team $awayTeam,
        int $matchday,
        \DateTimeImmutable $playedAt,
    ): self {
        return new self(Uuid::v4(), $externalId, $competition, $homeTeam, $awayTeam, $matchday, $playedAt);
    }

    public function finish(int $homeGoalsFt, int $awayGoalsFt, int $homeGoalsHt, int $awayGoalsHt): void
    {
        $this->homeGoalsFt = $homeGoalsFt;
        $this->awayGoalsFt = $awayGoalsFt;
        $this->homeGoalsHt = $homeGoalsHt;
        $this->awayGoalsHt = $awayGoalsHt;
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

    public function competition(): Competition
    {
        return $this->competition;
    }

    public function homeTeam(): Team
    {
        return $this->homeTeam;
    }

    public function awayTeam(): Team
    {
        return $this->awayTeam;
    }

    public function matchday(): int
    {
        return $this->matchday;
    }

    public function playedAt(): \DateTimeImmutable
    {
        return $this->playedAt;
    }

    public function status(): string
    {
        return $this->status;
    }

    public function homeGoalsFt(): ?int
    {
        return $this->homeGoalsFt;
    }

    public function awayGoalsFt(): ?int
    {
        return $this->awayGoalsFt;
    }

    public function homeGoalsHt(): ?int
    {
        return $this->homeGoalsHt;
    }

    public function awayGoalsHt(): ?int
    {
        return $this->awayGoalsHt;
    }
}
