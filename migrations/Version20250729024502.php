<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20250729024502 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Populate resources table with gathering resources';
    }

    public function up(Schema $schema): void
    {
        $resources = [
            'alchemy' => [
                'common' => ['Meadow Herbs', 'Glowcap Mushrooms', 'Crystal Water'],
                'uncommon' => ['Silverleaf', 'Ironroot Bark', 'Twilight Pollen'],
                'rare' => ['Dreamfoil', 'Echo Crystals', 'Void Essence'],
                'epic' => ['Whisperwind Petals', 'Etherbloom', 'Dragon\'s Scale Spores'],
                'legendary' => ['Phoenix Ash', 'Celestial Dew', 'Moonshadow Extract']
            ],
            'hunting' => [
                'common' => ['Wolf Hide', 'Boar Tusks', 'Rabbit Meat'],
                'uncommon' => ['Bear Pelt', 'Stag Antlers', 'Shadowcat Fangs'],
                'rare' => ['Nightfeather', 'Direhorn Hooves', 'Timber Wolf Heart'],
                'epic' => ['Thunderhawk Talons', 'Wyvern Scales', 'Frostclaw'],
                'legendary' => ['Chimera\'s Mane', 'Dragon Heartstring', 'Manticore Venom']
            ],
            'mining' => [
                'common' => ['Copper Ore', 'Tin Ore', 'Rough Stone'],
                'uncommon' => ['Iron Ore', 'Silver Ore', 'Mithril Dust'],
                'rare' => ['Gold Nuggets', 'Adamantite', 'Dark Iron'],
                'epic' => ['Cobalt Crystal', 'Eternium Ore', 'Star Ruby'],
                'legendary' => ['Astralite', 'Voidforge Ingot', 'Dragon Scale Mineral']
            ],
            'fishing' => [
                'common' => ['Silverfish', 'Bluegill', 'River Crab'],
                'uncommon' => ['Spotted Bass', 'Pearl Clams', 'Sunfish'],
                'rare' => ['Shadowfin Tuna', 'Starlight Eel', 'Emberscale Fish'],
                'epic' => ['Abyssal Squid', 'Ancient Pearl', 'Merloc Treasures'],
                'legendary' => ['Leviathan Scale', 'Tidehunter\'s Trident', 'Sea Dragon Egg']
            ],
            'farming' => [
                'common' => ['Wheat', 'Carrots', 'Apples'],
                'uncommon' => ['Moonberry', 'Golden Grain', 'Honey Roots'],
                'rare' => ['Sunfruit', 'Duskwheat', 'Starseed'],
                'epic' => ['Ghostpeppers', 'Harmony Beans', 'Dreamleaf'],
                'legendary' => ['Eternal Apples', 'Chronoberries', 'Ambrosia Flowers']
            ]
        ];

        $dropChances = [
            'common' => 80,
            'uncommon' => 50,
            'rare' => 20,
            'epic' => 8,
            'legendary' => 2
        ];

        foreach ($resources as $category => $rarityGroups) {
            foreach ($rarityGroups as $rarity => $items) {
                foreach ($items as $item) {
                    $this->addSql(
                        "INSERT INTO resources (name, description, category, rarity, drop_chance) 
                         VALUES (:name, :description, :category, :rarity, :drop_chance)",
                        [
                            'name' => $item,
                            'description' => $item,
                            'category' => $category,
                            'rarity' => $rarity,
                            'drop_chance' => $dropChances[$rarity]
                        ]
                    );
                }
            }
        }
    }

    public function down(Schema $schema): void
    {
        $this->addSql('TRUNCATE TABLE resources');
    }
}