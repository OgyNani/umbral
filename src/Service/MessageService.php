<?php

namespace App\Service;

use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use TelegramBot\Api\BotApi;
use TelegramBot\Api\Types\ReplyKeyboardMarkup;
use TelegramBot\Api\Exception;

/**
 * Сервис для отправки сообщений в Telegram
 */
class MessageService
{
    private LoggerInterface $logger;
    private RequestStack $requestStack;
    private BotApi $botApi;
    
    public function __construct(
        LoggerInterface $logger,
        RequestStack $requestStack,
        string $telegramBotToken
    ) {
        $this->logger = $logger;
        $this->requestStack = $requestStack;
        $this->botApi = new BotApi($telegramBotToken);
    }
    
    /**
     * Отправляет сообщение в Telegram
     */
    public function sendMessage(
        int $chatId, 
        string $text, 
        ?ReplyKeyboardMarkup $keyboard = null,
        bool $disableWebPagePreview = true
    ): ?int {
        try {
            $this->logger->info('Sending message to Telegram', [
                'chat_id' => $chatId,
                'text_length' => strlen($text)
            ]);
            
            $message = $this->botApi->sendMessage(
                $chatId,
                $text,
                'HTML',
                $disableWebPagePreview,
                null,
                $keyboard
            );
            
            return $message->getMessageId();
        } catch (Exception $e) {
            $this->logger->error('Failed to send message to Telegram', [
                'chat_id' => $chatId,
                'error' => $e->getMessage()
            ]);
            
            return null;
        }
    }
    
    /**
     * Форматирует текст с HTML-тегами для Telegram
     */
    public function formatText(string $text): string
    {
        return $text;
    }
    
    /**
     * Отправляет ответ на запрос с колбэком
     */
    public function answerCallbackQuery(string $callbackQueryId, string $text = '', bool $showAlert = false): bool
    {
        try {
            $this->botApi->answerCallbackQuery($callbackQueryId, $text, $showAlert);
            return true;
        } catch (Exception $e) {
            $this->logger->error('Failed to answer callback query', [
                'callback_query_id' => $callbackQueryId,
                'error' => $e->getMessage()
            ]);
            
            return false;
        }
    }
    
    /**
     * Редактирует ранее отправленное сообщение
     */
    public function editMessageText(
        int $chatId,
        int $messageId,
        string $text,
        ?ReplyKeyboardMarkup $keyboard = null,
        bool $disableWebPagePreview = true
    ): bool {
        try {
            $this->botApi->editMessageText(
                $chatId,
                $messageId,
                $text,
                'HTML',
                $disableWebPagePreview,
                $keyboard
            );
            
            return true;
        } catch (Exception $e) {
            $this->logger->error('Failed to edit message', [
                'chat_id' => $chatId,
                'message_id' => $messageId,
                'error' => $e->getMessage()
            ]);
            
            return false;
        }
    }
}
