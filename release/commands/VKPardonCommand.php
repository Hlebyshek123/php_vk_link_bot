<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/error.log');
require_once __DIR__.'/../config.php';
require_once __DIR__.'/../Rcon.php';

class VkPardonCommand {
    private $pdo;
    private $botDB;
    private $APIshka;
    private $user_id;
    private $message_text;

    public function __construct($pdo, $botDB, $APIshka, $user_id, $message_text) {
        $this->pdo = $pdo;
        $this->botDB = $botDB;
        $this->APIshka = $APIshka;
        $this->user_id = $user_id;
        $this->message_text = $message_text;
    }

    public function execute() {
        // Проверка прав
        $selec_acc = $this->botDB->getSelectedAccount($this->user_id);
        $rank = $this->botDB->getPlayerRank($selec_acc);
        $cmd = "vkpardon";
        if (!$this->botDB->hasPermissions($selec_acc, $rank, $cmd)) {
            $this->sendMessage(EMOJIS['blocked'] . " | У вас недостаточно прав для выполнения этой команды.");
            return;
        }
        
        if ($this->botDB->isPlayerBanned($selec_acc)) {
            $this->sendMessage(EMOJIS['chepi'] . " | Вы заблокированы в консоли сервера. Команда более недоступна.");
            return;
        }

        // Парсинг аргументов
        $parts = explode(' ', $this->message_text);
        array_shift($parts); // Удаляем саму команду

        // Проверка количества аргументов
        if (count($parts) < 1) {
            $this->sendMessage(
                EMOJIS['blocked'] . " | Использование: " . BOT_SETTINGS['bot_prefix'] . "vkpardon [ник]"
            );
            return;
        }

        $username = strtolower(trim($parts[0]));

        // Получаем ник администратора
        $adminAccount = $this->botDB->getSelectedAccount($this->user_id);
        if (!$adminAccount) {
            $this->sendMessage(EMOJIS['blocked'] . " | У вас не выбран аккаунт. Используйте команду !акк 2 [номер].");
            return;
        }

        try {
            // Проверяем, забанен ли игрок
            if (!$this->botDB->isPlayerBanned($username)) {
                $this->sendMessage(EMOJIS['blocked'] . " | Игрок $username не забанен в ВК консоли.");
                return;
            }

            // Снимаем бан
            $this->botDB->removeBan($username);

            // Отправляем broadcast на сервер
            $this->broadcastPardon($username, $adminAccount);

            // Отправляем сообщение в ВК пользователю
            $this->sendPardonMessageToUser($username, $adminAccount);

            $this->sendMessage(EMOJIS['galochka'] . " | Игрок $username успешно разблокирован!");
        } catch (Exception $e) {
            $this->sendMessage(EMOJIS['blocked'] . " | Ошибка: " . $e->getMessage());
        }
    }
/**/
    private function broadcastPardon(string $username, string $adminAccount): void {
        $message = "§f> §aVKPARDON §fИгрок §a{$username} §fбыл досрочно разблокирован в ВК консоли.\n" .
                   "§f> §fИгроком: §b{$adminAccount}\n";

        // Отправляем broadcast на все сервера
        foreach (SERVERS as $serverName => $serverConfig) {
            $this->sendRconCommand($serverName, "vksay " . $this->escapeRconMessage($message));
        }
    }

    private function escapeRconMessage(string $message): string {
        // Экранируем специальные символы для RCON
        return str_replace(['"', "'"], '', $message);
    }

    private function sendRconCommand(string $serverName, string $command): void {
        $server = SERVERS[$serverName];
        try {
            $rcon = new Rcon(
                $server['rcon_host'],
                $server['rcon_port'],
                $server['rcon_password'],
                3
            );

            if ($rcon->connect()) {
                $rcon->sendCommand($command);
                $rcon->disconnect();
            }
        } catch (Exception $e) {
            error_log("RCON broadcast error on $serverName: " . $e->getMessage());
        }
    }

    private function sendPardonMessageToUser(string $username, string $adminAccount): void {
        // Получаем VK ID пользователя
        $vkId = $this->botDB->getVkIdByNickname($username);
        if (!$vkId) return;

        $message = EMOJIS['galochka'] . " | Ваша блокировка в ВК консоли была досрочно снята!\n" .
                   "~ Игроком: $adminAccount\n" .
                   "~ Ваши донат.возможности восстановлены\n" .
                   "~ Приятной игры на сервере!";

        $this->APIshka->sendMessage($vkId, $message);
    }

    private function sendMessage(string $message): void {
        $this->APIshka->sendMessage($this->user_id, $message);
    }
}