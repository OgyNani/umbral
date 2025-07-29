<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20250716191057 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
    // Добавляем классы с базовыми и максимальными статами
    $this->addSql("INSERT INTO classes (name, base_stats, max_base_stats) VALUES
        ('Mage', 
         '{\"hp\":50, \"attack\":5, \"defence\":0, \"strength\":0, \"dexterity\":0, \"speed\":0, \"vitality\":0}',
         '{\"hp\":100, \"attack\":20, \"defence\":20, \"strength\":20, \"dexterity\":20, \"speed\":20, \"vitality\":20}'),
        
        ('Knight', 
         '{\"hp\":60, \"attack\":3, \"defence\":0, \"strength\":0, \"dexterity\":0, \"speed\":0, \"vitality\":0}',
         '{\"hp\":150, \"attack\":25, \"defence\":30, \"strength\":30, \"dexterity\":15, \"speed\":15, \"vitality\":25}'),
        
        ('Archer', 
         '{\"hp\":50, \"attack\":5, \"defence\":0, \"strength\":0, \"dexterity\":0, \"speed\":0, \"vitality\":0}',
         '{\"hp\":120, \"attack\":30, \"defence\":20, \"strength\":20, \"dexterity\":30, \"speed\":25, \"vitality\":15}'),
        
        ('Priest', 
         '{\"hp\":40, \"attack\":4, \"defence\":0, \"strength\":0, \"dexterity\":0, \"speed\":0, \"vitality\":0}',
         '{\"hp\":130, \"attack\":15, \"defence\":25, \"strength\":15, \"dexterity\":15, \"speed\":15, \"vitality\":30}'),
        
        ('Rogue', 
         '{\"hp\":50, \"attack\":6, \"defence\":0, \"strength\":0, \"dexterity\":0, \"speed\":0, \"vitality\":0}',
         '{\"hp\":110, \"attack\":25, \"defence\":15, \"strength\":25, \"dexterity\":30, \"speed\":30, \"vitality\":10}')");
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE IF EXISTS classes');
    }
}
