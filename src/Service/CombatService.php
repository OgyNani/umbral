<?php

namespace App\Service;

use App\Entity\Character;
use App\Entity\Mob;
use App\Repository\MobRepository;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Predis\Client as RedisClient;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use TelegramBot\Api\BotApi;
use TelegramBot\Api\Types\ReplyKeyboardMarkup;

class CombatService
{
    // Combat states
    public const STATE_IDLE = 'idle';
    public const STATE_SEARCHING = 'searching';
    public const STATE_TARGET_SELECTION = 'target_selection';
    public const STATE_ATTACK_POINT_SELECTION = 'attack_point_selection';
    public const STATE_DEFENSE_POINT_SELECTION = 'defense_point_selection';
    public const STATE_COMBAT_RESULT = 'combat_result';
    
    // Body parts for attack and defense
    public const BODY_PARTS = [
        'Head',
        'Chest',
        'Leg',
        'Arm',
        'Waist',
        'Neck',
        'Shoulder'
    ];
    
    private BotApi $botApi;
    private EntityManagerInterface $entityManager;
    private MobRepository $mobRepository;
    private ButtonService $buttonService;
    private RequestStack $requestStack;
    private LoggerInterface $logger;
    private ?SessionInterface $session;
    private ?RedisClient $redis;
    
    // Fallback storage for CLI commands
    private static array $combatStateStorage = [];
    
    public function __construct(
        BotApi $botApi,
        EntityManagerInterface $entityManager,
        MobRepository $mobRepository,
        ButtonService $buttonService,
        RequestStack $requestStack,
        LoggerInterface $logger
    ) {
        $this->botApi = $botApi;
        $this->entityManager = $entityManager;
        $this->mobRepository = $mobRepository;
        $this->buttonService = $buttonService;
        $this->requestStack = $requestStack;
        $this->logger = $logger;
        
        try {
            $this->session = $requestStack->getSession();
            $this->logger->info('Combat session initialized successfully');
        } catch (\Exception $e) {
            // Session is not available (CLI context)
            $this->session = null;
            $this->logger->warning('Combat session not available, using fallback storage: ' . $e->getMessage());
        }
        
        try {
            // Initialize Redis client with default connection parameters
            $this->redis = new RedisClient([
                'scheme' => 'tcp',
                'host'   => 'redis',
                'port'   => 6379,
            ]);
            $this->logger->info('Redis client initialized successfully');
        } catch (\Exception $e) {
            $this->redis = null;
            $this->logger->warning('Redis client not available, using fallback storage: ' . $e->getMessage());
        }
    }
    
    /**
     * Start combat search for a character
     */
    public function startCombatSearch(int $chatId, Character $character): void
    {
        $this->logger->info(sprintf('Starting combat search for character %s (ID: %d)', 
            $character->getName(), $character->getId()));
            
        // Set combat state to searching
        $this->setCombatState($chatId, [
            'state' => self::STATE_SEARCHING,
            'character_id' => $character->getId(),
            'mob_id' => null,
            'attack_point' => null,
            'defense_point' => null
        ]);
        
        // Create cancel button keyboard
        $keyboard = new ReplyKeyboardMarkup([
            ['❌ Cancel Search']
        ], true, true);
        
        // Send searching message
        $this->botApi->sendMessage(
            $chatId,
            sprintf("🔍 %s начинает поиск противника в локации %s...", 
                $character->getName(), 
                $character->getLocation()->getName()
            ),
            'markdown',
            false,
            null,
            $keyboard
        );
        
        // Simulate search delay (will be handled by a separate method in real implementation)
        $this->findOpponent($chatId, $character);
    }
    
    /**
     * Cancel combat search
     */
    public function cancelCombatSearch(int $chatId, Character $character): void
    {
        $this->logger->info(sprintf('Cancelling combat search for character %s (ID: %d)', 
            $character->getName(), $character->getId()));
            
        // Clear combat state
        $this->clearCombatState($chatId);
        
        // Send cancel message
        $this->botApi->sendMessage(
            $chatId,
            sprintf("❌ %s прекратил поиск противника.", $character->getName()),
            'markdown',
            false,
            null,
            $this->buttonService->getKeyboardForLocation($character->getLocation())
        );
    }
    
