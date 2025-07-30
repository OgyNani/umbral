<?php

namespace App\Service;

use App\Entity\Character;
use App\Entity\Inventory;
use App\Entity\Resource;
use App\Entity\UserGatheringLevel;
use App\Repository\CharacterRepository;
use App\Repository\InventoryRepository;
use App\Repository\ResourceRepository;
use App\Repository\UserGatheringLevelRepository;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;
use Redis;
use Symfony\Component\HttpFoundation\RequestStack;
use TelegramBot\Api\Types\ReplyKeyboardMarkup;

class GatheringService implements LoggerAwareInterface
{
    // Константы для категорий сбора
    public const CATEGORY_ALCHEMY = 'alchemy';
    public const CATEGORY_HUNT = 'hunting';
    public const CATEGORY_MINE = 'mining';
    public const CATEGORY_FISH = 'fishing';
    public const CATEGORY_FARM = 'farming';
    use LoggerAwareTrait;

    // Константы для рарити и бонусов опыта
    public const RARITY_COMMON = 'common';
    public const RARITY_UNCOMMON = 'uncommon';
    public const RARITY_RARE = 'rare';
    public const RARITY_EPIC = 'epic';
    public const RARITY_LEGENDARY = 'legendary';
    
    // Бонус опыта за разные редкости
    private const XP_BONUS = [
        self::RARITY_COMMON => 10,
        self::RARITY_UNCOMMON => 20,
        self::RARITY_RARE => 50,
        self::RARITY_EPIC => 100,
        self::RARITY_LEGENDARY => 200,
    ];
    
    // Время сбора в секундах (для тестов используем 10 секунд)
    private const GATHERING_TIME_SECONDS = 10; // для тестов 10 секунд
    // Для реального использования: private const GATHERING_TIME_SECONDS = 600; // 10 минут
    
    // Диапазон количества собираемых ресурсов
    private const MIN_RESOURCES = 1;
    private const MAX_RESOURCES = 5;

    private EntityManagerInterface $entityManager;
    private ResourceRepository $resourceRepository;
    private InventoryRepository $inventoryRepository;
    private UserGatheringLevelRepository $userGatheringLevelRepository;
    private CharacterRepository $characterRepository;
    private UserRepository $userRepository;
    private Redis $redis;
    private MessageService $messageService;
    private ButtonService $buttonService;
    private RequestStack $requestStack;

    public function __construct(
        EntityManagerInterface $entityManager,
        ResourceRepository $resourceRepository,
        InventoryRepository $inventoryRepository,
        UserGatheringLevelRepository $userGatheringLevelRepository,
        CharacterRepository $characterRepository,
        UserRepository $userRepository,
        LoggerInterface $logger,
        Redis $redis,
        MessageService $messageService,
        ButtonService $buttonService,
        RequestStack $requestStack
    ) {
        $this->entityManager = $entityManager;
        $this->resourceRepository = $resourceRepository;
        $this->inventoryRepository = $inventoryRepository;
        $this->userGatheringLevelRepository = $userGatheringLevelRepository;
        $this->characterRepository = $characterRepository;
        $this->userRepository = $userRepository;
        $this->logger = $logger;
        $this->redis = $redis;
        $this->messageService = $messageService;
        $this->buttonService = $buttonService;
        $this->requestStack = $requestStack;
    }

    /**
     * Обрабатывает начало процесса сбора ресурсов определенной категории
     */
    public function handleGatheringStart(int $telegramId, string $category): array
    {
        $this->logger->info('Starting gathering process', ['telegram_id' => $telegramId, 'category' => $category]);
        
        // Получаем название ремесла для сообщения
        $categoryName = $this->getCategoryDisplayName($category);
        
        // Создаем сообщение с предупреждением о времени
        $gatheringTime = self::GATHERING_TIME_SECONDS < 60 ? self::GATHERING_TIME_SECONDS . " seconds" : (self::GATHERING_TIME_SECONDS / 60) . " minutes";
        $message = "Do you want to start gathering {$categoryName}?\nThis will take {$gatheringTime}.";
        
        // Создаем клавиатуру с кнопками Начать и Назад
        $keyboard = new ReplyKeyboardMarkup([
            [ButtonService::BUTTON_START_GATHERING],
            [ButtonService::BUTTON_BACK],
        ], true, true);
        
        // Сохраняем категорию сбора в Redis для дальнейшего использования
        $this->redis->set("gathering:category:{$telegramId}", $category);
        
        return [
            'message' => $message,
            'keyboard' => $keyboard
        ];
    }
    
