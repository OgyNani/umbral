<?php

namespace App\Service;

use App\Entity\Character;
use App\Entity\User;
use App\Entity\UserGatheringLevel;
use App\Repository\CharacterClassRepository;
use App\Repository\CharacterRepository;
use App\Repository\LocationRepository;
use App\Repository\UserGatheringLevelRepository;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Predis\Client as RedisClient;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use TelegramBot\Api\BotApi;
use TelegramBot\Api\Types\Inline\InlineKeyboardMarkup;
use TelegramBot\Api\Types\ReplyKeyboardMarkup;
use TelegramBot\Api\Types\ReplyKeyboardRemove;
use App\Service\GameTextService as Text;
use App\Service\ButtonService;

class CharacterCreationService
{
    private const STATE_NONE = 0;
    private const STATE_CLASS_SELECTION = 1;
    private const STATE_GENDER_SELECTION = 2;
    private const STATE_NAME_INPUT = 3;
    private const STATE_CONFIRMATION = 4;
    
    private BotApi $botApi;
    private EntityManagerInterface $entityManager;
    private UserRepository $userRepository;
    private CharacterRepository $characterRepository;
    private CharacterClassRepository $characterClassRepository;
    private LocationRepository $locationRepository;
    private ButtonService $buttonService;
    private RequestStack $requestStack;
    private ?SessionInterface $session = null;
    private LoggerInterface $logger;
    private ?RedisClient $redis;
    private UserGatheringLevelRepository $userGatheringLevelRepository;

    // Fallback storage for CLI commands
    private static array $creationStateStorage = [];

    public function __construct(
        BotApi $botApi,
        UserRepository $userRepository,
        EntityManagerInterface $entityManager,
        CharacterRepository $characterRepository,
        CharacterClassRepository $characterClassRepository,
        LocationRepository $locationRepository,
        ButtonService $buttonService,
        RequestStack $requestStack,
        LoggerInterface $logger,
        UserGatheringLevelRepository $userGatheringLevelRepository
    ) {
        $this->botApi = $botApi;
        $this->userRepository = $userRepository;
        $this->entityManager = $entityManager;
        $this->characterRepository = $characterRepository;
        $this->characterClassRepository = $characterClassRepository;
        $this->locationRepository = $locationRepository;
        $this->buttonService = $buttonService;
        $this->requestStack = $requestStack;
        $this->logger = $logger;
        $this->userGatheringLevelRepository = $userGatheringLevelRepository;
        
        try {
            $this->session = $requestStack->getSession();
            $this->logger->info('Session initialized successfully');
        } catch (\Exception $e) {
            // Session is not available (CLI context)
            $this->session = null;
            $this->logger->warning('Session not available, using fallback storage: ' . $e->getMessage());
        }
        
        // Инициализация Redis-клиента
        try {
            // Инициализация Redis-клиента со стандартными параметрами подключения
            $this->redis = new RedisClient([
                'scheme' => 'tcp',
                'host'   => 'redis',
                'port'   => 6379,
            ]);
            $this->logger->info('Redis client initialized successfully for CharacterCreationService');
        } catch (\Exception $e) {
            $this->redis = null;
            $this->logger->warning('Redis client not available for CharacterCreationService, using fallback storage: ' . $e->getMessage());
        }
    }
    
    /**
     * Checks if the user is waiting for a character name input
     */
    public function isWaitingForName(int $chatId): bool
    {
        $creationState = $this->getCreationState($chatId);
        return $creationState !== null && $creationState['state'] === self::STATE_NAME_INPUT;
    }
    
