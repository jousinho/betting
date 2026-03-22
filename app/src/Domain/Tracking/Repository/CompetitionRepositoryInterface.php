<?php

declare(strict_types=1);

namespace App\Domain\Tracking\Repository;

use App\Domain\Tracking\Entity\Competition;

interface CompetitionRepositoryInterface
{
    public function save(Competition $competition): void;

    public function findByCode(string $code): ?Competition;
}
