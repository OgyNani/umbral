<?php

namespace App\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use TelegramBot\Api\BotApi;

class TelegramDeleteWebhookCommand extends Command
{
    protected static $defaultName = 'app:telegram:delete-webhook';
    protected static $defaultDescription = 'Delete the Telegram webhook';

    private BotApi $botApi;

    public function __construct(BotApi $botApi)
    {
        parent::__construct();
        $this->botApi = $botApi;
    }

    protected function configure(): void
    {
        $this->setDescription(self::$defaultDescription);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $io->title('Telegram Webhook Deletion');

        try {
            $result = $this->botApi->deleteWebhook();
            if ($result) {
                $io->success('Webhook successfully deleted.');
            } else {
                $io->error('Failed to delete webhook.');
                return Command::FAILURE;
            }
        } catch (\Exception $e) {
            $io->error('Error: ' . $e->getMessage());
            return Command::FAILURE;
        }

        return Command::SUCCESS;
    }
}
