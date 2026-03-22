<?php

declare(strict_types=1);

namespace App\Infrastructure\Shared\Persistence\Doctrine\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260322124153 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE competitions (id UUID NOT NULL, code VARCHAR(255) NOT NULL, name VARCHAR(255) NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_A7DD463D77153098 ON competitions (code)');
        $this->addSql('CREATE TABLE league_matches (id UUID NOT NULL, external_id INT NOT NULL, matchday INT NOT NULL, played_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, status VARCHAR(255) NOT NULL, home_goals_ft INT DEFAULT NULL, away_goals_ft INT DEFAULT NULL, home_goals_ht INT DEFAULT NULL, away_goals_ht INT DEFAULT NULL, competition_id UUID NOT NULL, home_team_id UUID NOT NULL, away_team_id UUID NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_BF8F0CCB9F75D7B0 ON league_matches (external_id)');
        $this->addSql('CREATE INDEX IDX_BF8F0CCB7B39D312 ON league_matches (competition_id)');
        $this->addSql('CREATE INDEX IDX_BF8F0CCB9C4C13F6 ON league_matches (home_team_id)');
        $this->addSql('CREATE INDEX IDX_BF8F0CCB45185D02 ON league_matches (away_team_id)');
        $this->addSql('CREATE TABLE non_league_matches (id UUID NOT NULL, external_id INT NOT NULL, played_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, status VARCHAR(255) NOT NULL, competition_name VARCHAR(255) NOT NULL, team_id UUID NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE INDEX IDX_817C58C0296CD8AE ON non_league_matches (team_id)');
        $this->addSql('CREATE TABLE sync_states (id UUID NOT NULL, last_synced_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, competition_id UUID NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_994399927B39D312 ON sync_states (competition_id)');
        $this->addSql('CREATE TABLE teams (id UUID NOT NULL, external_id INT NOT NULL, name VARCHAR(255) NOT NULL, competition_id UUID NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_96C222589F75D7B0 ON teams (external_id)');
        $this->addSql('CREATE INDEX IDX_96C222587B39D312 ON teams (competition_id)');
        $this->addSql('ALTER TABLE league_matches ADD CONSTRAINT FK_BF8F0CCB7B39D312 FOREIGN KEY (competition_id) REFERENCES competitions (id) NOT DEFERRABLE');
        $this->addSql('ALTER TABLE league_matches ADD CONSTRAINT FK_BF8F0CCB9C4C13F6 FOREIGN KEY (home_team_id) REFERENCES teams (id) NOT DEFERRABLE');
        $this->addSql('ALTER TABLE league_matches ADD CONSTRAINT FK_BF8F0CCB45185D02 FOREIGN KEY (away_team_id) REFERENCES teams (id) NOT DEFERRABLE');
        $this->addSql('ALTER TABLE non_league_matches ADD CONSTRAINT FK_817C58C0296CD8AE FOREIGN KEY (team_id) REFERENCES teams (id) NOT DEFERRABLE');
        $this->addSql('ALTER TABLE sync_states ADD CONSTRAINT FK_994399927B39D312 FOREIGN KEY (competition_id) REFERENCES competitions (id) NOT DEFERRABLE');
        $this->addSql('ALTER TABLE teams ADD CONSTRAINT FK_96C222587B39D312 FOREIGN KEY (competition_id) REFERENCES competitions (id) NOT DEFERRABLE');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE league_matches DROP CONSTRAINT FK_BF8F0CCB7B39D312');
        $this->addSql('ALTER TABLE league_matches DROP CONSTRAINT FK_BF8F0CCB9C4C13F6');
        $this->addSql('ALTER TABLE league_matches DROP CONSTRAINT FK_BF8F0CCB45185D02');
        $this->addSql('ALTER TABLE non_league_matches DROP CONSTRAINT FK_817C58C0296CD8AE');
        $this->addSql('ALTER TABLE sync_states DROP CONSTRAINT FK_994399927B39D312');
        $this->addSql('ALTER TABLE teams DROP CONSTRAINT FK_96C222587B39D312');
        $this->addSql('DROP TABLE competitions');
        $this->addSql('DROP TABLE league_matches');
        $this->addSql('DROP TABLE non_league_matches');
        $this->addSql('DROP TABLE sync_states');
        $this->addSql('DROP TABLE teams');
    }
}
