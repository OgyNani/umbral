<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Migration for character levels table and data
 */
final class Version20250729024500 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Creates character_levels table and fills it with experience data for levels 1-150';
    }

    public function up(Schema $schema): void
    {        
        // Create index on level for faster lookups
        $this->addSql('CREATE UNIQUE INDEX idx_character_levels_level ON character_levels (level)');
        
        // Calculate and insert experience data for each level
        $this->fillCharacterLevelsTable();
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE character_levels');
    }
    
    private function calculateExpForLevel(int $level): int
    {
        $baseExp = 100;
        $exponentFactor = 2.0;
        $complexityMod = 0.1;
        $tierDivider = 10;
        
        $tierMultiplier = 1.0;
        if ($level > 100) {
            $tierMultiplier = 2.2; // Tier 3
        } elseif ($level > 50) {
            $tierMultiplier = 1.5; // Tier 2
        }
        
        return (int)($baseExp * pow($level, $exponentFactor) * (1 + $complexityMod * ($level / $tierDivider)) * $tierMultiplier);
    }
    
    private function fillCharacterLevelsTable(): void
    {
        $totalExp = 0;
        
        // Add level 1 with 0 required exp (you start at level 1)
        $this->addSql("INSERT INTO character_levels (level, total_experience) VALUES (1, 0)");
        
        // For each level 2-150, calculate the required experience based on the formula
        for ($level = 2; $level <= 150; $level++) {
            $expForLevel = $this->calculateExpForLevel($level - 1); // Exp needed to reach this level
            $totalExp += $expForLevel;
            
            $this->addSql("INSERT INTO character_levels (level, total_experience) VALUES ($level, $totalExp)");
        }
    }
}
