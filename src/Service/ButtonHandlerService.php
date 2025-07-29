<?php

namespace App\Service;

use App\Entity\Character;
use App\Entity\User;
use TelegramBot\Api\BotApi;
use TelegramBot\Api\Types\ReplyKeyboardMarkup;
use Psr\Log\LoggerInterface;
use App\Service\ButtonService;
use App\Service\CombatService;
use App\Service\GatheringService;
use App\Repository\UserGatheringLevelRepository;

class ButtonHandlerService
{
    private BotApi $botApi;
    private LoggerInterface $logger;
    private LocationService $locationService;
    private ButtonService $buttonService;
    private CombatService $combatService;
    private GatheringService $gatheringService;
    private UserGatheringLevelRepository $userGatheringLevelRepository;
    
    public function __construct(
        BotApi $botApi, 
        LoggerInterface $logger, 
        LocationService $locationService, 
        ButtonService $buttonService,
        CombatService $combatService,
        GatheringService $gatheringService,
        UserGatheringLevelRepository $userGatheringLevelRepository)
    {
        $this->botApi = $botApi;
        $this->logger = $logger;
        $this->locationService = $locationService;
        $this->buttonService = $buttonService;
        $this->combatService = $combatService;
        $this->gatheringService = $gatheringService;
        $this->userGatheringLevelRepository = $userGatheringLevelRepository;
    }
    
    /**
     * Обрабатывает команды от кнопок ReplyKeyboardMarkup
     */
    public function handleButtonCommand(int $chatId, string $buttonText, Character $character): void
    {
        // Детальное логирование для отладки
        $this->logger->info('Handling button command: "' . $buttonText . '"');
        $this->logger->info('Button text length: ' . strlen($buttonText));
        $this->logger->info('Button text hex: ' . bin2hex($buttonText));
        
        $this->logger->info(sprintf('Handling button command: %s for character %s', $buttonText, $character->getName()));
        
        // Check if character is in combat
        if ($this->combatService->isInCombat($chatId) && 
            $buttonText !== '❌ Cancel Search' && 
            !in_array($buttonText, CombatService::BODY_PARTS)) {
            $this->botApi->sendMessage(
                $chatId,
                'You are in combat and cannot perform other actions!',
                null,
                false
            );
            return;
        }
        
        // Handle combat-specific buttons
        if ($buttonText === '❌ Cancel Search') {
            $this->combatService->cancelCombatSearch($chatId, $character);
            return;
        }
        
        // Check if the button is a body part selection for combat (case-insensitive)
        foreach (CombatService::BODY_PARTS as $part) {
            if (strcasecmp($buttonText, $part) === 0) {
                // Нашли совпадение части тела
                $combatState = $this->combatService->getCombatState($chatId);
                if ($combatState && $combatState['state'] === CombatService::STATE_ATTACK_POINT_SELECTION) {
                    $this->combatService->handleAttackPointSelection($chatId, $part);
                    return;
                } elseif ($combatState && $combatState['state'] === CombatService::STATE_DEFENSE_POINT_SELECTION) {
                    $this->combatService->handleDefensePointSelection($chatId, $part);
                    return;
                }
                
                // Если мы дошли сюда, значит часть тела распознана, но состояние боя неверное
                $this->botApi->sendMessage($chatId, "You need to start combat first with the Fight command.");
                return;
            }
        }
        
        switch ($buttonText) {
            // Gathering buttons
            case ButtonService::BUTTON_GATHER:
                $this->handleGather($chatId, $character, GatheringService::CATEGORY_ALCHEMY);
                break;
                
            case ButtonService::BUTTON_HUNT:
                $this->handleGather($chatId, $character, GatheringService::CATEGORY_HUNT);
                break;
                
            case ButtonService::BUTTON_MINE:
                $this->handleGather($chatId, $character, GatheringService::CATEGORY_MINE);
                break;
                
            case ButtonService::BUTTON_FISH:
                $this->handleGather($chatId, $character, GatheringService::CATEGORY_FISH);
                break;
                
            case ButtonService::BUTTON_FARM:
                $this->handleGather($chatId, $character, GatheringService::CATEGORY_FARM);
                break;
            
            // Gathering action buttons
            case "Start Gathering":
                $this->handleStartGathering($chatId, $character);
                break;
                
            case "Cancel Gathering":
                $this->handleCancelGathering($chatId, $character);
                break;
                
            // Regular buttons
            case ButtonService::BUTTON_CHARACTER:
                $this->handleCharacterInfo($chatId, $character);
                break;

            case ButtonService::BUTTON_USER:
                $this->handleUserInfo($chatId, $character);
                break;
                
            case ButtonService::BUTTON_INVENTORY:
                $this->handleInventory($chatId, $character);
                break;
                
            case ButtonService::BUTTON_SHOP:
                $this->handleShop($chatId, $character);
                break;
                
            case ButtonService::BUTTON_MARKET:
                $this->handleMarket($chatId, $character);
                break;
                
            case ButtonService::BUTTON_HOUSE:
                $this->handleHouse($chatId, $character);
                break;
                
            case ButtonService::BUTTON_GUILD_HALL:
                $this->handleGuildHall($chatId, $character);
                break;
                
            case ButtonService::BUTTON_MAP:
                $this->handleMap($chatId, $character);
                break;
                
            case ButtonService::BUTTON_DUNGEON:
                $this->handleDungeon($chatId, $character);
                break;
                
            case ButtonService::BUTTON_FIGHT:
                $this->handleFight($chatId, $character);
                break;
                
            case ButtonService::BUTTON_EXIT_DUNGEON:
                $this->handleExitDungeon($chatId, $character);
                break;
                
            case ButtonService::BUTTON_EXPLORE:
                $this->handleExplore($chatId, $character);
                break;
                
            case ButtonService::BUTTON_HELP:
                $this->handleHelp($chatId);
                break;
                
            case ButtonService::BUTTON_BACK:
                $this->handleBack($chatId, $character);
                break;
                
            default:
                $this->handleUnknownCommand($chatId, $buttonText);
                break;
        }
    }

