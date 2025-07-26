<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20250726181435 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE characters ADD location_id INT NOT NULL');
        $this->addSql('ALTER TABLE characters DROP location');
        $this->addSql('ALTER TABLE characters ADD CONSTRAINT FK_3A29410E64D218E FOREIGN KEY (location_id) REFERENCES locations (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('CREATE INDEX IDX_3A29410E64D218E ON characters (location_id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE characters DROP CONSTRAINT FK_3A29410E64D218E');
        $this->addSql('DROP INDEX IDX_3A29410E64D218E');
        $this->addSql('ALTER TABLE characters ADD location VARCHAR(255) NOT NULL');
        $this->addSql('ALTER TABLE characters DROP location_id');
    }
}
