<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20250729024501 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Populate locations table with gathering locations';
    }

    public function up(Schema $schema): void
    {
        $locations = [
            'alchemy' => [
                'Whisperwind Meadow',
                'Mystwood Glade',
                'Twilight Grove',
                'Ethereal Gardens',
                'Celestial Nexus',
            ],
            'hunting' => [
                'Greenwood Forest',
                'Shadowmist Valleys',
                'Broken Fang Highlands',
                'Thunder Ridge Summit',
                'Dragonspine Peaks',
            ],
            'mining' => [
                'Copper Ridge Mines',
                'Iron Depths Quarry',
                'Shimmerstone Caverns',
                'Obsidian Heart Forge',
                'Voidforge Abyss',
            ],
            'fishing' => [
                'Clearwater Lake',
                'Mistfall River',
                'Azure Deeps',
                'Abyssal Waters',
                'Leviathan\'s Trench',
            ],
            'farming' => [
                'Sunhaven Fields',
                'Goldenseed Valley',
                'Spiritbloom Acres',
                'Everbloom Gardens',
                'Chronomist Plantations',
            ],
            'city' => [
                'Virelith',
                'Caeloria',
                'Dusmire',
                'Noxhollow',
                'Solrendel',
            ],
            'world' => [
                'Dawnfields',
                'Rotting Crossing',
                'Shadowgrove',
                'Mistweb Hollow',
                'Deadmarshes',
                'Forgotten Catacombs',
                'Skull Peak',
                'Crypts of Ilmarin',
                'Wolfcross Road',
                'Plague Valley',
                'Wailing Caverns',
                'Bloodspire Cliffs',
                'Precursor Rift',
                'Silent Wastes',
                'Corrupted Keep',
                'Valley of Shadows',
                'Black Mire',
                'Empire’s Bones',
                'Helmor Depths',
                'Infernal Maw',
            ],
        ];

        foreach ($locations as $type => $names) {
            foreach ($names as $name) {
                $this->addSql("INSERT INTO locations (name, type) VALUES (:name, :type)", [
                    'name' => $name,
                    'type' => $type,
                ]);
            }
        }
    }

    public function down(Schema $schema): void
    {
        $this->addSql('TRUNCATE TABLE locations');
    }
}