    /**
     * Handles text messages for character creation
     */
    public function handleCharacterCreationMessage(int $chatId, string $text): bool
    {
        // If user is not in character creation process, return false
        $creationState = $this->getCreationState($chatId);
        $this->logger->info(sprintf('handleCharacterCreationMessage: chatId=%d, text=%s, state=%s', 
            $chatId, $text, $creationState ? json_encode($creationState) : 'null'));
        
        if ($creationState === null) {
            $this->logger->warning('Creation state is null, cannot handle message in creation flow');
            return false;
        }
        
        $state = $creationState['state'];
        
        // Process messages based on current state
        switch ($state) {
            case self::STATE_CLASS_SELECTION:
                // Handle class selection
                if (stripos($text, 'confirm') !== false || stripos($text, '✅') !== false) {
                    $this->sendGenderSelectionMenu($chatId);
                } else if (stripos($text, 'back') !== false || stripos($text, '⬅️') !== false) {
                    $this->sendClassSelectionMenu($chatId);
                } else {
                    $this->handleClassSelectionByName($chatId, $text);
                }
                return true;
                
            case self::STATE_GENDER_SELECTION:
                // Handle gender selection
                if (stripos($text, 'confirm') !== false || stripos($text, '✅') !== false) {
                    $this->logger->info(sprintf('Gender selection confirmed for chat_id: %d, requesting character name', $chatId));
                    $this->requestCharacterName($chatId);
                } else if (stripos($text, 'back') !== false || stripos($text, '⬅️') !== false) {
                    $this->logger->info(sprintf('Going back to class selection for chat_id: %d', $chatId));
                    $this->sendClassSelectionMenu($chatId);
                } else {
                    $this->handleGenderSelectionByText($chatId, $text);
                }
                return true;
                
            case self::STATE_NAME_INPUT:
                // Name input is handled in TelegramController
                return true;
                
            case self::STATE_CONFIRMATION:
                // Handle confirmation
                if (stripos($text, 'confirm') !== false || stripos($text, 'yes') !== false || stripos($text, '✅') !== false) {
                    $this->createCharacter($chatId);
                } else if (stripos($text, 'back') !== false || stripos($text, 'no') !== false || stripos($text, '⬅️') !== false || stripos($text, '❌') !== false) {
                    $this->requestCharacterName($chatId);
                }
                return true;
        }
        
        // If state is unknown, return false
        return false;
    }
    
    /**
     * Start character creation process
     */
    public function startCharacterCreation(int $chatId, ?string $username, ?string $firstName): void
    {
        $this->logger->info(sprintf('Starting character creation for chat_id: %d, username: %s', $chatId, $username));
        
        // Check if user exists
        $user = $this->userRepository->findOneBy(['telegram_id' => $chatId]);
        
        if (!$user) {
            $this->logger->info(sprintf('User not found, creating new user for chat_id: %d', $chatId));
            // Create new user
            $user = new User();
            $user->setTelegramId($chatId);
            // Примечание: у сущности User нет методов setUsername и setFirstName
            // Эти поля не хранятся в базе данных, так как доступны из Telegram API
            $this->entityManager->persist($user);
            $this->entityManager->flush();
            $this->logger->info(sprintf('New user created with ID: %d', $user->getId()));
        } else {
            $this->logger->info(sprintf('Found existing user with ID: %d', $user->getId()));
        }
        
        // Check if user already has a character
        if ($user->getCharacter()) {
            $this->logger->info('User already has an active character, aborting character creation');
            $this->sendMainMenu($chatId, $user->getCharacter());
            return;
        }
        
        // Start character creation process
        $this->logger->info('No active character found, starting character creation process');
        $this->setCreationState($chatId, [
            'state' => self::STATE_CLASS_SELECTION,
            'username' => $username,
            'firstName' => $firstName,
            'class_id' => null,
            'gender' => null,
            'name' => null
        ]);
        
        $this->sendClassSelectionMenu($chatId);
    }
    
    /**
     * Send class selection menu
     */
    private function sendClassSelectionMenu(int $chatId, ?int $messageId = null): void
    {
        $this->logger->info(sprintf('Sending class selection menu to chat_id: %d', $chatId));
        
        // Get all available classes
        $classes = $this->characterClassRepository->findAll();
        $this->logger->info(sprintf('Found %d character classes', count($classes)));
        
        // Create keyboard with classes
        $keyboardButtons = [];
        foreach ($classes as $class) {
            $keyboardButtons[] = [$class->getName()];
            $this->logger->debug(sprintf('Added class to keyboard: %s', $class->getName()));
        }
        
        // Create Reply keyboard
        $keyboard = new ReplyKeyboardMarkup($keyboardButtons, true, true);
        
        // Set creation state
        $this->setCreationState($chatId, [
            'state' => self::STATE_CLASS_SELECTION,
            'class_id' => null,
            'gender' => null,
            'name' => null
        ]);
        $this->logger->info(sprintf('Set creation state for chat_id: %d to CLASS_SELECTION', $chatId));
        
        try {
            $this->botApi->sendMessage($chatId, Text::CLASS_SELECTION_TITLE, 'markdown', false, null, $keyboard);
            $this->logger->info('Class selection message sent successfully');
        } catch (\Exception $e) {
            $this->logger->error('Error sending class selection message: ' . $e->getMessage());
        }
    }
    
