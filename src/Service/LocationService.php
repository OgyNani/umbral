<?php

namespace App\Service;

use App\Entity\Character;
use App\Entity\Location;
use App\Repository\LocationRepository;
use TelegramBot\Api\BotApi;
use Psr\Log\LoggerInterface;
use Doctrine\ORM\EntityManagerInterface;

class LocationService
{
    private LocationRepository $locationRepository;
    private EntityManagerInterface $entityManager;
    private BotApi $botApi;
    private ButtonService $buttonService;
    private LoggerInterface $logger;
    
    public function __construct(
        LocationRepository $locationRepository,
        EntityManagerInterface $entityManager,
        BotApi $botApi,
        ButtonService $buttonService,
        LoggerInterface $logger
    ) {
        $this->locationRepository = $locationRepository;
        $this->entityManager = $entityManager;
        $this->botApi = $botApi;
        $this->buttonService = $buttonService;
        $this->logger = $logger;
    }
    
    /**
     * Получить список доступных локаций для перехода
     */
    public function getAvailableLocations(Location $currentLocation): array
    {
        $availableLocationIds = $currentLocation->getConnections();
        if (empty($availableLocationIds)) {
            return [];
        }
        
        return $this->locationRepository->findBy(['id' => $availableLocationIds]);
    }
    
    /**
     * Переместить персонажа в новую локацию
     */
    public function moveCharacterToLocation(Character $character, Location $targetLocation, int $chatId): bool
    {
        $currentLocation = $character->getLocation();
        
        // Проверяем, доступна ли локация для перехода
        if (!$currentLocation->isConnectionAvailable($targetLocation->getId())) {
            $this->botApi->sendMessage(
                $chatId, 
                sprintf("You cannot travel to %s from your current location.", $targetLocation->getName())
            );
            return false;
        }
        
        // Перемещаем персонажа в новую локацию
        $character->setLocation($targetLocation);
        $this->entityManager->persist($character);
        $this->entityManager->flush();
        
        // Отправляем сообщение о перемещении
        $this->botApi->sendMessage(
            $chatId,
            sprintf("You have traveled to %s.", $targetLocation->getName()),
            null,
            false,
            null,
            $this->buttonService->getKeyboardForLocation($targetLocation)
        );
        
        $this->logger->info(sprintf(
            'Character %s moved from %s to %s',
            $character->getName(),
            $currentLocation->getName(),
            $targetLocation->getName()
        ));
        
        return true;
    }
    
    /**
     * Получить клавиатуру с доступными локациями для перехода
     */
    public function getLocationSelectionKeyboard(Location $currentLocation): array
    {
        $availableLocations = $this->getAvailableLocations($currentLocation);
        $keyboard = [];
        
        foreach ($availableLocations as $location) {
            $keyboard[] = [$location->getName()];
        }
        
        // Добавляем кнопку "Назад"
        $keyboard[] = [ButtonService::BUTTON_BACK];
        
        return $keyboard;
    }
    
    /**
     * Проверить, является ли название локацией, доступной для перехода
     */
    public function isAvailableLocationName(string $locationName, Location $currentLocation): bool
    {
        $availableLocations = $this->getAvailableLocations($currentLocation);
        
        foreach ($availableLocations as $location) {
            if ($location->getName() === $locationName) {
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * Получить локацию по названию
     */
    public function getLocationByName(string $locationName): ?Location
    {
        return $this->locationRepository->findOneBy(['name' => $locationName]);
    }
    

}
