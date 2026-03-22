<?php

declare(strict_types=1);

namespace App\Infrastructure\Shared\Persistence\Doctrine\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260322200000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create season_stats table';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE season_stats (id UUID NOT NULL, season VARCHAR(255) NOT NULL, competition_code VARCHAR(255) NOT NULL, competition_name VARCHAR(255) NOT NULL, stats JSON NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_season_competition ON season_stats (competition_code, season)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE season_stats');
    }
}