    /**
     * Начинает процесс сбора после подтверждения
     */
    public function startGathering(int $telegramId): array
    {
        // Получаем категорию сбора из Redis
        $category = $this->redis->get("gathering:category:{$telegramId}");
        if (!$category) {
            // Если персонаж не найден или категория не определена, возвращаем дефолтную клавиатуру
            $user = $this->userRepository->findByTelegramId($telegramId);
            $character = $user->getCharacter();
            if (!$user || !$character) {
                return [
                    'message' => "Error: gathering category not found. Please try again.",
                    'keyboard' => $this->buttonService->getDefaultKeyboard()
                ];
            }

            $location = $character->getLocation();
            return [
                'message' => "Error: gathering category not found. Please try again.",
                'keyboard' => $this->buttonService->getKeyboardForLocation($location)
            ];    
        }
        
        $this->logger->info('Gathering process confirmed', ['telegram_id' => $telegramId, 'category' => $category]);
        
        // Устанавливаем точное время окончания сбора в формате Y-m-d H:i:s
        $endTime = new \DateTime();
        $endTime->modify('+' . self::GATHERING_TIME_SECONDS . ' seconds');
        $endTimeFormatted = $endTime->format('Y-m-d H:i:s');
        
        // Сохраняем время окончания сбора в Redis
        $this->redis->set("gathering:active:{$telegramId}", $endTimeFormatted);
        
        // Сохраняем тип сбора
        $this->redis->set("gathering:type:{$telegramId}", $category);
        
        // Создаем сообщение о начале сбора
        $categoryName = $this->getCategoryDisplayName($category);
        $message = "You have started gathering {$categoryName}.\nYou will be notified when the process is complete.";
        
        // Создаем клавиатуру только с кнопкой отмены
        $keyboard = new ReplyKeyboardMarkup([
            [
                ButtonService::BUTTON_CANCEL_GATHERING,
                ButtonService::BUTTON_CHECK_GATHERING
            ],
        ], true, true);
        
        return [
            'message' => $message,
            'keyboard' => $keyboard
        ];
    }
    
    /**
     * Отменяет процесс сбора
     */
    public function cancelGathering(int $telegramId): array
    {
        // Удаляем все данные о сборе из Redis
        $this->redis->del("gathering:active:{$telegramId}");
        $this->redis->del("gathering:type:{$telegramId}");
        $this->redis->del("gathering:category:{$telegramId}");
        
        $this->logger->info('Gathering process canceled', ['telegram_id' => $telegramId]);
        
        // Получаем персонажа и его текущую локацию для отображения правильной клавиатуры
        $user = $this->userRepository->findByTelegramId($telegramId);
        $character = $user->getCharacter();
        if (!$user || !$character) {
            // Если персонажа нет, возвращаем базовую клавиатуру
            return [
                'message' => "Gathering has been canceled.",
                'keyboard' => $this->buttonService->getDefaultKeyboard()
            ];
        }
        
        $location = $character->getLocation();
        
        return [
            'message' => "Gathering has been canceled.",
            'keyboard' => $this->buttonService->getKeyboardForLocation($location)
        ];
    }
    
    /**
     * Проверяет статус активного сбора
     * 
     * @param int $telegramId ID пользователя в Telegram
     * @return array Массив с информацией о статусе сбора
     */
    public function checkGatheringStatus(int $telegramId): array
    {
        // Проверяем, активен ли процесс сбора
        $endTimeStr = $this->redis->get("gathering:active:{$telegramId}");

        $keyboard = new ReplyKeyboardMarkup([
            [
                ButtonService::BUTTON_CANCEL_GATHERING,
                ButtonService::BUTTON_CHECK_GATHERING
            ],
        ], true, true);
        
        if (!$endTimeStr) {
            return [
                'is_active' => false,
                'time_remaining' => 0
            ];
        }
        
        // Преобразуем строку времени обратно в объект DateTime
        $endTime = \DateTime::createFromFormat('Y-m-d H:i:s', $endTimeStr);
        $currentTime = new \DateTime();
        
        if ($currentTime >= $endTime) {
            return [
                'is_active' => false,
                'time_remaining' => 0,
                'end_time' => $endTimeStr,
                'keyboard' => $keyboard
            ];
        }
        
        // Вычисляем оставшееся время в секундах
        $timeRemaining = $endTime->getTimestamp() - $currentTime->getTimestamp();

        return [
            'is_active' => true,
            'time_remaining' => $timeRemaining,
            'end_time' => $endTimeStr,
            'category' => $this->redis->get("gathering:type:{$telegramId}"),
            'keyboard' => $keyboard,
        ];
    }
    
