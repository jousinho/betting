<?php

declare(strict_types=1);

namespace App\Tests\Unit\Domain\Tracking\Entity;

use App\Domain\Tracking\Entity\Competition;
use PHPUnit\Framework\TestCase;

class CompetitionTest extends TestCase
{
    public function test_creating_competition__with_valid_data__should_store_code_and_name(): void
    {
        $competition = Competition::create('PD', 'Primera División');

        $this->assertSame('PD', $competition->code());
        $this->assertSame('Primera División', $competition->name());
        $this->assertNotNull($competition->id());
    }
}
