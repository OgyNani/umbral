<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use TelegramBot\Api\BotApi;
use TelegramBot\Api\Types\Update;
use App\Service\CharacterCreationService;
use App\Service\ButtonService;
use App\Service\ButtonHandlerService;
use App\Service\LocationService;
use App\Service\CombatService;
use App\Service\GameTextService as Text;
use Psr\Log\LoggerInterface;

class TelegramController extends AbstractController
{
    private BotApi $botApi;
    private CharacterCreationService $characterCreationService;
    private ButtonService $buttonService;
    private ButtonHandlerService $buttonHandlerService;
    private LocationService $locationService;
    private CombatService $combatService;
    private LoggerInterface $logger;

    public function __construct(
        CharacterCreationService $characterCreationService, 
        ButtonService $buttonService, 
        ButtonHandlerService $buttonHandlerService, 
        LocationService $locationService,
        CombatService $combatService,
        LoggerInterface $logger
    ) {
        $this->botApi = new BotApi($_ENV['TELEGRAM_BOT_TOKEN']);
        $this->characterCreationService = $characterCreationService;
        $this->buttonService = $buttonService;
        $this->buttonHandlerService = $buttonHandlerService;
        $this->locationService = $locationService;
        $this->combatService = $combatService;
        $this->logger = $logger;
    }

    #[Route('/webhook', name: 'telegram_webhook', methods: ['POST'])]
    public function webhook(Request $request): Response
    {        
        $content = $request->getContent();
        $this->logger->info('Received webhook data: ' . $content);
        
        $update = json_decode($content, true);
        $this->logger->info('Update structure: ' . json_encode($update));
        
        if (isset($update['message'])) {
            $this->logger->info('Processing message update: ' . json_encode($update['message']));
            $this->handleMessage($update);
        } elseif (isset($update['callback_query'])) {
            $this->logger->info('Processing callback_query update: ' . json_encode($update['callback_query']));
            $this->handleCallbackQuery($update);
        } else {
            $this->logger->warning('Received update with unknown structure: ' . json_encode($update));
        }

        return new Response('', Response::HTTP_OK);
    }

