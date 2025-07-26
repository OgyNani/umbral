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
use App\Service\GameTextService as Text;
use Psr\Log\LoggerInterface;

class TelegramController extends AbstractController
{
    private BotApi $botApi;
    private CharacterCreationService $characterCreationService;
    private ButtonService $buttonService;
    private ButtonHandlerService $buttonHandlerService;
    private LocationService $locationService;
    private LoggerInterface $logger;

    public function __construct(
        CharacterCreationService $characterCreationService, 
        ButtonService $buttonService, 
        ButtonHandlerService $buttonHandlerService, 
        LocationService $locationService,
        LoggerInterface $logger
    ) {
        $this->botApi = new BotApi($_ENV['TELEGRAM_BOT_TOKEN']);
        $this->characterCreationService = $characterCreationService;
        $this->buttonService = $buttonService;
        $this->buttonHandlerService = $buttonHandlerService;
        $this->locationService = $locationService;
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
        $username = $message['from']['username'] ?? null;
        $firstName = $message['from']['first_name'] ?? null;
        
        $this->logger->info(sprintf('Processing message: [%s] from chat_id: %d, username: %s', $text, $chatId, $username));

        if ($text === '/start') {
            $this->logger->info('Received /start command. Starting character creation.');
            // Handle /start command - begin interaction with the bot
            $this->characterCreationService->startCharacterCreation($chatId, $username, $firstName);
            $this->logger->info('Character creation process started');
        } else {
            // Check if the user is in the character creation process
            if ($this->characterCreationService->isWaitingForName($chatId)) {
                // If waiting for name input, handle it through handleNameInput
                $this->characterCreationService->handleNameInput($chatId, $text);
            } else {
                // Try to process the message in the character creation flow
                $handled = $this->characterCreationService->handleCharacterCreationMessage($chatId, $text);
                
                // If the message was not handled in the character creation process, check if it's a button command
                if (!$handled) {
                    // Get the active character and its location
                    $character = $this->characterCreationService->getActiveCharacter($chatId);
                    
                    if ($character) {
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
                    } else {
                        // No active character, send unknown command message
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
        
        $this->logger->info(sprintf('Processing callback query: %s from chat_id: %d', $callbackData, $chatId));
        
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
