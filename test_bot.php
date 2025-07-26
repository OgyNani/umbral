<?php

require_once __DIR__ . '/vendor/autoload.php';

use TelegramBot\Api\BotApi;
use TelegramBot\Api\Types\Update;

// Загружаем переменные окружения из .env.docker
$envFile = __DIR__ . '/.env.docker';
if (file_exists($envFile)) {
    $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (strpos($line, '=') !== false && strpos($line, '#') !== 0) {
            list($key, $value) = explode('=', $line, 2);
            $_ENV[trim($key)] = trim($value);
        }
    }
}

// Получаем токен бота из переменных окружения
$botToken = $_ENV['TELEGRAM_BOT_TOKEN'] ?? null;

if (!$botToken) {
    die("Ошибка: Токен бота не найден в .env.docker файле\n");
}

// Создаем экземпляр BotApi
$bot = new BotApi($botToken);

// Функция для отправки сообщения боту и получения ответа
function sendMessage($bot, $chatId, $text) {
    echo "Отправка сообщения: $text\n";
    try {
        $bot->sendMessage($chatId, $text);
        echo "Сообщение успешно отправлено!\n";
    } catch (\Exception $e) {
        echo "Ошибка при отправке сообщения: " . $e->getMessage() . "\n";
    }
}

// Получаем ID чата от пользователя
echo "Введите ID вашего чата в Telegram (можно узнать, отправив сообщение боту @userinfobot): ";
$chatId = trim(fgets(STDIN));

// Меню тестирования
while (true) {
    echo "\n=== Меню тестирования Telegram бота ===\n";
    echo "1. Отправить команду /start\n";
    echo "2. Отправить команду /help\n";
    echo "3. Отправить произвольное сообщение\n";
    echo "4. Выйти\n";
    echo "Выберите опцию: ";
    
    $option = trim(fgets(STDIN));
    
    switch ($option) {
        case '1':
            sendMessage($bot, $chatId, '/start');
            break;
        case '2':
            sendMessage($bot, $chatId, '/help');
            break;
        case '3':
            echo "Введите сообщение: ";
            $message = trim(fgets(STDIN));
            sendMessage($bot, $chatId, $message);
            break;
        case '4':
            echo "Выход из программы.\n";
            exit(0);
        default:
            echo "Неверная опция. Попробуйте снова.\n";
    }
}
