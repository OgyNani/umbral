<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20250729024503 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add initial mobs to the game';
    }

    public function up(Schema $schema): void
    {
        $mobs = [
            [
                'name' => 'Forest Rat',
                'description' => 'A dangerous level 1 creature.',
                'stats' => '{"hp": 30, "attack": 8, "defence": 5, "strength": 6, "dexterity": 4, "speed": 2, "vitality": 5}',
                'exp_reward' => 10,
                'gold_reward' => 5,
                'level' => 1,
                'abilities' => '{}',
                'loot' => '{}',
                'location_id' => 32
            ],
            [
                'name' => 'Wild Boar',
                'description' => 'A dangerous level 1 creature.',
                'stats' => '{"hp": 31, "attack": 9, "defence": 4, "strength": 7, "dexterity": 5, "speed": 2, "vitality": 4}',
                'exp_reward' => 14,
                'gold_reward' => 8,
                'level' => 1,
                'abilities' => '{}',
                'loot' => '{}',
                'location_id' => 32
            ],
            [
                'name' => 'Moss Crawler',
                'description' => 'A dangerous level 1 creature.',
                'stats' => '{"hp": 25, "attack": 8, "defence": 6, "strength": 4, "dexterity": 5, "speed": 3, "vitality": 3}',
                'exp_reward' => 14,
                'gold_reward' => 5,
                'level' => 1,
                'abilities' => '{}',
                'loot' => '{}',
                'location_id' => 32
            ],
        ];

        foreach ($mobs as $mob) {
            $this->addSql(
                "INSERT INTO mobs (
                    name, 
                    description, 
                    stats, 
                    exp_reward, 
                    gold_reward, 
                    level, 
                    abilities, 
                    loot, 
                    location_id
                ) VALUES (
                    :name,
                    :description,
                    :stats::jsonb,
                    :exp_reward,
                    :gold_reward,
                    :level,
                    :abilities::jsonb,
                    :loot::jsonb,
                    :location_id
                )",
                $mob
            );
        }
    }

    public function down(Schema $schema): void
    {
        $this->addSql('TRUNCATE TABLE mobs');
    }
}