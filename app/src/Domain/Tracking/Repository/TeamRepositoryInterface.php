<?php

declare(strict_types=1);

namespace App\Domain\Tracking\Repository;

use App\Domain\Tracking\Entity\Team;

interface TeamRepositoryInterface
{
    public function save(Team $team): void;

    public function findByExternalId(int $externalId): ?Team;
}