    private function handleMessage(array $update): void
    {
        $message = $update['message'];
        $chatId = $message['chat']['id'];
        $text = $message['text'] ?? '';
        
        // При каждом запросе проверяем, не завершился ли процесс сбора ресурсов
        $this->buttonHandlerService->checkGatheringCompletion($chatId);
        $username = $message['from']['username'] ?? null;
        $firstName = $message['from']['first_name'] ?? null;
        
        // Детальное логирование для отладки
        $this->logger->info(sprintf('Processing message: [%s] from chat_id: %d, username: %s', $text, $chatId, $username));
        $this->logger->info('Raw message text: "' . $text . '"');
        $this->logger->info('Message text length: ' . strlen($text));
        $this->logger->info('Message text hex: ' . bin2hex($text));

        if ($text === '/start') {
            $this->logger->info('Received /start command. Starting character creation.');
            // Handle /start command - begin interaction with the bot
            $this->characterCreationService->startCharacterCreation($chatId, $username, $firstName);
            $this->logger->info('Character creation process started');
        } else {
            // Check if the user is in the character creation process
            $this->logger->info('Checking if user is in character creation process');
            
            // Проверяем, находится ли пользователь в процессе создания персонажа и ожидает ли ввода имени
            $isWaitingForName = $this->characterCreationService->isWaitingForName($chatId);
            $this->logger->info('Is waiting for name: ' . ($isWaitingForName ? 'true' : 'false'));
            
            if ($isWaitingForName) {
                // If waiting for name input, handle it through handleNameInput
                $this->logger->info('User is waiting for name input, handling: ' . $text);
                $this->characterCreationService->handleNameInput($chatId, $text);
            } else {
                // Try to process the message in the character creation flow
                $this->logger->info('Attempting to handle message in character creation flow: ' . $text);
                $handled = $this->characterCreationService->handleCharacterCreationMessage($chatId, $text);
                $this->logger->info('Character creation message handled: ' . ($handled ? 'true' : 'false'));
                
                // If the message was not handled in the character creation process, check if it's a combat-related message
                if (!$handled) {
                    // Check if it's a body part selection for combat (case-insensitive)
                    if (in_array(strtolower($text), array_map('strtolower', CombatService::BODY_PARTS)) || $text === '❌ Cancel Search') {
                        $character = $this->characterCreationService->getActiveCharacter($chatId);
                        if ($character) {
                            $this->buttonHandlerService->handleButtonCommand($chatId, $text, $character);
                            $handled = true;
                        }
                    }
                }
                
                // If not handled by character creation or combat, check if it's a regular button command
                if (!$handled) {
                    // Get the active character and its location
                    $character = $this->characterCreationService->getActiveCharacter($chatId);
                    
                    if ($character) {
                        // Убрано автоматическое исцеление при каждом сообщении
                        // Теперь хил происходит только через фоновый воркер Messenger

                        // Проверяем кнопку Back специально, чтобы она работала всегда
                        if ($text === ButtonService::BUTTON_BACK) {
                            $this->buttonHandlerService->handleButtonCommand($chatId, $text, $character);
                        }
                        // Check if the button is available in the current location
                        else if ($this->buttonService->isButtonAvailableInLocation($text, $character->getLocation())) {
                            // Button is available, delegate handling to ButtonHandlerService
                            $this->buttonHandlerService->handleButtonCommand($chatId, $text, $character);
                        } else {
                            // Проверяем, является ли текст названием локации для перехода
                            if ($this->locationService->isAvailableLocationName($text, $character->getLocation())) {
                                $targetLocation = $this->locationService->getLocationByName($text);
                                if ($targetLocation) {
                                    $this->locationService->moveCharacterToLocation($character, $targetLocation, $chatId);
                                }
                            } else {
                                // Button is not available in this location
                                $this->botApi->sendMessage($chatId, "This action is not available in your current location.");
                            }
                        }
                    } else if ($this->combatService->isInCombat($chatId)) {
                        // If in combat, inform the user they can only use combat commands
                        $this->botApi->sendMessage($chatId, "You are in combat and can only use combat commands.");
                    } else {
                        // No active character, send unknown command message
                        $this->logger->info('No active character and no character creation in progress, sending unknown command message for: ' . $text);
                        $this->botApi->sendMessage($chatId, sprintf(Text::UNKNOWN_COMMAND, $text));
                    }
                }
            }
        }
    }

    private function handleCallbackQuery(array $update): void
    {
        $callbackQuery = $update['callback_query'];
        $callbackData = $callbackQuery['data'];
        $chatId = $callbackQuery['message']['chat']['id'];
        
        // При каждом запросе проверяем, не завершился ли процесс сбора ресурсов
        $this->buttonHandlerService->checkGatheringCompletion($chatId);
        
        $this->logger->info(sprintf('Processing callback query: %s from chat_id: %d', $callbackData, $chatId));
        
        // Check if the user is in combat
        if ($this->combatService->isInCombat($chatId)) {
            // Don't process other callbacks during combat
            $this->botApi->answerCallbackQuery($callbackQuery['id'], 'You are in combat and cannot perform this action.');
            return;
        }
        
        // Try to handle the callback in the character creation flow
        $handled = $this->characterCreationService->handleCharacterCreationCallback($chatId, $callbackData, $callbackQuery['id']);
        
        // If not handled, send a message that this callback is not supported
        if (!$handled) {
            $this->botApi->answerCallbackQuery($callbackQuery['id'], 'This callback is not supported.');
        }
    }
    


    #[Route('/set-webhook', name: 'set_webhook')]
    public function setWebhook(Request $request): Response
    {
        $url = $request->query->get('url');
        
        if (!$url) {
            $baseUrl = $request->getSchemeAndHttpHost();
            $url = $baseUrl . $this->generateUrl('telegram_webhook');
        }
        
        if (strpos($url, 'https://') !== 0) {
            $url = str_replace('http://', 'https://', $url);
            if (strpos($url, 'https://') !== 0) {
                $url = 'https://' . $url;
            }
        }
        
        try {
            $this->botApi->setWebhook($url);
            return new Response('Webhook успешно установлен на: ' . $url);
        } catch (\Exception $e) {
            return new Response('Ошибка при установке webhook: ' . $e->getMessage(), 500);
        }
    }
}