    /**
     * Handle class selection by name
     */
    private function handleClassSelectionByName(int $chatId, string $className): void
    {
        $this->logger->info(sprintf('handleClassSelectionByName: chatId=%d, className=%s', $chatId, $className));
        
        // Try to find class by exact name
        $class = $this->characterClassRepository->findOneBy(['name' => $className]);
        $this->logger->info(sprintf('Class search by exact name result: %s', $class ? 'found' : 'not found'));
        
        // If not found, try case-insensitive or partial match
        if (!$class) {
            $classes = $this->characterClassRepository->findAll();
            foreach ($classes as $possibleClass) {
                if (strcasecmp($possibleClass->getName(), $className) === 0 || 
                    stripos($possibleClass->getName(), $className) !== false || 
                    stripos($className, $possibleClass->getName()) !== false) {
                    $class = $possibleClass;
                    break;
                }
            }
        }
        
        if (!$class) {
            $this->botApi->sendMessage($chatId, sprintf(Text::CLASS_NOT_FOUND, $className));
            $this->sendClassSelectionMenu($chatId);
            return;
        }
        
        $creationState = $this->getCreationState($chatId);
        $creationState['class_id'] = $class->getId();
        $this->setCreationState($chatId, $creationState);
        
        $baseStats = $class->getBaseStats();
        $maxBaseStats = $class->getMaxBaseStats();
        $statsText = "";
        
        if (is_array($baseStats) && is_array($maxBaseStats)) {
            foreach ($baseStats as $stat => $value) {
                $maxValue = $maxBaseStats[$stat] ?? '?';
                $statsText .= ucfirst($stat) . ": " . $value . " / " . $maxValue . " (max)\n";
            }
        }
        
        $text = sprintf(Text::CLASS_SELECTED, $class->getName(), $statsText);
        
        // Create buttons for confirmation or going back
        $keyboard = new ReplyKeyboardMarkup([
            [Text::BUTTON_CONFIRM],
            [Text::BUTTON_BACK]
        ], true, true);
        
        $this->botApi->sendMessage(
            $chatId,
            $text,
            'markdown',
            false,
            null,
            $keyboard
        );
        
        // Обновляем состояние через метод setCreationState
        $creationState = $this->getCreationState($chatId);
        $creationState['state'] = self::STATE_CLASS_SELECTION; // Оставляем в том же состоянии, пока не нажата кнопка Confirm
        $this->setCreationState($chatId, $creationState);
        $this->logger->info(sprintf('Updated creation state for chat_id: %d, class selected: %s', $chatId, $class->getName()));
    }
    
    /**
     * Send gender selection menu
     */
    private function sendGenderSelectionMenu(int $chatId): void
    {
        $creationState = $this->getCreationState($chatId);
        $creationState['state'] = self::STATE_GENDER_SELECTION;
        $this->setCreationState($chatId, $creationState);
        
        $text = Text::GENDER_SELECTION_TITLE;
        
        // Create buttons for gender selection
        $keyboard = new ReplyKeyboardMarkup([
            [Text::BUTTON_MALE, Text::BUTTON_FEMALE],
            [Text::BUTTON_BACK]
        ], true, true);
        
        $this->botApi->sendMessage(
            $chatId,
            $text,
            'markdown',
            false,
            null,
            $keyboard
        );
    }
    
    /**
     * Handle gender selection by text
     */
    private function handleGenderSelectionByText(int $chatId, string $genderText): void
    {
        $creationState = $this->getCreationState($chatId);
        
        if (stripos($genderText, 'male') !== false && stripos($genderText, 'female') === false) {
            $creationState['gender'] = 'male';
        } elseif (stripos($genderText, 'female') !== false) {
            $creationState['gender'] = 'female';
        } else {
            $this->botApi->sendMessage($chatId, Text::INVALID_GENDER);
            $this->sendGenderSelectionMenu($chatId);
            return;
        }
        
        $genderName = $creationState['gender'] === 'male' ? 'Male' : 'Female';
        $text = sprintf(Text::GENDER_SELECTED, $genderName);
        
        // Create buttons for confirmation or going back
        $keyboard = new ReplyKeyboardMarkup([
            [Text::BUTTON_CONFIRM],
            [Text::BUTTON_BACK]
        ], true, true);
        
        $this->botApi->sendMessage(
            $chatId,
            $text,
            'markdown',
            false,
            null,
            $keyboard
        );
        
        // Сохраняем выбранный пол, но остаемся в том же состоянии
        // Состояние изменится только после подтверждения
        $this->setCreationState($chatId, $creationState);
        $this->logger->info(sprintf('Gender selected for chat_id: %d, gender: %s', $chatId, $creationState['gender']));
    }

