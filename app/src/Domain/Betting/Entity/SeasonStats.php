<?php

declare(strict_types=1);

namespace App\Domain\Betting\Entity;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity]
#[ORM\Table(name: 'season_stats')]
class SeasonStats
{
    #[ORM\Id]
    #[ORM\Column(type: 'uuid')]
    private Uuid $id;

    #[ORM\Column(type: 'string')]
    private string $season;

    #[ORM\Column(type: 'string')]
    private string $competitionCode;

    #[ORM\Column(type: 'string')]
    private string $competitionName;

    #[ORM\Column(type: 'json')]
    private array $stats;

    private function __construct(
        Uuid $id,
        string $season,
        string $competitionCode,
        string $competitionName,
        array $stats,
    ) {
        $this->id              = $id;
        $this->season          = $season;
        $this->competitionCode = $competitionCode;
        $this->competitionName = $competitionName;
        $this->stats           = $stats;
    }

    public static function create(
        string $season,
        string $competitionCode,
        string $competitionName,
        array $stats,
    ): self {
        return new self(Uuid::v4(), $season, $competitionCode, $competitionName, $stats);
    }

    public function id(): Uuid
    {
        return $this->id;
    }

    public function season(): string
    {
        return $this->season;
    }

    public function competitionCode(): string
    {
        return $this->competitionCode;
    }

    public function competitionName(): string
    {
        return $this->competitionName;
    }

    public function stats(): array
    {
        return $this->stats;
    }
}
