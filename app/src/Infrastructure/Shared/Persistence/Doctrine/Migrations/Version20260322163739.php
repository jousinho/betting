<?php

declare(strict_types=1);

namespace App\Infrastructure\Shared\Persistence\Doctrine\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260322163739 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE bets (id UUID NOT NULL, bet_type VARCHAR(255) NOT NULL, perspective VARCHAR(255) NOT NULL, skipped BOOLEAN NOT NULL, status VARCHAR(255) NOT NULL, settled_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, league_match_id UUID NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE INDEX IDX_7C28752BD054465 ON bets (league_match_id)');
        $this->addSql('ALTER TABLE bets ADD CONSTRAINT FK_7C28752BD054465 FOREIGN KEY (league_match_id) REFERENCES league_matches (id) NOT DEFERRABLE');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE bets DROP CONSTRAINT FK_7C28752BD054465');
        $this->addSql('DROP TABLE bets');
    }
}
