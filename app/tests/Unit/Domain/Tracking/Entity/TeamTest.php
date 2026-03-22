<?php

declare(strict_types=1);

namespace App\Tests\Unit\Domain\Tracking\Entity;

use App\Domain\Tracking\Entity\Competition;
use App\Domain\Tracking\Entity\Team;
use PHPUnit\Framework\TestCase;

class TeamTest extends TestCase
{
    public function test_creating_team__with_valid_data__should_store_external_id_and_name(): void
    {
        $competition = Competition::create('PD', 'Primera División');
        $team = Team::create(86, 'Real Madrid CF', $competition);

        $this->assertSame(86, $team->externalId());
        $this->assertSame('Real Madrid CF', $team->name());
        $this->assertSame($competition, $team->competition());
        $this->assertNotNull($team->id());
    }
}
