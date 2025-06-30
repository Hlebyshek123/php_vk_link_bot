<?php
date_default_timezone_set("Europe/Moscow");
require_once("error_handler.php");
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/error.log');
require_once __DIR__.'/vendor/autoload.php';
require_once __DIR__.'/config.php';

// Ранняя проверка секретного ключа
$input = file_get_contents('php://input');
$data = json_decode($input, true) ?? [];

if(!isset($data['secret']) || $data['secret'] !== SECRET_KEY) {
    http_response_code(403);
    exit('Invalid secret key');
}

// Логирование только для отладочных целей
if (BOT_SETTINGS['debug_logging'] ?? false) {
    $logDir = __DIR__ . '/tests/';
    if (!is_dir($logDir)) mkdir($logDir, 0755, true);
    file_put_contents(
        $logDir . 'requests.log',
        date('Y-m-d H:i:s') . " Headers:\n" . print_r(getallheaders(), true) . "\nBody: " . $input . "\n\n",
        FILE_APPEND
    );
}

// Обработка подтверждения до любых других операций
if (($data['type'] ?? '') === 'confirmation') {
    die((($data['group_id'] ?? 0) == GROUP_ID) ? CONFIRMATION_TOKEN : 'Group not found');
}

// Ленивая загрузка остальных зависимостей
require_once __DIR__.'/mysql/BotDatabase.php';
require_once __DIR__.'/mysql/ServerDatabase.php';
require_once __DIR__.'/API/VKGOIDA.php';
use VK\Client\VKApiClient;

// Инициализация только при необходимости
$vkAPIshka = null;
$botDB = null;
$serverDB = null;

try {
    switch ($data['type'] ?? 'unknown') {
        case 'message_new':
            $event = $data['object']['message'] ?? [];
            $peer_id = $event['peer_id'] ?? 0;
            
            // Ранний выход для групповых чатов
            if ($peer_id >= 2000000000) {
                exit('ok');
            }
            
            $user_id = $event['from_id'] ?? 0;
            $message_text = $event['text'] ?? '';
            $message_date = $event['date'] ?? 0;
            
            // Проверка возраста сообщения перед любой обработкой
            $max_age = BOT_SETTINGS['max_message_age'] ?? 86400;
            if ($message_date < (BOT_SETTINGS['start_time'] ?? 0) || (time() - $message_date) > $max_age) {
                exit('ok');
            }
            
            // Ленивая инициализация VK API
            if ($vkAPIshka === null) {
                $vk = new VKApiClient();
                $vkAPIshka = new VKGOIDA($vk, VK_API_TOKEN, GROUP_ID);
            }
            
            // Ленивая инициализация баз данных
            if ($botDB === null) {
                $botDB = new BotDatabase(DATABASE["bot"]);
                $serverDB = new ServerDatabase(DATABASE["server"]);
                $pdo = $botDB->getConnection();
                $pdo2 = $serverDB->getConnection();
            }
            
            // Обработка команд с префиксом бота
            if (str_starts_with($message_text, BOT_SETTINGS['bot_prefix'])) {
                $command = substr($message_text, strlen(BOT_SETTINGS['bot_prefix']));
                $command = strtok($command, " ");
                
                switch ($command) {
                    case 'привязка':
                        require_once __DIR__.'/commands/BindCommand.php';
                        (new BindCommand($pdo, $pdo2, $botDB, $serverDB, $vkAPIshka, $user_id, $message_text))->execute();
                        break;
                    case 'отвязка':
                        require_once __DIR__ . '/commands/UnbindCommand.php';
                        (new UnbindCommand($pdo, $pdo2, $botDB, $serverDB, $vkAPIshka, $user_id, $message_text))->execute();
                        break;
                    case 'помощь':
                        require_once __DIR__ . '/commands/HelpCommand.php';
                        (new HelpCommand($pdo, $botDB, $serverDB, $vkAPIshka, $user_id, $message_text))->execute();
                        break;
                    case 'акк':
                        require_once __DIR__ . '/commands/AccountCommand.php';
                        (new AccountCommand($pdo, $botDB, $serverDB, $vkAPIshka, $user_id, $message_text))->execute();
                        break;
                    case 'player-info':
                        require_once __DIR__ . '/commands/PlayerInfoCommand.php';
                        (new PlayerInfoCommand($pdo, $botDB, $serverDB, $vkAPIshka, $user_id, $message_text))->execute();
                        break;
                    case 'vkban':
                        require_once __DIR__ . '/commands/VKBanCommand.php';
                        (new VKBanCommand($pdo, $botDB, $vkAPIshka, $user_id, $message_text))->execute();
                        break;
                    case 'vkpardon':
                        require_once __DIR__ . '/commands/VKPardonCommand.php';
                        (new VKPardonCommand($pdo, $botDB, $vkAPIshka, $user_id, $message_text))->execute();
                        break;
                    case 'user-list':
                        require_once __DIR__ . '/commands/UserListCommand.php';
                        (new UserListCommand($pdo, $botDB, $vkAPIshka, $user_id, $message_text))->execute();
                        break;
                }
            }
            // Обработка команд с префиксом управления
            else if (str_starts_with($message_text, BOT_SETTINGS['manage_prefix'])) {
                $command = substr($message_text, strlen(BOT_SETTINGS['manage_prefix']));
                $command = strtok($command, " ");
                
                switch ($command) {
                    case 'рассылка':
                        require_once __DIR__ . '/commands/BroadCastCommand.php';
                        (new BroadCastCommand($pdo, $vkAPIshka, $user_id, $message_text))->execute();
                        break;
                    case 'sg':
                        require_once __DIR__ . '/commands/SetGroupCommand.php';
                        (new SetGroupCommand($pdo, $vkAPIshka, $user_id, $message_text))->execute();
                        break;
                    case 'pardon-all':
                        require_once __DIR__ . '/commands/VKPardonAllCommand.php';
                        (new VKPardonAllCommand($pdo, $botDB, $vkAPIshka, $user_id, $message_text))->execute();
                        break;
                }
            }
            // Обработка RCON команд
            else if (str_starts_with($message_text, BOT_SETTINGS['rcon_prefix'])) {
                $command = substr($message_text, strlen(BOT_SETTINGS['rcon_prefix']));
                $command = strtok($command, " ");
                
                switch ($command) {
                    case 'rcon':
                        require_once __DIR__ . '/commands/RconCommand.php';
                        (new RconCommand($pdo, $botDB, $vkAPIshka, $user_id, $message_text))->execute();
                        break;
                }
            }
            
            echo 'ok';
            break;
            
        default:
            echo 'ok';
            break;
    }
} catch (Throwable $e) {
    error_log("Error: " . $e->getMessage() . "\n" . $e->getTraceAsString());
    http_response_code(500);
    exit('Internal Server Error 500');
}