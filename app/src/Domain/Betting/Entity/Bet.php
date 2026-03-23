<?php

declare(strict_types=1);

namespace App\Domain\Betting\Entity;

use App\Domain\Tracking\Entity\LeagueMatch;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity]
#[ORM\Table(name: 'bets')]
class Bet
{
    public const STATUS_PENDING = 'PENDING';
    public const STATUS_WON     = 'WON';
    public const STATUS_LOST    = 'LOST';

    #[ORM\Id]
    #[ORM\Column(type: 'uuid')]
    private Uuid $id;

    #[ORM\ManyToOne(targetEntity: LeagueMatch::class)]
    #[ORM\JoinColumn(nullable: false)]
    private LeagueMatch $leagueMatch;

    #[ORM\Column(type: 'string')]
    private string $betType;

    #[ORM\Column(type: 'string')]
    private string $perspective;

    #[ORM\Column(type: 'boolean')]
    private bool $skipped;

    #[ORM\Column(type: 'string')]
    private string $status;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $settledAt = null;

    #[ORM\Column(type: 'float', nullable: true)]
    private ?float $odds = null;

    private function __construct(
        Uuid $id,
        LeagueMatch $leagueMatch,
        string $betType,
        string $perspective,
        bool $skipped,
    ) {
        $this->id          = $id;
        $this->leagueMatch = $leagueMatch;
        $this->betType     = $betType;
        $this->perspective = $perspective;
        $this->skipped     = $skipped;
        $this->status      = self::STATUS_PENDING;
    }

    public static function create(
        LeagueMatch $leagueMatch,
        string $betType,
        string $perspective,
        bool $skipped = false,
    ): self {
        return new self(Uuid::v4(), $leagueMatch, $betType, $perspective, $skipped);
    }

    public function settle(\DateTimeImmutable $at, bool $won): void
    {
        $this->status     = $won ? self::STATUS_WON : self::STATUS_LOST;
        $this->settledAt  = $at;
    }

    public function id(): Uuid
    {
        return $this->id;
    }

    public function leagueMatch(): LeagueMatch
    {
        return $this->leagueMatch;
    }

    public function betType(): string
    {
        return $this->betType;
    }

    public function perspective(): string
    {
        return $this->perspective;
    }

    public function skipped(): bool
    {
        return $this->skipped;
    }

    public function status(): string
    {
        return $this->status;
    }

    public function settledAt(): ?\DateTimeImmutable
    {
        return $this->settledAt;
    }

    public function odds(): ?float
    {
        return $this->odds;
    }

    public function setOdds(?float $odds): void
    {
        $this->odds = $odds;
    }
}