    /**
     * Find an opponent for the character in the current location
     */
    private function findOpponent(int $chatId, Character $character): void
    {
        $this->logger->info(sprintf('Finding opponent for character %s in location %s', 
            $character->getName(), $character->getLocation()->getName()));
            
        // Find mobs in the current location
        $mobs = $this->mobRepository->findBy(['location' => $character->getLocation()]);
        
        if (empty($mobs)) {
            $this->logger->info('No mobs found in the location');
            
            // No mobs found in this location
            $this->botApi->sendMessage(
                $chatId,
                sprintf("🔍 %s не нашел противников в локации %s.", 
                    $character->getName(), 
                    $character->getLocation()->getName()
                ),
                'markdown',
                false,
                null,
                $this->buttonService->getKeyboardForLocation($character->getLocation())
            );
            
            // Clear combat state
            $this->clearCombatState($chatId);
            return;
        }
        
        // Select a random mob
        $mob = $mobs[array_rand($mobs)];
        $this->logger->info(sprintf('Selected mob: %s (ID: %d)', $mob->getName(), $mob->getId()));
        
        // Update combat state
        $combatState = $this->getCombatState($chatId);
        $combatState['state'] = self::STATE_ATTACK_POINT_SELECTION;
        $combatState['mob_id'] = $mob->getId();
        
        // Store mob stats in combat state instead of modifying the database
        $combatState['mob_stats'] = $mob->getStats();
        $combatState['mob_name'] = $mob->getName();
        $combatState['mob_level'] = $mob->getLevel();
        $combatState['mob_exp_reward'] = $mob->getExpReward();
        $combatState['mob_gold_reward'] = $mob->getGoldReward();
        
        $this->setCombatState($chatId, $combatState);
        
        // Send encounter message
        $this->botApi->sendMessage(
            $chatId,
            sprintf("⚔️ %s встретил противника: **%s** (Уровень %d)\n\n%s", 
                $character->getName(), 
                $mob->getName(),
                $mob->getLevel(),
                $mob->getDescription() ?? 'Нет описания'
            ),
            'markdown',
            false
        );
        
        // Start combat
        $this->promptAttackPointSelection($chatId);
    }
    
    /**
     * Prompt the user to select an attack point
     */
    public function promptAttackPointSelection(int $chatId): void
    {
        $this->logger->info(sprintf('Prompting attack point selection for chat ID: %d', $chatId));
        
        // Create keyboard with body parts
        $keyboard = [];
        $row = [];
        $i = 0;
        
        foreach (self::BODY_PARTS as $bodyPart) {
            $row[] = $bodyPart;
            $i++;
            
            if ($i % 2 === 0) {
                $keyboard[] = $row;
                $row = [];
            }
        }
        
        if (!empty($row)) {
            $keyboard[] = $row;
        }
        
        $replyKeyboard = new ReplyKeyboardMarkup($keyboard, true, true);
        
        // Send attack selection message
        $this->botApi->sendMessage(
            $chatId,
            "🎯 Choose your attack point:",
            'markdown',
            false,
            null,
            $replyKeyboard
        );
    }
    
    /**
     * Handle attack point selection
     */
    public function handleAttackPointSelection(int $chatId, string $bodyPart): void
    {
        $this->logger->info(sprintf('Handling attack point selection: %s for chat ID: %d', $bodyPart, $chatId));
        
        // Find the key for the selected body part (case-insensitive)
        $bodyPartKey = false;
        foreach (self::BODY_PARTS as $key => $part) {
            if (strcasecmp($part, $bodyPart) === 0) {
                $bodyPartKey = $key;
                break;
            }
        }
        
        if ($bodyPartKey === false) {
            $this->logger->warning(sprintf('Invalid body part selected: %s', $bodyPart));
            $this->botApi->sendMessage($chatId, "❌ Invalid attack point. Please choose from the provided options.");
            $this->promptAttackPointSelection($chatId);
            return;
        }
        
        // Update combat state
        $combatState = $this->getCombatState($chatId);
        $combatState['state'] = self::STATE_DEFENSE_POINT_SELECTION;
        $combatState['attack_point'] = $bodyPartKey;
        $this->setCombatState($chatId, $combatState);
        
        // Confirm attack point selection
        $this->botApi->sendMessage(
            $chatId,
            sprintf("🎯 You chose to attack: **%s**", $bodyPart),
            'markdown',
            false
        );
        
        // Prompt defense point selection
        $this->promptDefensePointSelection($chatId);
    }
    
    /**
     * Prompt the user to select a defense point
     */
    public function promptDefensePointSelection(int $chatId): void
    {
        $this->logger->info(sprintf('Prompting defense point selection for chat ID: %d', $chatId));
        
        // Create keyboard with body parts
        $keyboard = [];
        $row = [];
        $i = 0;
        
        foreach (self::BODY_PARTS as $bodyPart) {
            $row[] = $bodyPart;
            $i++;
            
            if ($i % 2 === 0) {
                $keyboard[] = $row;
                $row = [];
            }
        }
        
        if (!empty($row)) {
            $keyboard[] = $row;
        }
        
        $replyKeyboard = new ReplyKeyboardMarkup($keyboard, true, true);
        
        // Send defense selection message
        $this->botApi->sendMessage(
            $chatId,
            "🛡️ Choose your defense point:",
            'markdown',
            false,
            null,
            $replyKeyboard
        );
    }
    
