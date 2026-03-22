<?php

declare(strict_types=1);

namespace App\Domain\Tracking\Repository;

use App\Domain\Tracking\Entity\Competition;
use App\Domain\Tracking\Entity\SyncState;

interface SyncStateRepositoryInterface
{
    public function save(SyncState $syncState): void;

    public function findByCompetition(Competition $competition): ?SyncState;
}
