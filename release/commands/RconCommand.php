<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/error.log');
require_once __DIR__.'/../config.php';
require_once __DIR__.'/../Rcon.php';

class RconCommand {
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
        $parts = explode(' ', $this->message_text);
        $subcommand = $parts[1] ?? null;

        // Проверка выбранного аккаунта
        $selected_account = $this->botDB->getSelectedAccount($this->user_id);
        if (!$selected_account) {
            $rank = '0';
            $this->sendMessage(EMOJIS['blocked'] . " | Вы не привязаны к боту для доступа к консоли.");
            return;
        } else {
            $rank = $this->botDB->getPlayerRank($selected_account);
        }
        $srv_shop = SRV_SHOP;
        
        // Если команда без аргументов - показываем текущие настройки
        if ($subcommand === null) {
            if (!$selected_account) {
                $this->sendMessage(EMOJIS['blocked'] . " | У вас не выбран аккаунт. Используйте команду !акк 2 [номер].");
                return;
            }
        
            if (!$rank) {
                $this->sendMessage(EMOJIS['computer'] . " | У вас нет доступа к консоли нашего сервера\n\n" . EMOJIS['voprosik'] . " | Что бы приобрести доступ, вы можете перейти по прямой ссылке на наш сайт авто-доната!\n\n" . EMOJIS['keychik'] . " | Ссылочка - {$srv_shop}");
                return;
            } else {
                $this->showCurrentSettings($selected_account);
                return;
            }
        }
        
        if (!$selected_account) {
            $this->sendMessage(EMOJIS['blocked'] . " | У вас не выбран аккаунт для выполнения команды.");
            return;
        }