    /**
     * Handle defense point selection
     */
    public function handleDefensePointSelection(int $chatId, string $bodyPart): void
    {
        $this->logger->info(sprintf('Handling defense point selection: %s for chat ID: %d', $bodyPart, $chatId));
        
        // Find the key for the selected body part (case-insensitive)
        $bodyPartKey = false;
        foreach (self::BODY_PARTS as $key => $part) {
            if (strcasecmp($part, $bodyPart) === 0) {
                $bodyPartKey = $key;
                break;
            }
        }
        
        if ($bodyPartKey === false) {
            $this->logger->warning(sprintf('Invalid body part selected: %s', $bodyPart));
            $this->botApi->sendMessage($chatId, "❌ Invalid defense point. Please choose from the provided options.");
            $this->promptDefensePointSelection($chatId);
            return;
        }
        
        // Update combat state
        $combatState = $this->getCombatState($chatId);
        $combatState['state'] = self::STATE_COMBAT_RESULT;
        $combatState['defense_point'] = $bodyPartKey;
        $this->setCombatState($chatId, $combatState);
        
        // Confirm defense point selection
        $this->botApi->sendMessage(
            $chatId,
            sprintf("🛡️ You chose to defend: **%s**", $bodyPart),
            'markdown',
            false
        );
        
        // Process combat round
        $this->processCombatRound($chatId);
    }
    
