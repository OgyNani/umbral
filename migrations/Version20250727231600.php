<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Add max_base_stats column to classes table
 */
final class Version20250727231600 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add max_base_stats column to classes table';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE classes ADD max_base_stats JSON NOT NULL DEFAULT \'{}\'');
        
        // Update existing classes with default max_base_stats values
        $this->addSql("UPDATE classes SET max_base_stats = '{\"hp\":100, \"attack\":20, \"defence\":20, \"strength\":20, \"dexterity\":20, \"speed\":20, \"vitality\":20}' WHERE name = 'Маг'");
        $this->addSql("UPDATE classes SET max_base_stats = '{\"hp\":150, \"attack\":25, \"defence\":30, \"strength\":30, \"dexterity\":15, \"speed\":15, \"vitality\":25}' WHERE name = 'Рыцарь'");
        $this->addSql("UPDATE classes SET max_base_stats = '{\"hp\":120, \"attack\":30, \"defence\":20, \"strength\":20, \"dexterity\":30, \"speed\":25, \"vitality\":15}' WHERE name = 'Лучник'");
        $this->addSql("UPDATE classes SET max_base_stats = '{\"hp\":130, \"attack\":15, \"defence\":25, \"strength\":15, \"dexterity\":15, \"speed\":15, \"vitality\":30}' WHERE name = 'Жрец'");
        $this->addSql("UPDATE classes SET max_base_stats = '{\"hp\":110, \"attack\":25, \"defence\":15, \"strength\":25, \"dexterity\":30, \"speed\":30, \"vitality\":10}' WHERE name = 'Разбойник'");
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE classes DROP COLUMN max_base_stats');
    }
}