        // Обработка подкоманд
        if ($subcommand === "1") {
            $this->ServerSelection($parts, $rank, $selected_account);
        } 
        elseif ($subcommand === "2") {
            $this->listAvailableServers($rank);
        }
        else {
            $this->RconCommand($parts, $rank, $selected_account);
        }
    }

    private function ServerSelection(array $parts, string $rank, string $selected_account) {
        if (count($parts) < 3) {
            $this->sendMessage(EMOJIS['blocked'] . " | Неверный формат. Используйте: " . BOT_SETTINGS['rcon_prefix'] . "rcon 1 [имя сервера].");
            return;
        }

        $server_name = trim($parts[2]);
        $allowed_servers = $this->getServerPermissions($rank);

        if (!in_array($server_name, $allowed_servers)) {
            $this->sendMessage(EMOJIS['blocked'] . " | Сервер $server_name недоступен для вашего ранга.");
            return;
        }

        $this->selectServer($server_name, $selected_account);
        $this->sendMessage(EMOJIS['computer'] . " | Вы успешно выбрали сервер $server_name с аккаунта $selected_account");
    }

    private function listAvailableServers(string $rank) {
        $allowed_servers = $this->getServerPermissions($rank);

        if (empty($allowed_servers)) {
            $this->sendMessage(EMOJIS['blocked'] . " | У вас нет доступа к серверам.");
            return;
        }

        $message = EMOJIS['computer'] . " | Доступные сервера:\n";
        foreach ($allowed_servers as $i => $server) {
            $message .= EMOJIS['blestki'] . " " . ($i + 1) . ". $server\n";
        }

        $this->sendMessage($message);
    }
    
    private function showCurrentSettings(?string $selected_account): void {
        $srv_name = SRV_NAME;
        $message = EMOJIS['computer'] . " | Консоль сервера $srv_name:\n\n";
        
        if ($selected_account) {
            $message .= EMOJIS['galochka'] . " | Выбранный аккаунт: $selected_account\n";
            // Получаем выбранный сервер
            $selected_server = $this->getSelectedServer($selected_account);
            if ($selected_server) {
                $message .= EMOJIS['galochka'] . " | Выбранный сервер: $selected_server\n";
            } else {
                $message .= EMOJIS['blocked'] . " | Сервер не выбран\n";
            }
            
            $message .= "\n" . EMOJIS['pismo_otpravka'] . " | Для использования консоли:\n";
            $message .= "~ /rcon 1 [сервер] - выбор сервера\n";
            $message .= "~ /rcon 2 - Список серверов\n";
            $message .= "~ /rcon [команда] - Выполнить команду.";
        }
        $this->sendMessage($message);
    }
    

    private function RconCommand(array $parts, string $rank, string $selected_account) {
        // Проверка бана перед выполнением команды
        $ban_data = $this->getBanData($selected_account);
        if ($ban_data && $ban_data['banned'] === 'YES') {
            $ban_time = strtotime($ban_data['ban_time']);
            $current_time = time();
            
            if ($current_time < $ban_time) {
                $this->sendBanMessage($selected_account, $ban_data);
                return;
            } else {
                $this->unbanPlayer($selected_account);
            }
        }

        // Получение команды и аргументов
        array_shift($parts); // Удаляем префикс команды
        $command = array_shift($parts);
        $args = $parts;

        // Проверка разрешений
        if (!$this->isCommandAllowed($rank, $command)) {
            $this->sendMessage(EMOJIS['blocked'] . " | У вас нет прав для выполнения этой команды.");
            return;
        }

        // Получение выбранного сервера
        $selected_server = $this->getSelectedServer($selected_account);
        if (!$selected_server) {
            $this->sendMessage(EMOJIS['blocked'] . " | Вы не выбрали сервер. Используйте: " . BOT_SETTINGS['rcon_prefix'] . "rcon 1 [сервер]");
            return;
        }

        // Выполнение команды
        $this->executeRconCommand($selected_server, $command, $args, $selected_account);
    }

    private function getBanData(string $username): ?array {
        try {
            $stmt = $this->pdo->prepare("SELECT banned, ban_reason, ban_time, ban_by FROM vk_rcon WHERE nickname = ?");
            $stmt->execute([$username]);
            return $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Ban data error: " . $e->getMessage());
            return null;
        }
    }

    private function sendBanMessage(string $username, array $ban_data): void {
        $ban_time = strtotime($ban_data['ban_time']);
        $this->sendMessage(
            EMOJIS['chepi'] . " | Вы ($username) были заблокированы в ВК консоли сервера а также лишены своих донатерских возможностей.\n" .
            "~ Игроком: {$ban_data['ban_by']}\n" .
            "~ Время: до " . date('Y-m-d H:i:s', $ban_time) . "\n" .
            "~ Причина: {$ban_data['ban_reason']}"
        );
    }

    private function unbanPlayer(string $username): void {
        try {
            $stmt = $this->pdo->prepare(
                "UPDATE vk_rcon 
                SET banned = 'NO', 
                    ban_reason = NULL, 
                    ban_time = NULL, 
                    ban_by = NULL 
                WHERE nickname = ?"
            );
            $stmt->execute([$username]);
            $this->sendMessage(EMOJIS['galochka'] . " | {$username}, ваша блокировка автоматически снята по истечению срока!");
        } catch (PDOException $e) {
            error_log("Unban error: " . $e->getMessage());
        }
    }

    private function getServerPermissions(string $rank): array {
        return SERV_PERMS[$rank] ?? [];
    }

    private function isCommandAllowed(string $rank, string $command): bool {
        $allowed_commands = RCON_RANKS[$rank] ?? [];
        return in_array($command, $allowed_commands) || in_array('*', $allowed_commands);
    }

    private function getSelectedServer(string $username): ?string {
        try {
            $stmt = $this->pdo->prepare("SELECT selected_server FROM vk_rcon WHERE nickname = ?");
            $stmt->execute([$username]);
            return $stmt->fetchColumn();
        } catch (PDOException $e) {
            error_log("Server selection error: " . $e->getMessage());
            return null;
        }
    }

    private function selectServer(string $server_name, string $username): void {
        try {
            $stmt = $this->pdo->prepare(
                "UPDATE vk_rcon SET selected_server = ? WHERE nickname = ?"
            );
            $stmt->execute([$server_name, $username]);
        } catch (PDOException $e) {
            error_log("Server select error: " . $e->getMessage());
        }
    }

    private function executeRconCommand(string $server_name, string $command, array $args, string $username) {
        if (!isset(SERVERS[$server_name])) {
            $this->sendMessage(EMOJIS['blocked'] . " | Сервер $server_name не найден.");
            return;
        }

        $server = SERVERS[$server_name];
        $selected_account = $this->botDB->getSelectedAccount($this->user_id);
        $full_command = $command . ' ' . implode(' ', $args);
        
        if (strtolower($command) === 'say') {
            $message = implode(' ', $args);
            $full_command = "say $message (by $username)";
        }
        
        try {
            $rcon = new Rcon(
                $server['rcon_host'],
                $server['rcon_port'],
                $server['rcon_password'],
                3
            );

            if (!$rcon->connect()) {
                $this->sendMessage(EMOJIS['blocked'] . " | Ошибка подключения к RCON: " . $rcon->getResponse());
                return;
            }

            $response = $rcon->sendCommand($full_command);
            $rcon->disconnect();
            $response = (string)$response;

            if ($response === "") {
                $this->sendMessage(
                    EMOJIS['galochka'] . " | Команда успешно выполнена на сервере {$server_name} (by {$selected_account})!\n\n" .
                    EMOJIS['pismo_otpravka'] . " | Сервер вернул пустой ответ"
                );
            } else {
                $this->sendMessage(
                    EMOJIS['galochka'] . " | Команда успешно выполнена на сервере {$server_name} (by {$selected_account}):\n\n" . EMOJIS['pismo_otpravka'] . " | " . $response
                );
            }
        } catch (Exception $e) {
            $this->sendMessage(EMOJIS['blocked'] . " | RCON ошибка: " . $e->getMessage());
        }
    }

    private function sendMessage(string $message): void {
        $this->APIshka->sendMessage($this->user_id, $message);
    }
}