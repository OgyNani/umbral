<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Fix max_base_stats column in classes table
 */
final class Version20250727232300 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Fix max_base_stats column in classes table';
    }

    public function up(Schema $schema): void
    {
        // First, make the column nullable so we can add it without errors
        $this->addSql('ALTER TABLE classes ALTER COLUMN max_base_stats DROP NOT NULL');
        
        // Update all records with the base_stats value
        $this->addSql('UPDATE classes SET max_base_stats = base_stats');
        
        // Now make it NOT NULL again
        $this->addSql('ALTER TABLE classes ALTER COLUMN max_base_stats SET NOT NULL');
    }

    public function down(Schema $schema): void
    {
        // No need to do anything in down() as we're just fixing data
    }
}
