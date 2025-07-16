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
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql("INSERT INTO classes (name, base_stats) VALUES
            ('Mage', '{\"hp\":30, \"attack\":0, \"defence\":0, \"strength\":0, \"dexterity\":0, \"speed\":0, \"vitality\":0}'),
            ('Knight', '{\"hp\":30, \"attack\":0, \"defence\":0, \"strength\":0, \"dexterity\":0, \"speed\":0, \"vitality\":0}'),
            ('Archer', '{\"hp\":30, \"attack\":0, \"defence\":0, \"strength\":0, \"dexterity\":0, \"speed\":0, \"vitality\":0}'),
            ('Priest', '{\"hp\":30, \"attack\":0, \"defence\":0, \"strength\":0, \"dexterity\":0, \"speed\":0, \"vitality\":0}'),
            ('Rogue', '{\"hp\":30, \"attack\":0, \"defence\":0, \"strength\":0, \"dexterity\":0, \"speed\":0, \"vitality\":0}')");
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs

    }
}
