<?php

namespace App\Service;

class GameTextService
{
    // Character creation texts
    public const CLASS_SELECTION_TITLE = '🧙 *Choose a class for your character:*';
    public const CLASS_NOT_FOUND = '⚠️ Class "%s" not found. Please choose a class from the options provided.';
    public const CLASS_SELECTED = '📜 *You have chosen the class: %s*

Base stats:
%s

Confirm your choice or go back to class selection.';
    
    public const GENDER_SELECTION_TITLE = '👤 *Choose your character\'s gender:*';
    public const GENDER_SELECTED = '👤 *You have chosen: %s*

Confirm your choice or go back to gender selection.';
    
    public const NAME_INPUT_REQUEST = '✏️ *Enter your character\'s name:*

The name must be 3-20 characters long and unique.';
    public const NAME_TOO_SHORT = '⚠️ The name is too short. It must be at least 3 characters long.';
    public const NAME_TOO_LONG = '⚠️ The name is too long. It must be no more than 20 characters long.';
    public const NAME_ALREADY_EXISTS = '⚠️ A character with this name already exists. Please choose another name.';
    
    public const CHARACTER_CONFIRMATION = '✨ *Character creation confirmation*

Class: %s
Gender: %s
Name: %s

Create this character?';
    
    public const CHARACTER_CREATED = '🎉 *Character created successfully!*

Welcome to Umbral Realms, %s!';
    
    // Button texts
    public const BUTTON_CONFIRM = '✅ Confirm';
    public const BUTTON_BACK = '⬅️ Back';
    public const BUTTON_YES = '✅ Yes';
    public const BUTTON_NO = '❌ No';
    public const BUTTON_MALE = '♂️ Male';
    public const BUTTON_FEMALE = '♀️ Female';
    
    // Error messages
    public const ERROR_CLASSES_NOT_FOUND = '⚠️ Error: Character classes not found.';
    public const UNKNOWN_COMMAND = 'Unknown command: %s. Use /help to get a list of available commands.';
    
    // Main menu
    public const MAIN_MENU_TITLE = '📋 *Main Menu*';
    public const MAIN_MENU_CHARACTER_INFO = '👤 *Character: %s*
Level: %d
Class: %s
Location: %s';
    
    // Help text
    public const HELP_TEXT = '📚 *Available Commands:*

/start - Start the game or show main menu
/character - Show character information
/inventory - Show your inventory
/help - Show this help message';
}