    private function handleUserInfo(int $chatId, Character $character): void
    {
        $user = $character->getUser();
        if (!$user) {
            $this->botApi->sendMessage(
                $chatId,
                "Error: User information not available.",
                null,
                false,
                null,
                $this->buttonService->getKeyboardForLocation($character->getLocation())
            );
            return;
        }
        
        // Получаем информацию о навыках сбора ресурсов
        $gatheringLevels = $this->userGatheringLevelRepository->findOneBy(['user' => $user]);
        
        $craftingInfo = "";
        if ($gatheringLevels) {
            $craftingInfo = sprintf(
                "\n\nCrafting Skills:\n" .
                "Alchemy: Lvl %d (Exp: %d)\n" .
                "Hunting: Lvl %d (Exp: %d)\n" .
                "Mining: Lvl %d (Exp: %d)\n" .
                "Fishing: Lvl %d (Exp: %d)\n" .
                "Farming: Lvl %d (Exp: %d)",
                $gatheringLevels->getAlchemyLvl(), $gatheringLevels->getAlchemyExp(),
                $gatheringLevels->getHuntingLvl(), $gatheringLevels->getHuntingExp(),
                $gatheringLevels->getMinesLvl(), $gatheringLevels->getMinesExp(),
                $gatheringLevels->getFishingLvl(), $gatheringLevels->getFishingExp(),
                $gatheringLevels->getFarmLvl(), $gatheringLevels->getFarmExp()
            );
        }
        
        $this->botApi->sendMessage(
            $chatId,
            sprintf(
                "User Info:\n\nName: %s\nCreated At: %s\nGold: %s\nEmeralds: %s%s",
                $user->getCharacter() ? $user->getCharacter()->getName() : $character->getName(),
                $user->getCreatedAt()->format('Y-m-d H:i:s'),
                $user->getGold(),
                $user->getEmeralds(),
                $craftingInfo
            ),
            null,
            false,
            null,
            $this->buttonService->getKeyboardForLocation($character->getLocation())
        );
    }
    