    /**
     * Process a combat round
     */
    public function processCombatRound(int $chatId): void
    {
        $this->logger->info(sprintf('Processing combat round for chat ID: %d', $chatId));
        
        $combatState = $this->getCombatState($chatId);
        
        if (!$combatState || $combatState['state'] !== self::STATE_COMBAT_RESULT) {
            $this->logger->warning('Invalid combat state for processing round');
            return;
        }
        
        // Get character and mob info from combat state
        $character = $this->entityManager->getRepository(Character::class)->find($combatState['character_id']);
        $mob = $this->entityManager->getRepository(Mob::class)->find($combatState['mob_id']);
        
        if (!$character || !$mob) {
            $this->logger->error('Character or mob not found');
            $this->botApi->sendMessage(
                $chatId, 
                "❌ Error: character or opponent not found.",
                null,
                false,
                null,
                $this->buttonService->getKeyboardForLocation($character ? $character->getLocation() : null)
            );
            $this->clearCombatState($chatId);
            return;
        }
        
        // Get attack and defense points
        $characterAttackPoint = $combatState['attack_point'];
        $characterDefensePoint = $combatState['defense_point'];
        
        // Mob randomly selects attack and defense points
        $mobAttackPoint = array_rand(self::BODY_PARTS);
        $mobDefensePoint = array_rand(self::BODY_PARTS);
        
        // Get stats
        $characterStats = $character->getStats();
        $mobStats = $combatState['mob_stats'];
        $mobName = $combatState['mob_name'];
        
        // Calculate damage dealt by character
        $characterHit = $characterAttackPoint !== $mobDefensePoint;
        $characterDamage = 0;
        $characterCritical = false;
        
        if ($characterHit) {
            // Base damage calculation
            $characterDamage = ($characterStats['attack'] ?? 1);
            
            // Critical hit check
            $critChance = ($characterStats['dexterity'] ?? 0) / 100;
            $characterCritical = (mt_rand(1, 100) / 100) <= $critChance;
            
            if ($characterCritical) {
                $critMultiplier = 1.5 + (($characterStats['strength'] ?? 0) / 100);
                $characterDamage = round($characterDamage * $critMultiplier);
            }
            
            // Apply defense reduction
            $defenseReduction = ($mobStats['defence'] ?? 0) / 100;
            $characterDamage = max(1, round($characterDamage * (1 - $defenseReduction)));
        }
        
        // Calculate damage dealt by mob
        $mobHit = $mobAttackPoint !== $characterDefensePoint;
        $mobDamage = 0;
        $mobCritical = false;
        
        if ($mobHit) {
            // Base damage calculation
            $mobDamage = ($mobStats['attack'] ?? 1);
            
            // Critical hit check
            $critChance = ($mobStats['dexterity'] ?? 0) / 100;
            $mobCritical = (mt_rand(1, 100) / 100) <= $critChance;
            
            if ($mobCritical) {
                $critMultiplier = 1.5 + (($mobStats['strength'] ?? 0) / 100);
                $mobDamage = round($mobDamage * $critMultiplier);
            }
            
            // Apply defense reduction
            $defenseReduction = ($characterStats['defence'] ?? 0) / 100;
            $mobDamage = max(1, round($mobDamage * (1 - $defenseReduction)));
        }
        
        // Apply damage
        $mobNewHp = $mobStats['hp'] - $characterDamage;
        $characterNewHp = $character->getHp() - $mobDamage;
        
        // Update mob HP in combat state (not in database)
        $mobStats['hp'] = max(0, $mobNewHp);
        $combatState['mob_stats'] = $mobStats;
        
        // Update character HP
        $character->setHp(max(0, $characterNewHp));
        
        // Save character changes only
        $this->entityManager->flush();
        
        // Prepare combat result message
        $resultMessage = "⚔️ **Combat Round Results:**\n\n";
        
        // Character attack result
        $resultMessage .= sprintf("🎯 **%s** attacks **%s**\n", 
            $character->getName(), 
            $characterAttackPoint
        );
        
        $resultMessage .= sprintf("🛡️ **%s** defends **%s**\n", 
            $mob->getName(), 
            $mobDefensePoint
        );
        
        if ($characterHit) {
            $resultMessage .= sprintf("✅ **Hit!** %s", $characterCritical ? "**CRITICAL STRIKE!** " : "");
            $resultMessage .= sprintf("Dealt **%d** damage.\n", $characterDamage);
        } else {
            $resultMessage .= "❌ **Blocked!** No damage dealt.\n";
        }
        
        // Mob attack result
        $resultMessage .= sprintf("\n🎯 **%s** attacks **%s**\n", 
            $mob->getName(), 
            $mobAttackPoint
        );
        
        $resultMessage .= sprintf("🛡️ **%s** defends **%s**\n", 
            $character->getName(), 
            $characterDefensePoint
        );
        
        if ($mobHit) {
            $resultMessage .= sprintf("✅ **Hit!** %s", $mobCritical ? "**CRITICAL STRIKE!** " : "");
            $resultMessage .= sprintf("Dealt **%d** damage.\n", $mobDamage);
        } else {
            $resultMessage .= "❌ **Blocked!** No damage dealt.\n";
        }
        
        // Current HP status
        $resultMessage .= sprintf("\n**%s**: %d HP", $character->getName(), $character->getHp());
        $resultMessage .= sprintf("\n**%s**: %d HP\n", $mobName, $mobStats['hp']);
        
        // Check if combat is over
        if ($mobStats['hp'] <= 0 || $characterNewHp <= 0) {
            $resultMessage .= "\n🏆 **Combat Ended!**\n";
            
            if ($mobStats['hp'] <= 0 && $characterNewHp <= 0) {
                // Both defeated
                $resultMessage .= "Both combatants have fallen in battle!";
            } else if ($mobStats['hp'] <= 0) {
                // Character won
                $resultMessage .= sprintf("**%s** is victorious!\n", $character->getName());
                
                // Award experience and gold
                $expReward = $combatState['mob_exp_reward'];
                $goldReward = $combatState['mob_gold_reward'];
                
                $character->setExp($character->getExp() + $expReward);
                $character->setGold((string)(intval($character->getGold()) + $goldReward));
                
                $resultMessage .= sprintf("Received: **%d XP** and **%d gold**", $expReward, $goldReward);
                
                // Save changes
                $this->entityManager->flush();
            } else {
                // Mob won
                $resultMessage .= sprintf("**%s** is victorious!\n", $mobName);
                $resultMessage .= "You lost the battle and fell unconscious. You have been moved to a safe location.";
                
                // TODO: Handle character defeat (teleport to safe location, etc.)
            }
            
            // Clear combat state
            $this->clearCombatState($chatId);
            
            // Send result message with location keyboard
            $this->botApi->sendMessage(
                $chatId,
                $resultMessage,
                'markdown',
                false,
                null,
                $this->buttonService->getKeyboardForLocation($character->getLocation())
            );
        } else {
            // Combat continues
            $resultMessage .= "\n⚔️ Combat continues! Choose your attack point:";
            
            // Send result message
            $this->botApi->sendMessage(
                $chatId,
                $resultMessage,
                'markdown',
                false
            );
            
            // Reset for next round
            $combatState['state'] = self::STATE_ATTACK_POINT_SELECTION;
            $combatState['attack_point'] = null;
            $combatState['defense_point'] = null;
            $this->setCombatState($chatId, $combatState);
            
            // Prompt for next attack
            $this->promptAttackPointSelection($chatId);
        }
    }
    
