<?php

namespace App\Service;

use App\Entity\Character;
use TelegramBot\Api\BotApi;
use TelegramBot\Api\Types\ReplyKeyboardMarkup;
use Psr\Log\LoggerInterface;
use App\Service\ButtonService;

class ButtonHandlerService
{
    private BotApi $botApi;
    private LoggerInterface $logger;
    private LocationService $locationService;
    private ButtonService $buttonService;
    
    public function __construct(BotApi $botApi, LoggerInterface $logger, LocationService $locationService, ButtonService $buttonService)
    {
        $this->botApi = $botApi;
        $this->logger = $logger;
        $this->locationService = $locationService;
        $this->buttonService = $buttonService;
    }
    
    /**
     * Обрабатывает команды от кнопок ReplyKeyboardMarkup
     */
    public function handleButtonCommand(int $chatId, string $buttonText, Character $character): void
    {
        $this->logger->info(sprintf('Handling button command: %s for character %s', $buttonText, $character->getName()));
        
        switch ($buttonText) {
            case ButtonService::BUTTON_CHARACTER:
                $this->handleCharacterInfo($chatId, $character);
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
                "Character Info:\n\nName: %s\nClass: %s\nLevel: %d\nHP: %d/%d\nGender: %s\n\nStats:\n%s\nLocation: %s",
                $character->getName(),
                $character->getClass()->getName(),
                $character->getLevel(),
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
        $this->botApi->sendMessage(
            $chatId, 
            "Combat system coming soon!",
            null,
            false,
            null,
            $this->buttonService->getKeyboardForLocation($character->getLocation())
        );
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
            null,
            $this->buttonService->getKeyboardForLocation($character->getLocation())
        );
    }
}