    /**
     * Показать информацию о персонаже
     */
    private function handleCharacterInfo(int $chatId, Character $character): void
    {
        // Преобразуем массив stats в строку для отображения
        $statsString = '';
        $stats = $character->getStats();
        if (!empty($stats)) {
            foreach ($stats as $key => $value) {
                $statsString .= sprintf("%s: %s\n", ucfirst($key), $value);
            }
        } else {
            $statsString = 'No stats available';
        }
        
        $this->botApi->sendMessage(
            $chatId,
            sprintf(
                "Character Info:\n\nName: %s\nGold: %s\nClass: %s\nLevel: %d\nExp: %d\nHP: %d/%d\nGender: %s\n\nStats:\n%s\nLocation: %s",
                $character->getName(),
                $character->getGold(),
                $character->getClass()->getName(),
                $character->getLevel(),
                $character->getExp(),
                $character->getHp(),
                $character->getMaxHp(),
                $character->getSex(),
                $statsString,
                $character->getLocation()->getName()
            ),
            null,
            false,
            null,
            $this->buttonService->getKeyboardForLocation($character->getLocation())
        );
    }
    
    /**
     * Показать инвентарь персонажа
     */
    private function handleInventory(int $chatId, Character $character): void
    {
        $this->botApi->sendMessage(
            $chatId, 
            "Your inventory is empty.",
            null,
            false,
            null,
            $this->buttonService->getKeyboardForLocation($character->getLocation())
        );
    }
    
    /**
     * Открыть магазин
     */
    private function handleShop(int $chatId, Character $character): void
    {
        $this->botApi->sendMessage(
            $chatId, 
            "Welcome to the shop! (Shop functionality coming soon)",
            null,
            false,
            null,
            $this->buttonService->getKeyboardForLocation($character->getLocation())
        );
    }
    
    /**
     * Открыть рынок
     */
    private function handleMarket(int $chatId, Character $character): void
    {
        $this->botApi->sendMessage(
            $chatId, 
            "Welcome to the market! (Market functionality coming soon)",
            null,
            false,
            null,
            $this->buttonService->getKeyboardForLocation($character->getLocation())
        );
    }
    
    /**
     * Открыть дом
     */
    private function handleHouse(int $chatId, Character $character): void
    {
        $this->botApi->sendMessage(
            $chatId, 
            "Welcome to your house! (House functionality coming soon)",
            null,
            false,
            null,
            $this->buttonService->getKeyboardForLocation($character->getLocation())
        );
    }
    
    /**
     * Открыть гильдию
     */
    private function handleGuildHall(int $chatId, Character $character): void
    {
        $this->botApi->sendMessage(
            $chatId, 
            "Welcome to the Guild Hall! (Guild functionality coming soon)",
            null,
            false,
            null,
            $this->buttonService->getKeyboardForLocation($character->getLocation())
        );
    }
    
    /**
     * Показать карту с доступными локациями для перехода
     */
    private function handleMap(int $chatId, Character $character): void
    {
        $currentLocation = $character->getLocation();
        $availableLocations = $this->locationService->getAvailableLocations($currentLocation);
        
        if (empty($availableLocations)) {
            $this->botApi->sendMessage($chatId, "There are no available locations to travel to from your current location.");
            return;
        }
        
        $message = sprintf("You are currently in %s.\n\nAvailable locations to travel:\n", $currentLocation->getName());
        
        foreach ($availableLocations as $location) {
            $message .= sprintf("- %s (%s)\n", $location->getName(), $location->getType());
        }
        
        $message .= "\nTo travel to a location, select it from the keyboard below.";
        
        // Создаем клавиатуру с доступными локациями
        $keyboard = $this->locationService->getLocationSelectionKeyboard($currentLocation);
        $replyMarkup = new ReplyKeyboardMarkup($keyboard, true, true);
        
        $this->botApi->sendMessage($chatId, $message, null, false, null, $replyMarkup);
    }
    
    /**
     * Войти в подземелье
     */
    private function handleDungeon(int $chatId, Character $character): void
    {
        $this->botApi->sendMessage(
            $chatId, 
            "Entering dungeon... (Dungeon functionality coming soon)",
            null,
            false,
            null,
            $this->buttonService->getKeyboardForLocation($character->getLocation())
        );
    }
    