    /**
     * Request character name
     */
    private function requestCharacterName(int $chatId): void
    {
        $creationState = $this->getCreationState($chatId);
        $creationState['state'] = self::STATE_NAME_INPUT;
        $this->setCreationState($chatId, $creationState);
        
        // Hide keyboard for text input
        $removeKeyboard = new ReplyKeyboardRemove(true);
        
        $this->botApi->sendMessage(
            $chatId,
            Text::NAME_INPUT_REQUEST,
            'markdown',
            false,
            null,
            $removeKeyboard
        );
    }
    
    /**
     * Handle name input
     */
    public function handleNameInput(int $chatId, string $name): void
    {
        // Validate name length
        if (mb_strlen($name) < 3) {
            $this->botApi->sendMessage($chatId, Text::NAME_TOO_SHORT);
            return;
        }
        
        if (mb_strlen($name) > 20) {
            $this->botApi->sendMessage($chatId, Text::NAME_TOO_LONG);
            return;
        }
        
        // Check if name already exists
        $existingCharacter = $this->characterRepository->findOneBy(['name' => $name]);
        if ($existingCharacter) {
            $this->botApi->sendMessage($chatId, Text::NAME_EXISTS);
            return;
        }
        
        $creationState = $this->getCreationState($chatId);
        $creationState['name'] = $name;
        $this->setCreationState($chatId, $creationState);
        $this->showCharacterConfirmation($chatId);
    }
    
    /**
     * Show character confirmation
     */
    private function showCharacterConfirmation(int $chatId): void
    {
        $creationState = $this->getCreationState($chatId);
        $creationState['state'] = self::STATE_CONFIRMATION;
        $this->setCreationState($chatId, $creationState);
        
        $classId = $creationState['class_id'];
        $class = $this->characterClassRepository->find($classId);
        $className = $class ? $class->getName() : 'Unknown';
        
        $gender = $creationState['gender'] === 'male' ? 'Male' : 'Female';
        $name = $creationState['name'];
        
        $text = sprintf(Text::CHARACTER_CONFIRMATION, $className, $gender, $name);
        
        // Create buttons for confirmation
        $keyboard = new ReplyKeyboardMarkup([
            [Text::BUTTON_YES],
            [Text::BUTTON_NO]
        ], true, true);
        
        $this->botApi->sendMessage(
            $chatId,
            $text,
            'markdown',
            false,
            null,
            $keyboard
        );
    }
    
