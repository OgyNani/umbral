<?php

namespace App\Service;

use App\Entity\Location;
use TelegramBot\Api\Types\ReplyKeyboardMarkup;

class ButtonService
{
    // Константы для кнопок
    public const BUTTON_CHARACTER = '👤 Character';
    public const BUTTON_USER = '👤 User';
    public const BUTTON_INVENTORY = '🎒 Inventory';
    public const BUTTON_SHOP = '🏪 Shop';
    public const BUTTON_MARKET = '🏪 Market';
    public const BUTTON_HOUSE = '🏠 House';
    public const BUTTON_GUILD_HALL = '🏠 Guild Hall';
    public const BUTTON_MAP = '🗺️ Map';
    public const BUTTON_DUNGEON = '⚔️ Dungeon';
    public const BUTTON_FIGHT = '⚔️ Fight';
    public const BUTTON_EXIT_DUNGEON = '🚪 Exit from dungeon';
    public const BUTTON_TAVERN = '🏠 Tavern';
    public const BUTTON_EXPLORE = '🗺️ Explore';
    public const BUTTON_HELP = '📚 Help';
    public const BUTTON_BACK = '🔙 Back';
    
    /**
     * Возвращает клавиатуру в зависимости от типа локации
     */
    public function getKeyboardForLocation(Location $location): ReplyKeyboardMarkup
    {
        $locationType = $location->getType();
        
        switch ($locationType) {
            case 'city':
                return $this->getCityKeyboard();
            case 'dungeon':
                return $this->getDungeonKeyboard();
            case 'world':
                return $this->getWorldKeyboard();
            default:
                return $this->getDefaultKeyboard();
        }
    }
    
    /**
     * Клавиатура для городских локаций
     */
    private function getCityKeyboard(): ReplyKeyboardMarkup
    {
        return new ReplyKeyboardMarkup([
            [self::BUTTON_CHARACTER, self::BUTTON_USER, self::BUTTON_INVENTORY],
            [self::BUTTON_SHOP, self::BUTTON_MARKET],
            [self::BUTTON_HOUSE, self::BUTTON_GUILD_HALL],
            [self::BUTTON_MAP, self::BUTTON_DUNGEON]
        ], true, true, true);
    }
    
    /**
     * Клавиатура для подземелий
     */
    private function getDungeonKeyboard(): ReplyKeyboardMarkup
    {
        return new ReplyKeyboardMarkup([
            [self::BUTTON_CHARACTER, self::BUTTON_USER, self::BUTTON_INVENTORY],
            [self::BUTTON_FIGHT, self::BUTTON_EXIT_DUNGEON]
        ], true, true, true);
    }
    
    /**
     * Клавиатура для мировой карты
     */
    private function getWorldKeyboard(): ReplyKeyboardMarkup
    {
        return new ReplyKeyboardMarkup([
            [self::BUTTON_CHARACTER, self::BUTTON_USER, self::BUTTON_INVENTORY],
            [self::BUTTON_FIGHT, self::BUTTON_MAP]
        ], true, true, true);
    }
    
    /**
     * Клавиатура по умолчанию
     */
    private function getDefaultKeyboard(): ReplyKeyboardMarkup
    {
        return new ReplyKeyboardMarkup([
            [self::BUTTON_CHARACTER, self::BUTTON_USER, self::BUTTON_INVENTORY],
            [self::BUTTON_EXPLORE, self::BUTTON_FIGHT],
            [self::BUTTON_SHOP, self::BUTTON_HELP]
        ], true, true, true);
    }
    
    /**
     * Проверяет, доступна ли кнопка в текущей локации
     */
    public function isButtonAvailableInLocation(string $buttonText, Location $location): bool
    {
        $availableButtons = $this->getAvailableButtonsForLocation($location);
        return in_array($buttonText, $availableButtons);
    }
    
    /**
     * Возвращает список доступных кнопок для локации
     */
    public function getAvailableButtonsForLocation(Location $location): array
    {
        $locationType = $location->getType();
        
        // Базовые кнопки, доступные во всех локациях
        $buttons = [
            self::BUTTON_CHARACTER,
            self::BUTTON_USER,
            self::BUTTON_INVENTORY
        ];
        
        // Добавляем кнопки в зависимости от типа локации
        switch ($locationType) {
            case 'city':
                $buttons = array_merge($buttons, [
                    self::BUTTON_SHOP,
                    self::BUTTON_MARKET,
                    self::BUTTON_HOUSE,
                    self::BUTTON_GUILD_HALL,
                    self::BUTTON_MAP,
                    self::BUTTON_DUNGEON
                ]);
                break;
                
            case 'dungeon':
                $buttons = array_merge($buttons, [
                    self::BUTTON_FIGHT,
                    self::BUTTON_EXIT_DUNGEON
                ]);
                break;
                
            case 'world':
                $buttons = array_merge($buttons, [
                    self::BUTTON_FIGHT,
                    self::BUTTON_MAP
                ]);
                break;
                
            default:
                $buttons = array_merge($buttons, [
                    self::BUTTON_EXPLORE,
                    self::BUTTON_FIGHT,
                    self::BUTTON_SHOP,
                    self::BUTTON_HELP
                ]);
        }
        
        return $buttons;
    }
}
