<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Clean fix for max_base_stats column in classes table
 */
final class Version20250727232500 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Clean fix for max_base_stats column in classes table';
    }

    public function up(Schema $schema): void
    {
        // Execute raw SQL to drop the column if it exists
        $this->addSql('ALTER TABLE classes DROP COLUMN IF EXISTS max_base_stats');
        
        // Add the column with default empty JSON object
        $this->addSql('ALTER TABLE classes ADD max_base_stats JSON DEFAULT \'{}\'::json NOT NULL');
        
        // Copy base_stats to max_base_stats for all existing classes
        $this->addSql('UPDATE classes SET max_base_stats = base_stats');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE classes DROP COLUMN max_base_stats');
    }
}
