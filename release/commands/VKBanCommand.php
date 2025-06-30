<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/error.log');
require_once __DIR__.'/../config.php';
require_once __DIR__.'/../Rcon.php';

class VKBanCommand {
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
        $cmd = "vkban";
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
        if (count($parts) < 3) {
            $this->sendMessage(
                EMOJIS['blocked'] . " | Использование: " . BOT_SETTINGS['bot_prefix'] . "vkban [ник] [время_в_часах] [причина]"
            );
            return;
        }

        $username = strtolower(trim($parts[0]));
        $hours = (int)$parts[1];
        $reason = implode(' ', array_slice($parts, 2));
        $protectedAdmins = PROTECTED_ADMINS;
        
        if (in_array($username, $protectedAdmins)) {
            $this->sendMessage(EMOJIS['crown'] . " | Вы не можете взаимодействовать с этим ником.");
            return;
        }

        // Проверка валидности времени
        if ($hours <= 0) {
            $this->sendMessage(EMOJIS['blocked'] . " | Некорректное время бана! Укажите число больше 0.");
            return;
        }

        // Проверка существования игрока
        if (!$this->botDB->playerExists($username)) {
            $this->sendMessage(EMOJIS['blocked'] . " | Игрок $username не привязан к консоли ВК.");
            return;
        }

        // Получаем ник администратора
        $adminAccount = $this->botDB->getSelectedAccount($this->user_id);
        if (!$adminAccount) {
            $this->sendMessage(EMOJIS['blocked'] . " | У вас не выбран аккаунт. Используйте команду !акк 2 [номер].");
            return;
        }

        // Рассчитываем время окончания бана
        $banEndTime = time() + ($hours * 3600);
        $banTime = date("Y-m-d H:i:s", $banEndTime);
        $formattedTime = date("d.m.Y H:i", $banEndTime);

        try {
            // Обновляем запись в базе данных
            $this->botDB->updateBan($username, $reason, $banTime, $adminAccount);

            // Отправляем broadcast на сервер
            $this->broadcastBan($username, $adminAccount, $hours, $formattedTime, $reason);

            // Отправляем сообщение в ВК пользователю
            $this->sendBanMessageToUser($username, $adminAccount, $hours, $formattedTime, $reason);

            $this->sendMessage(EMOJIS['galochka'] . " | Игрок $username успешно заблокирован!");
        } catch (Exception $e) {
            $this->sendMessage(EMOJIS['blocked'] . " | Ошибка: " . $e->getMessage());
        }
    }
/**/
    private function broadcastBan(string $username, string $adminAccount, int $hours, string $formattedTime, string $reason): void {
        $message = "§f> §cVKBAN §fИгрок §a{$username} §fбыл лишён донат.возможностей и забанен в ВК консоли.\n" .
                   "§f> §fИгроком: §b{$adminAccount}\n" .
                   "§f> §fВремя: §6{$hours} §fчасов (§6до {$formattedTime}§f)\n" .
                   "§f> §fПричина: §e{$reason}";

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

    private function sendBanMessageToUser(string $username, string $adminAccount, int $hours, string $formattedTime, string $reason): void {
        // Получаем VK ID пользователя
        $vkId = $this->botDB->getVkIdByNickname($username);
        if (!$vkId) return;

        $message = "⛓️ | Вы ($username) были заблокированы в ВК консоли сервера а также лишены своих донатерских возможностей.\n" .
                   "~ Игроком: $adminAccount\n" .
                   "~ Время: $hours часов (до $formattedTime)\n" .
                   "~ Причина: $reason";

        $this->APIshka->sendMessage($vkId, $message);
    }

    private function sendMessage(string $message): void {
        $this->APIshka->sendMessage($this->user_id, $message);
    }
}