    /**
     * Проверяет, завершен ли процесс сбора
     */
    public function checkGatheringCompletion(int $telegramId): ?array
    {
        // Получаем время окончания сбора
        $endTimeStr = $this->redis->get("gathering:active:{$telegramId}");
        
        if (!$endTimeStr) {
            return null;
        }
        
        // Преобразуем строку времени обратно в объект DateTime
        $endTime = \DateTime::createFromFormat('Y-m-d H:i:s', $endTimeStr);
        $currentTime = new \DateTime();
        
        if ($currentTime < $endTime) {
            // Если время еще не наступило, возвращаем null
            return null;
        }
        
        // Получаем тип сбора
        $category = $this->redis->get("gathering:category:{$telegramId}");
        if (!$category) {
            // Удаляем все данные о сборе
            $this->redis->del("gathering:active:{$telegramId}");
            return null;
        }

        // Получаем пользователя по telegram_id и его активного персонажа
        $user = $this->userRepository->findOneBy(['telegram_id' => $telegramId]);
        if (!$user) {
            $this->logger->error('User not found', ['telegram_id' => $telegramId]);
            return [
                'message' => "Error: user not found.",
                'keyboard' => $this->buttonService->getMainMenuKeyboard()
            ];
        }
        
        $character = $user->getCharacter();
        $location = $character->getLocation();

        if (!$character) {
            $this->logger->error('No active character found', ['user_id' => $user->getId()]);
            return [
                'message' => "Error: character not found.",
                'keyboard' => $this->buttonService->getKeyboardForLocation($location)
            ];
        }
        
        // Выполняем сбор ресурсов
        $gatheringResult = $this->collectResources($character, $category);
        
        // Удаляем все данные о сборе из Redis
        $this->redis->del("gathering:active:{$telegramId}");
        $this->redis->del("gathering:type:{$telegramId}");
        $this->redis->del("gathering:category:{$telegramId}");
        
        return $gatheringResult;
    }
    