    /**
     * Начать бой
     */
    private function handleFight(int $chatId, Character $character): void
    {
        $this->logger->info(sprintf('Starting combat for character %s in location %s', 
            $character->getName(), $character->getLocation()->getName()));
            
        // Start combat search
        $this->combatService->startCombatSearch($chatId, $character);
    }
    
    /**
     * Выйти из подземелья
     */
    private function handleExitDungeon(int $chatId, Character $character): void
    {
        $this->botApi->sendMessage(
            $chatId, 
            "Exiting dungeon... (Functionality coming soon)",
            null,
            false,
            null,
            $this->buttonService->getKeyboardForLocation($character->getLocation())
        );
    }
    
    /**
     * Исследовать местность
     */
    private function handleExplore(int $chatId, Character $character): void
    {
        $this->botApi->sendMessage(
            $chatId, 
            "Exploring the area... (Exploration functionality coming soon)",
            null,
            false,
            null,
            $this->buttonService->getKeyboardForLocation($character->getLocation())
        );
    }
    
    /**
     * Показать справку
     */
    private function handleHelp(int $chatId): void
    {
        $this->botApi->sendMessage(
            $chatId,
            "Available commands:\n\n" .
            "/start - Start or restart the game\n" .
            "Use the buttons below to interact with the game."
        );
    }
    
    /**
     * Обрабатывает кнопку "Назад"
     */
    private function handleBack(int $chatId, Character $character): void
    {
        $location = $character->getLocation();
        $this->logger->info(sprintf('Handling Back button for character %s in location %s', $character->getName(), $location->getName()));
        
        // Отправляем основное меню локации
        $this->botApi->sendMessage(
            $chatId,
            sprintf("You are in %s.", $location->getName()),
            null,
            false,
            null,
            $this->buttonService->getKeyboardForLocation($location)
        );
    }
    
    /**
     * Обрабатывает неизвестную команду
     */
    private function handleUnknownCommand(int $chatId, string $command): void
    {
        $this->botApi->sendMessage(
            $chatId, 
            sprintf("Unknown command: %s", $command),
            null,
            false,
            null
        );
    }
    
    /**
     * Обрабатывает начало процесса сбора ресурсов
     */
    private function handleGather(int $chatId, Character $character, string $category): void
    {
        $this->logger->info('Handling gathering request', ['category' => $category, 'character' => $character->getName()]);
        
        // Получаем данные для начала сбора через сервис
        $result = $this->gatheringService->handleGatheringStart($chatId, $category);
        
        // Отправляем сообщение с подтверждением
        $this->botApi->sendMessage(
            $chatId,
            $result['message'],
            null,
            false,
            null,
            $result['keyboard']
        );
    }
    
    /**
     * Обрабатывает подтверждение начала сбора
     */
    private function handleStartGathering(int $chatId, Character $character): void
    {
        $this->logger->info('Starting gathering process', ['character' => $character->getName()]);
        
        // Запускаем процесс сбора через сервис
        $result = $this->gatheringService->startGathering($chatId);
        
        // Отправляем сообщение с подтверждением
        $this->botApi->sendMessage(
            $chatId,
            $result['message'],
            null,
            false,
            null,
            $result['keyboard']
        );
    }
    
    /**
     * Обрабатывает отмену сбора
     */
    private function handleCancelGathering(int $chatId, Character $character): void
    {
        $this->logger->info('Canceling gathering process', ['character' => $character->getName()]);
        
        // Отменяем процесс сбора через сервис
        $result = $this->gatheringService->cancelGathering($chatId);
        
        // Отправляем сообщение с подтверждением
        $this->botApi->sendMessage(
            $chatId,
            $result['message'],
            null,
            false,
            null,
            $result['keyboard']
        );
    }
    
    /**
     * Проверяет завершение процесса сбора
     */
    public function checkGatheringCompletion(int $chatId): void
    {
        // Проверяем, завершен ли процесс сбора
        $result = $this->gatheringService->checkGatheringCompletion($chatId);
        
        // Если процесс сбора завершен, отправляем сообщение с результатами
        if ($result) {
            $this->botApi->sendMessage(
                $chatId,
                $result['message'],
                null,
                false,
                null,
                $result['keyboard']
            );
        }
    }
}