    /**
     * Create character
     */
    private function createCharacter(int $chatId): void
    {
        $this->logger->info(sprintf('Creating character for chat_id: %d', $chatId));
        $user = $this->userRepository->findOneBy(['telegram_id' => $chatId]);
        
        if (!$user) {
            $this->logger->error(sprintf('User not found for chat_id: %d', $chatId));
            $this->botApi->sendMessage($chatId, "Error: User not found.");
            return;
        }
        
        $creationState = $this->getCreationState($chatId);
        $classId = $creationState['class_id'];
        $class = $this->characterClassRepository->find($classId);
        
        if (!$class) {
            $this->logger->error(sprintf('Class not found for ID: %d', $classId));
            $this->botApi->sendMessage($chatId, "Error: Class not found.");
            return;
        }
        
        // Получаем локацию с ID=1 (Test)
        $location = $this->locationRepository->find(1);
        if (!$location) {
            $this->logger->error('Default location with ID=1 not found');
            $this->botApi->sendMessage($chatId, "Error: Default location not found.");
            return;
        }
        
        $this->logger->info(sprintf('Creating character with name: %s, gender: %s, class: %s, location: %s', 
            $creationState['name'], $creationState['gender'], $class->getName(), $location->getName()));
            
        $character = new Character();
        $character->setName($creationState['name']);
        $character->setGender($creationState['gender']);
        $character->setClass($class); // Используем правильный метод setClass
        $character->setLevel(1);
        $character->setExp(0); // Используем правильный метод setExp
        $character->setUser($user);
        $character->setLocation($location); // Устанавливаем объект локации
        
        // Set base stats from class
        $baseStats = $class->getBaseStats();
        if (is_array($baseStats)) {
            $character->setStats($baseStats);
            
            // Устанавливаем HP и maxHP из статов класса
            if (isset($baseStats['hp'])) {
                $character->setHp($baseStats['hp']);
                $character->setMaxHp($baseStats['hp']);
                $this->logger->info(sprintf('Setting HP from class stats: %d', $baseStats['hp']));
            } else {
                // Если в статах нет HP, используем значение по умолчанию
                $character->setHp(30);
                $character->setMaxHp(30);
                $this->logger->info('HP not found in class stats, using default value: 30');
            }
        } else {
            // Если статы не являются массивом, используем значение по умолчанию
            $character->setHp(30);
            $character->setMaxHp(30);
            $character->setStats([]);
            $this->logger->info('Class stats are not an array, using default HP value: 30');
        }
        
        $this->logger->info('Persisting character to database');
        $this->entityManager->persist($character);
        
        // Добавляем персонажа к пользователю
        $user->addCharacter($character);
        $this->entityManager->flush(); // Сначала сохраняем персонажа, чтобы получить ID
        
        // Устанавливаем персонажа как текущего для пользователя
        $user->setCurrentCharacterId($character->getId());
        $this->entityManager->flush();
        
        // Инициализация уровней навыков сбора ресурсов
        $this->logger->info('Initializing gathering skills for new character');
        $gatheringLevel = new UserGatheringLevel();
        $gatheringLevel->setUser($user);
        $gatheringLevel->setAlchemyLvl(0);
        $gatheringLevel->setHuntingLvl(0);
        $gatheringLevel->setMinesLvl(0);
        $gatheringLevel->setFishingLvl(0);
        $gatheringLevel->setFarmLvl(0);
        $gatheringLevel->setAlchemyExp(0);
        $gatheringLevel->setHuntingExp(0);
        $gatheringLevel->setMinesExp(0);
        $gatheringLevel->setFishingExp(0);
        $gatheringLevel->setFarmExp(0);
        
        $this->entityManager->persist($gatheringLevel);
        $this->entityManager->flush();
        $this->logger->info(sprintf('Gathering skills initialized for user ID: %d', $user->getId()));
        
        // Clear creation state
        $this->clearCreationState($chatId);
        $this->logger->info('Character creation state cleared');
        
        // Send success message and show main menu
        $this->botApi->sendMessage(
            $chatId,
            sprintf(Text::CHARACTER_CREATED, $character->getName()),
            'markdown'
        );
        $this->logger->info('Character creation success message sent');
        
        $this->sendMainMenu($chatId, $character);
    }
    
    /**
     * Send main menu
     */
    private function sendMainMenu(int $chatId, Character $character): void
    {
        $text = Text::MAIN_MENU_TITLE . "\n\n" . sprintf(
            Text::MAIN_MENU_CHARACTER_INFO,
            $character->getName(),
            $character->getLevel(),
            $character->getClass()->getName(),
            $character->getLocation()->getName() // Получаем название локации из объекта
        );
        
        // Получаем клавиатуру в зависимости от типа локации персонажа
        $keyboard = $this->buttonService->getKeyboardForLocation($character->getLocation());
        
        $this->botApi->sendMessage(
            $chatId,
            $text,
            'markdown',
            false,
            null,
            $keyboard
        );
    }
    
    /**
     * Handle character creation callback (legacy support)
     */
    public function handleCharacterCreationCallback(int $chatId, string $callbackData, int $messageId, string $callbackId): void
    {
        // For backward compatibility, start character creation flow
        $this->startCharacterCreation($chatId);
        
        // Answer callback query to remove loading indicator
        $this->botApi->answerCallbackQuery($callbackId);
    }
    
    /**
     * Get creation state from session
     */
    private function getCreationState(int $chatId): ?array
    {
        $state = null;
        $redisKey = 'character_creation_' . $chatId;
        
        $this->logger->info(sprintf('getCreationState: chatId=%d, session=%s, redis=%s', 
            $chatId, $this->session !== null ? 'available' : 'not available',
            $this->redis !== null ? 'available' : 'not available'));
        
        // Сначала проверяем в Redis, если доступен
        if ($this->redis !== null) {
            try {
                $redisState = $this->redis->get($redisKey);
                if ($redisState) {
                    $state = json_decode($redisState, true);
                    $this->logger->info(sprintf('Found creation state in Redis for chat_id: %d, state: %s', 
                        $chatId, json_encode($state)));
                    return $state;
                }
            } catch (\Exception $e) {
                $this->logger->warning('Error getting creation state from Redis: ' . $e->getMessage());
            }
        }
        
        // Затем проверяем в сессии
        if ($this->session !== null) {
            $state = $this->session->get($redisKey);
            $this->logger->info(sprintf('Getting creation state from session for chat_id: %d, state: %s', 
                $chatId, $state ? json_encode($state) : 'null'));
                
            // Если нашли в сессии, сохраняем также в Redis для будущего использования
            if ($state && $this->redis !== null) {
                try {
                    $this->redis->set($redisKey, json_encode($state));
                    $this->logger->info('Copied creation state from session to Redis');
                } catch (\Exception $e) {
                    $this->logger->warning('Error copying creation state to Redis: ' . $e->getMessage());
                }
            }
        } else {
            // Наконец, проверяем в статическом хранилище
            $state = self::$creationStateStorage[$chatId] ?? null;
            $this->logger->info(sprintf('Getting creation state from static storage for chat_id: %d, state: %s', 
                $chatId, $state ? json_encode($state) : 'null'));
            
            // Вывод всех сохраненных состояний в статическом хранилище
            $this->logger->info('All stored states in static storage: ' . json_encode(self::$creationStateStorage));
        }
        
        return $state;
    }
    