    /**
     * Собирает ресурсы определенной категории
     */
    private function collectResources(Character $character, string $category): array
    {        
        // Получаем уровень навыка персонажа для данной категории сбора
        $userGatheringLevel = $this->userGatheringLevelRepository->findOneBy([
            'user' => $character->getUser()
        ]);
        
        // Map category to the corresponding getter method
        $levelGetters = [
            self::CATEGORY_ALCHEMY => 'getAlchemyLvl',
            self::CATEGORY_HUNT => 'getHuntingLvl',
            self::CATEGORY_MINE => 'getMinesLvl',
            self::CATEGORY_FISH => 'getFishingLvl',
            self::CATEGORY_FARM => 'getFarmLvl'
        ];
        
        // Get current skill level (default to 0 if not found)
        $skillLevel = $userGatheringLevel ? $userGatheringLevel->{$levelGetters[$category]}() : 0;
        
        // Base resource amount (5-15)
        $baseResourceAmount = mt_rand(self::MIN_RESOURCES, self::MAX_RESOURCES);
        
        // Get location tier (1-5)
        $location = $character->getLocation();
        // $locationTier = $location->getLevel() ?: 1; // Если тир не указан, используем 1
        $locationTier = 1;
        
        // Рассчитываем бонусный процент дропа от тира локации
        $locationBonusPercent = ($locationTier - 1) * 10; // Tier 1: +0%, Tier 2: +10%, ..., Tier 5: +40%
        
        // Дополнительный процент дропа от уровня навыка (уровень/10)
        $levelBonusPercent = floor($skillLevel / 10);
        
        // Общий процент бонусного дропа
        $totalBonusPercent = $locationBonusPercent + $levelBonusPercent;
        
        // Количество дополнительных ресурсов по формуле: A * (B+C) / 100
        $bonusResources = floor($baseResourceAmount * $totalBonusPercent / 100);
        
        // Итоговое количество ресурсов
        $finalResourceAmount = $baseResourceAmount + $bonusResources;
        
        $this->logger->info('Resource amount calculation', [
            'baseAmount' => $baseResourceAmount,
            'locationTier' => $locationTier,
            'locationBonus' => $locationBonusPercent,
            'levelBonus' => $levelBonusPercent,
            'totalBonus' => $totalBonusPercent,
            'bonusResources' => $bonusResources,
            'finalAmount' => $finalResourceAmount
        ]);
        
        // Расчет шансов выпадения ресурсов разных редкостей
        $chances = $this->calculateRarityChances($skillLevel);
        
        $collectedResources = [];
        $totalExperience = 0;
        
        // Для каждой единицы сбора определяем редкость и выбираем конкретный ресурс
        for ($i = 0; $i < $finalResourceAmount; $i++) {
            // Определяем редкость для текущей единицы сбора
            $rarity = $this->determineRarity($chances);
            
            // Получаем случайный ресурс указанной категории и редкости
            $resource = $this->getRandomResource($category, $rarity);
            
            if ($resource) {
                // Если ресурс найден, добавляем его в инвентарь
                $this->addResourceToInventory($character, $resource);
                
                // Начисляем опыт за сбор ресурса в зависимости от его редкости
                $experienceBonus = self::XP_BONUS[$rarity] ?? 1;
                $totalExperience += $experienceBonus;
                
                // Добавляем информацию о собранном ресурсе в результат
                if (isset($collectedResources[$resource->getId()])) {
                    $collectedResources[$resource->getId()]['amount']++;
                } else {
                    $collectedResources[$resource->getId()] = [
                        'resource' => $resource,
                        'amount' => 1,
                        'rarity' => $rarity
                    ];
                }
            }
        }
        
        // Обновляем опыт пользователя
        if ($userGatheringLevel) {
            $expSetters = [
                self::CATEGORY_ALCHEMY => 'setAlchemyExp',
                self::CATEGORY_HUNT => 'setHuntingExp',
                self::CATEGORY_MINE => 'setMinesExp',
                self::CATEGORY_FISH => 'setFishingExp',
                self::CATEGORY_FARM => 'setFarmExp'
            ];

            $expGetters = [
                self::CATEGORY_ALCHEMY => 'getAlchemyExp',
                self::CATEGORY_HUNT => 'getHuntingExp',
                self::CATEGORY_MINE => 'getMinesExp',
                self::CATEGORY_FISH => 'getFishingExp',
                self::CATEGORY_FARM => 'getFarmExp'
            ];
            
            // Get current skill level (default to 0 if not found)
            $skillExp = $userGatheringLevel ? $userGatheringLevel->{$expGetters[$category]}() : 0;
            $gatherFinExpWithCurrent = $skillExp + $totalExperience;
            $userGatheringLevel->{$expSetters[$category]}($gatherFinExpWithCurrent);
            $this->entityManager->flush();
        }
        
        $this->logger->info('Resources collected', [
            'count' => count($collectedResources),
            'totalExperience' => $totalExperience
        ]);
        // Формируем сообщение о результатах сбора
        $message = "Gathering complete!\n\nYou collected:\n";
        
        foreach ($collectedResources as $resourceName => $data) {
            $rarityIcon = $this->getRarityIcon($data['rarity']);
            $message .= "{$rarityIcon} {$data['resource']->getName()} x{$data['amount']}\n";
        }
        
        $message .= "\nExperience gained: +{$totalExperience} XP";
        if ($userGatheringLevel) {
            $message .= "\nCurrent {$this->getCategoryDisplayName($category)} level: {$skillLevel}";
            $message .= "\nCurrent XP: {$gatherFinExpWithCurrent}";
        }

        $location = $character->getLocation();
        
        return [
            'message' => $message,
            'keyboard' => $this->buttonService->getKeyboardForLocation($location)
        ];
    }
    
    /**
     * Добавляет ресурс в инвентарь персонажа
     */
    private function addResourceToInventory(Character $character, Resource $resource): void
    {
        // Проверяем, есть ли уже такой ресурс в инвентаре
        $inventory = $this->inventoryRepository->findOneBy([
            'character' => $character,
            'resource' => $resource
        ]);
        
        if ($inventory) {
            // Если ресурс уже есть, увеличиваем количество
            $inventory->setQuantity($inventory->getQuantity() + 1);
        } else {
            // Если ресурса нет, создаем новую запись
            $inventory = new Inventory();
            $inventory->setCharacter($character);
            $inventory->setResource($resource);
            $inventory->setQuantity(1);
        }
        
        $this->entityManager->persist($inventory);
        $this->entityManager->flush();
    }
    
