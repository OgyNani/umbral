<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Добавление колонок gold и emeralds в таблицу users и gold в таблицу characters
 */
final class Version20250727224500 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add gold and emeralds columns to users table and gold column to characters table';
    }

    public function up(Schema $schema): void
    {
        // Add gold and emeralds columns to users table
        $this->addSql('ALTER TABLE users ADD COLUMN gold BIGINT DEFAULT 0 NOT NULL');
        $this->addSql('ALTER TABLE users ADD COLUMN emeralds BIGINT DEFAULT 0 NOT NULL');
        
        // Add gold column to characters table
        $this->addSql('ALTER TABLE characters ADD COLUMN gold BIGINT DEFAULT 0 NOT NULL');
    }

    public function down(Schema $schema): void
    {
        // Remove gold and emeralds columns from users table
        $this->addSql('ALTER TABLE users DROP COLUMN gold');
        $this->addSql('ALTER TABLE users DROP COLUMN emeralds');
        
        // Remove gold column from characters table
        $this->addSql('ALTER TABLE characters DROP COLUMN gold');
    }
}