    /**
     * Set creation state in session
     */
    private function setCreationState(int $chatId, array $state): void
    {
        $redisKey = 'character_creation_' . $chatId;
        
        $this->logger->info(sprintf('setCreationState: setting state for chatId=%d, state=%s, sessionAvailable=%s, redisAvailable=%s', 
            $chatId, json_encode($state), $this->session !== null ? 'yes' : 'no', $this->redis !== null ? 'yes' : 'no'));
        
        // Сохраняем в Redis, если доступен
        if ($this->redis !== null) {
            try {
                $this->redis->set($redisKey, json_encode($state));
                $this->logger->info(sprintf('State set in REDIS for chat_id: %d, state: %s', 
                    $chatId, json_encode($state)));
                    
                // Проверяем, что состояние действительно сохранилось в Redis
                $redisCheck = $this->redis->get($redisKey);
                $this->logger->info(sprintf('VERIFICATION: State in Redis after save: %s', 
                    $redisCheck ?: 'null'));
            } catch (\Exception $e) {
                $this->logger->warning('Error setting creation state in Redis: ' . $e->getMessage());
            }
        }
        
        // Также сохраняем в сессии
        if ($this->session !== null) {
            $this->session->set($redisKey, $state);
            $this->session->save(); // Принудительное сохранение сессии
            $this->logger->info(sprintf('State set in SESSION for chat_id: %d, state: %s', 
                $chatId, json_encode($state)));
                
            // Сразу проверяем, что состояние действительно сохранилось
            $checkState = $this->session->get($redisKey);
            $this->logger->info(sprintf('VERIFICATION: State in session after save: %s', 
                $checkState ? json_encode($checkState) : 'null'));
        } 
        
        // Сохраняем также в статическом хранилище (для CLI или в случае отсутствия Redis и сессии)
        self::$creationStateStorage[$chatId] = $state;
        $this->logger->info(sprintf('State set in STATIC STORAGE for chat_id: %d, state: %s', 
            $chatId, json_encode($state)));
    }
    
    /**
     * Clear creation state from session
     */
    private function clearCreationState(int $chatId): void
    {
        $redisKey = 'character_creation_' . $chatId;
        $this->logger->info(sprintf('clearCreationState: chatId=%d', $chatId));
        
        // Очищаем состояние из Redis, если доступен
        if ($this->redis !== null) {
            try {
                $this->redis->del($redisKey);
                $this->logger->info(sprintf('Cleared creation state from Redis for chat_id: %d', $chatId));
            } catch (\Exception $e) {
                $this->logger->warning('Error clearing creation state from Redis: ' . $e->getMessage());
            }
        }
        
        // Очищаем состояние из сессии
        if ($this->session !== null) {
            $this->session->remove($redisKey);
            $this->logger->info(sprintf('Cleared creation state from session for chat_id: %d', $chatId));
        }
        
        // Очищаем состояние из статического хранилища
        unset(self::$creationStateStorage[$chatId]);
        $this->logger->info(sprintf('Cleared creation state from static storage for chat_id: %d', $chatId));
    }
    
    /**
     * Получает активного персонажа по chat_id
     */
    public function getActiveCharacter(int $chatId): ?Character
    {
        // Получаем пользователя по chat_id
        $user = $this->userRepository->findOneBy(['telegram_id' => $chatId]);
        
        if (!$user) {
            return null;
        }
        
        // Получаем активного персонажа пользователя
        return $user->getCharacter();
    }
}