    /**
     * Получает случайный ресурс указанной категории и редкости
     */
    private function getRandomResource(string $category, string $rarity): ?Resource
    {
        // Получаем все ресурсы заданной категории и редкости
        $resources = $this->resourceRepository->findBy([
            'category' => $category,
            'rarity' => $rarity
        ]);
        
        if (empty($resources)) {
            $this->logger->warning('No resources found for category and rarity', [
                'category' => $category,
                'rarity' => $rarity
            ]);
            return null;
        }
        
        // Выбираем случайный ресурс
        $randomIndex = mt_rand(0, count($resources) - 1);
        return $resources[$randomIndex];
    }
    
    /**
     * Определяет редкость ресурса на основе шансов
     * 
     * Шансы определяются следующим образом:
     * - Для каждой редкости выполняется бросок от 1 до 100
     * - Если значение ≤ шансу редкости, то эта редкость выпадает
     * - Проверка идет от Legendary до Common
     * - Если ни одна из редкостей не выпала, выбирается Common
     */
    private function determineRarity(array $chances): string
    {
        // Проверка по убыванию редкости (от легендарного к обычному)
        $rarities = [
            self::RARITY_LEGENDARY,
            self::RARITY_EPIC,
            self::RARITY_RARE,
            self::RARITY_UNCOMMON,
            self::RARITY_COMMON
        ];
        
        foreach ($rarities as $rarity) {
            $roll = mt_rand(1, 100);
            if ($roll <= $chances[$rarity]) {
                return $rarity;
            }
        }
        
        // По умолчанию возвращаем common
        return self::RARITY_COMMON;
    }
    
    /**
     * Рассчитывает шансы выпадения ресурсов разной редкости в зависимости от уровня навыка
     */
    private function calculateRarityChances(int $skillLevel): array
    {
        // Базовые шансы согласно требованиям
        $chances = [
            self::RARITY_COMMON => 80,
            self::RARITY_UNCOMMON => 50,
            self::RARITY_RARE => 20,
            self::RARITY_EPIC => 8,
            self::RARITY_LEGENDARY => 2
        ];
        
        // Модифицируем шансы в зависимости от уровня навыка
        // Каждые 10 уровней увеличивают шанс на более редкие ресурсы
        $levelBonus = floor($skillLevel / 10);
        
        // Уменьшаем шанс обычных ресурсов, увеличиваем шанс редких
        // При этом сохраняем базовые шансы, если бонус маленький
        if ($levelBonus > 0) {
            $chances[self::RARITY_COMMON] = max(60, $chances[self::RARITY_COMMON] - $levelBonus * 2);
            $chances[self::RARITY_UNCOMMON] = min(65, $chances[self::RARITY_UNCOMMON] + $levelBonus);
            $chances[self::RARITY_RARE] = min(35, $chances[self::RARITY_RARE] + $levelBonus * 0.5);
            $chances[self::RARITY_EPIC] = min(15, $chances[self::RARITY_EPIC] + $levelBonus * 0.3);
            $chances[self::RARITY_LEGENDARY] = min(8, $chances[self::RARITY_LEGENDARY] + $levelBonus * 0.2);
        }
        
        return $chances;
    }
    
    /**
     * Получает отображаемое имя категории сбора
     */
    private function getCategoryDisplayName(string $category): string
    {
        $displayNames = [
            self::CATEGORY_ALCHEMY => 'Alchemy Resources',
            self::CATEGORY_HUNT => 'Hunting Resources',
            self::CATEGORY_MINE => 'Mining Resources',
            self::CATEGORY_FISH => 'Fishing Resources',
            self::CATEGORY_FARM => 'Farming Resources'
        ];
        
        return $displayNames[$category] ?? ucfirst($category);
    }
    
    /**
     * Возвращает иконку для рарити ресурса
     */
    private function getRarityIcon(string $rarity): string
    {
        $icons = [
            self::RARITY_COMMON => '⚪',
            self::RARITY_UNCOMMON => '🟢',
            self::RARITY_RARE => '🔵',
            self::RARITY_EPIC => '🟣',
            self::RARITY_LEGENDARY => '🟡'
        ];
        
        return $icons[$rarity] ?? '⚪';
    }
}
