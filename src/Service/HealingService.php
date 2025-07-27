<?php

namespace App\Service;

use App\Entity\Character;
use Doctrine\ORM\EntityManagerInterface;
use TelegramBot\Api\BotApi;
use Psr\Log\LoggerInterface;

class HealingService
{
    private const HEALING_INTERVAL = 10; // seconds
    private const HEALING_PERCENT = 15; // 15% of max HP
    
    private EntityManagerInterface $entityManager;
    private BotApi $botApi;
    private LoggerInterface $logger;
    private CombatService $combatService;
    
    // Fallback storage for CLI
    private static array $lastHealingTimes = [];
    
    public function __construct(
        EntityManagerInterface $entityManager,
        LoggerInterface $logger,
        CombatService $combatService
    ) {
        $this->entityManager = $entityManager;
        $this->logger = $logger;
        $this->combatService = $combatService;
        $this->botApi = new BotApi($_ENV['TELEGRAM_BOT_TOKEN']);
    }
    
    /**
     * Process healing for a character if not in combat
     * 
     * @return bool True if character was healed, false otherwise
     */
    public function processHealing(int $chatId, Character $character): bool
    {
        try {
        $this->logger->info(sprintf('Processing healing for character %s (ID: %d)', $character->getName(), $character->getId()));
        
        // Skip healing if in combat
        if ($this->combatService->isInCombat($chatId)) {
            $this->logger->info('Healing skipped: character is in combat');
            return false;
        }
        
        // Skip if already at max HP
        if ($character->getHp() >= $character->getMaxHp()) {
            $this->logger->info(sprintf('Healing skipped: character already at max HP (%d/%d)', $character->getHp(), $character->getMaxHp()));
            return false;
        }
        
        $now = time();
        $lastHealingTime = $this->getLastHealingTime($chatId);
        
        $this->logger->info(sprintf('Last healing time: %s, Current time: %s', 
            $lastHealingTime ? date('Y-m-d H:i:s', $lastHealingTime) : 'never', 
            date('Y-m-d H:i:s', $now)
        ));
        
        // Check if enough time has passed since last healing
        if ($lastHealingTime && ($now - $lastHealingTime < self::HEALING_INTERVAL)) {
            $timeLeft = self::HEALING_INTERVAL - ($now - $lastHealingTime);
            $this->logger->info(sprintf('Healing skipped: not enough time passed. %d seconds left', $timeLeft));
            return false;
        }
        
        // Calculate healing amount (10% of max HP)
        $healingAmount = (int) ceil($character->getMaxHp() * (self::HEALING_PERCENT / 100));
        $oldHp = $character->getHp();
        $newHp = min($oldHp + $healingAmount, $character->getMaxHp());
        $wasHealed = $newHp > $oldHp;
        
        $this->logger->info(sprintf(
            'Healing calculation: maxHP=%d, healing amount=%d, old HP=%d, new HP=%d, wasHealed=%s',
            $character->getMaxHp(),
            $healingAmount,
            $oldHp,
            $newHp,
            $wasHealed ? 'true' : 'false'
        ));
        
        if ($wasHealed) {
            $this->logger->info(sprintf(
                'Healing character %s: %d -> %d HP',
                $character->getName(),
                $character->getHp(),
                $newHp
            ));
            
            // Update character HP
            $character->setHp($newHp);
            $this->entityManager->flush();
            
            // Update last healing time
            $this->setLastHealingTime($chatId, $now);
            $this->logger->info('Last healing time updated to: ' . date('Y-m-d H:i:s', $now));
            
            // Send notification if HP is fully restored
            if ($newHp >= $character->getMaxHp()) {
                try {
                    $this->botApi->sendMessage(
                        $chatId,
                        '✨ Ваше здоровье полностью восстановлено!',
                    );
                } catch (\Exception $e) {
                    $this->logger->error('Failed to send healing notification: ' . $e->getMessage());
                }
            }
            return true;
        }
        
        return false;
        } catch (\Exception $e) {
            // Log error but don't crash the application
            $this->logger->error('Error in healing process: ' . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Get last healing time
     */
    private function getLastHealingTime(int $chatId): ?int
    {
        $time = self::$lastHealingTimes[$chatId] ?? null;
        $this->logger->info(sprintf('Retrieved last healing time: %s', 
            $time ? date('Y-m-d H:i:s', $time) : 'null'
        ));
        return $time;
    }
    
    /**
     * Set last healing time
     */
    private function setLastHealingTime(int $chatId, int $time): void
    {
        self::$lastHealingTimes[$chatId] = $time;
        $this->logger->info('Last healing time set to: ' . date('Y-m-d H:i:s', $time));
    }
}
