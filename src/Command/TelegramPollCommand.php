<?php

namespace App\Command;

use App\Controller\TelegramController;
use App\Service\CharacterCreationService;
use App\Service\HealingService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\HttpFoundation\Request;
use TelegramBot\Api\BotApi;
use TelegramBot\Api\Exception;

#[AsCommand(
    name: 'app:telegram:poll',
    description: 'Poll Telegram API for updates and process them',
)]
class TelegramPollCommand extends Command
{
    private BotApi $botApi;
    private TelegramController $telegramController;
    private int $offset = 0;

    public function __construct(BotApi $botApi, TelegramController $telegramController)
    {
        parent::__construct();
        $this->botApi = $botApi;
        $this->telegramController = $telegramController;
    }
    
    protected function configure(): void
    {
        $this
            ->addOption('timeout', 't', InputOption::VALUE_OPTIONAL, 'Timeout for long polling in seconds', 30)
            ->addOption('limit', 'l', InputOption::VALUE_OPTIONAL, 'Maximum number of updates to retrieve', 100);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $io->success('Starting Telegram polling...');
        
        $timeout = (int)$input->getOption('timeout');
        $limit = (int)$input->getOption('limit');
        
        $io->info(sprintf('Using timeout: %d seconds, limit: %d updates', $timeout, $limit));
        
        // Проверим, что вебхук отключен
        try {
            $webhookInfo = $this->botApi->getWebhookInfo();
            if (!empty($webhookInfo->getUrl())) {
                $io->warning(sprintf('Webhook is active: %s. Trying to delete it...', $webhookInfo->getUrl()));
                $this->botApi->deleteWebhook();
                $io->success('Webhook deleted successfully');
            }
        } catch (Exception $e) {
            $io->error('Error checking webhook: ' . $e->getMessage());
            // Продолжаем работу, возможно, вебхук уже отключен
        }
        
        $retryCount = 0;
        $maxRetries = 5;
        
        while (true) {
            try {
                // Используем более короткий тайм-аут для предотвращения проблем
                $updates = $this->botApi->getUpdates($this->offset, $limit, $timeout);
                
                // Сбрасываем счетчик повторных попыток при успешном запросе
                $retryCount = 0;
                
                foreach ($updates as $update) {
                    $updateId = $update->getUpdateId();
                    $io->info(sprintf('Processing update ID: %d', $updateId));
                    
                    // Выводим информацию о типе обновления
                    if ($update->getMessage()) {
                        $message = $update->getMessage();
                        $io->info(sprintf('Message from %s (ID: %d): %s', 
                            $message->getFrom() ? $message->getFrom()->getUsername() : 'Unknown', 
                            $message->getChat()->getId(),
                            $message->getText() ?? 'No text'
                        ));
                    } elseif ($update->getCallbackQuery()) {
                        $callbackQuery = $update->getCallbackQuery();
                        $io->info(sprintf('Callback query from %s: %s', 
                            $callbackQuery->getFrom()->getUsername() ?? 'Unknown',
                            $callbackQuery->getData() ?? 'No data'
                        ));
                    } else {
                        $io->warning('Update contains neither message nor callback query');
                    }
                    
                    try {
                        // Создаем массив данных вручную из объекта Update
                        $updateArray = [];
                        $updateArray['update_id'] = $update->getUpdateId();
                        
                        if ($update->getMessage()) {
                            $message = $update->getMessage();
                            $updateArray['message'] = [
                                'message_id' => $message->getMessageId(),
                                'from' => [
                                    'id' => $message->getFrom() ? $message->getFrom()->getId() : null,
                                    'first_name' => $message->getFrom() ? $message->getFrom()->getFirstName() : null,
                                    'username' => $message->getFrom() ? $message->getFrom()->getUsername() : null,
                                ],
                                'chat' => [
                                    'id' => $message->getChat()->getId(),
                                    'type' => $message->getChat()->getType(),
                                ],
                                'date' => $message->getDate(),
                                'text' => $message->getText(),
                            ];
                        } elseif ($update->getCallbackQuery()) {
                            $callbackQuery = $update->getCallbackQuery();
                            $updateArray['callback_query'] = [
                                'id' => $callbackQuery->getId(),
                                'from' => [
                                    'id' => $callbackQuery->getFrom()->getId(),
                                    'first_name' => $callbackQuery->getFrom()->getFirstName(),
                                    'username' => $callbackQuery->getFrom()->getUsername(),
                                ],
                                'message' => [
                                    'message_id' => $callbackQuery->getMessage()->getMessageId(),
                                    'chat' => [
                                        'id' => $callbackQuery->getMessage()->getChat()->getId(),
                                    ],
                                ],
                                'data' => $callbackQuery->getData(),
                            ];
                        }
                        
                        $io->info('Update Array: ' . json_encode($updateArray));
                        
                        // Создаем Request объект с данными обновления в теле запроса
                        $content = json_encode($updateArray);
                        $request = Request::create(
                            '/webhook',
                            'POST',
                            [],
                            [],
                            [],
                            ['CONTENT_TYPE' => 'application/json'],
                            $content
                        );
                        
                        // Обрабатываем обновление через контроллер
                        $this->telegramController->webhook($request);
                        
                        $io->success(sprintf('Successfully processed update ID: %d', $updateId));
                    } catch (\Exception $e) {
                        $io->error(sprintf('Error processing update ID %d: %s', $updateId, $e->getMessage()));
                    }
                    
                    // Обновляем offset для получения следующих обновлений
                    $this->offset = $updateId + 1;
                }
                
                // Небольшая пауза между запросами, если не было обновлений
                if (empty($updates)) {
                    usleep(500000); // 0.5 секунды
                }
            } catch (Exception $e) {
                $retryCount++;
                $waitTime = min(5 * $retryCount, 30); // Увеличиваем время ожидания, но не более 30 секунд
                
                $io->error(sprintf('Error: %s', $e->getMessage()));
                $io->warning(sprintf('Retry %d/%d. Waiting %d seconds before next attempt...', 
                    $retryCount, $maxRetries, $waitTime));
                
                if ($retryCount >= $maxRetries) {
                    $io->error('Maximum retry count reached. Resetting retry counter and continuing...');
                    $retryCount = 0;
                }
                
                sleep($waitTime);
            }
        }

        return Command::SUCCESS;
    }
}