    /**
     * Check if a character is in combat
     */
    public function isInCombat(int $chatId): bool
    {
        $combatState = $this->getCombatState($chatId);
        return $combatState !== null && $combatState['state'] !== self::STATE_IDLE;
    }
    
    /**
     * Get current combat state
     */
    public function getCombatState(int $chatId): ?array
    {
        $state = null;
        
        // First check static storage (for current request)
        $state = self::$combatStateStorage[$chatId] ?? null;
        if ($state !== null) {
            $this->logger->debug(sprintf('Found combat state in static storage for chat_id: %d, state: %s', 
                $chatId, json_encode($state)));
            return $state;
        }
        
        // Then check Redis if available
        if ($this->redis !== null) {
            try {
                $redisKey = 'combat_state:' . $chatId;
                $redisData = $this->redis->get($redisKey);
                if ($redisData !== null) {
                    $state = json_decode($redisData, true);
                    $this->logger->debug(sprintf('Found combat state in Redis for chat_id: %d, state: %s', 
                        $chatId, $redisData));
                    
                    // Store in static storage for future use in this request
                    self::$combatStateStorage[$chatId] = $state;
                    return $state;
                }
            } catch (\Exception $e) {
                $this->logger->warning('Error getting combat state from Redis: ' . $e->getMessage());
            }
        }
        
        // Finally check session if available
        if ($this->session !== null) {
            $state = $this->session->get('combat_state_' . $chatId);
            $this->logger->debug(sprintf('Getting combat state from session for chat_id: %d, state: %s', 
                $chatId, $state ? json_encode($state) : 'null'));
            
            // If found in session, also store in static storage and Redis for future use
            if ($state !== null) {
                self::$combatStateStorage[$chatId] = $state;
                $this->logger->debug('Copied combat state from session to static storage');
                
                // Also store in Redis if available
                if ($this->redis !== null) {
                    try {
                        $redisKey = 'combat_state:' . $chatId;
                        $this->redis->set($redisKey, json_encode($state));
                        // Set expiration to 1 hour to prevent memory leaks
                        $this->redis->expire($redisKey, 3600);
                        $this->logger->debug('Copied combat state from session to Redis');
                    } catch (\Exception $e) {
                        $this->logger->warning('Error setting combat state in Redis: ' . $e->getMessage());
                    }
                }
            }
        }
        
        return $state;
    }
    
    /**
     * Set combat state
     */
    private function setCombatState(int $chatId, array $state): void
    {
        // Always store in static storage
        self::$combatStateStorage[$chatId] = $state;
        $this->logger->debug(sprintf('Setting combat state in static storage for chat_id: %d, state: %s', 
            $chatId, json_encode($state)));
        
        // Store in Redis if available
        if ($this->redis !== null) {
            try {
                $redisKey = 'combat_state:' . $chatId;
                $this->redis->set($redisKey, json_encode($state));
                // Set expiration to 1 hour to prevent memory leaks
                $this->redis->expire($redisKey, 3600);
                $this->logger->debug(sprintf('Setting combat state in Redis for chat_id: %d, state: %s', 
                    $chatId, json_encode($state)));
            } catch (\Exception $e) {
                $this->logger->warning('Error setting combat state in Redis: ' . $e->getMessage());
            }
        }
            
        // Also store in session if available
        if ($this->session !== null) {
            $this->session->set('combat_state_' . $chatId, $state);
            $this->logger->debug(sprintf('Setting combat state in session for chat_id: %d, state: %s', 
                $chatId, json_encode($state)));
        }
    }
    
    /**
     * Clear combat state
     */
    private function clearCombatState(int $chatId): void
    {
        // Always clear from static storage
        unset(self::$combatStateStorage[$chatId]);
        $this->logger->debug(sprintf('Clearing combat state from static storage for chat_id: %d', $chatId));
        
        // Clear from Redis if available
        if ($this->redis !== null) {
            try {
                $redisKey = 'combat_state:' . $chatId;
                $this->redis->del($redisKey);
                $this->logger->debug(sprintf('Clearing combat state from Redis for chat_id: %d', $chatId));
            } catch (\Exception $e) {
                $this->logger->warning('Error clearing combat state from Redis: ' . $e->getMessage());
            }
        }
        
        // Also clear from session if available
        if ($this->session !== null) {
            $this->session->remove('combat_state_' . $chatId);
            $this->logger->debug(sprintf('Clearing combat state from session for chat_id: %d', $chatId));
        }
    }
}
