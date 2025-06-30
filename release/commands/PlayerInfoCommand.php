<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/error.log');
require_once __DIR__.'/../config.php';

class PlayerInfoCommand {
    private $pdo;
    private $botDB;
    private $serverDB;
    private $APIshka;
    private $user_id;
    private $message_text;

    public function __construct($pdo, $botDB, $serverDB, $APIshka, $user_id, $message_text) {
        $this->pdo = $pdo;
        $this->botDB = $botDB;
        $this->serverDB = $serverDB;
        $this->APIshka = $APIshka;
        $this->user_id = $user_id;
        $this->message_text = $message_text;
    }

    public function execute() {
        // Извлечение никнейма из команды
        $username = $this->getUsernameFromMessage();
        
        $selec_acc = $this->botDB->getSelectedAccount($this->user_id);
        $rank = $this->botDB->getPlayerRank($selec_acc);
        $cmd = "player_info";
        if (!$this->botDB->hasPermissions($selec_acc, $rank, $cmd)) {
            $this->sendMessage(EMOJIS['blocked'] . " | У вас недостаточно прав для выполнения этой команды.");
            return;
        }
        
        if (!$username) {
            $this->sendMessage(EMOJIS['blocked'] . " | Неверный формат.\nИспользуйте: " . BOT_SETTINGS['bot_prefix'] . "player-info [ник]");
            return;
        }

        // Получение данных о целевом игроке
        $playerData = $this->getPlayerData($username);
        if (!$playerData) {
            $this->sendMessage(EMOJIS['blocked'] . " | Никнейм '$username' не привязан к ВК.\n");
            return;
        }

        // Формирование и отправка сообщения
        $message = $this->formatPlayerInfoMessage($playerData);
        $this->sendMessage($message);
    }

    private function getUsernameFromMessage(): ?string {
        $parts = explode(' ', $this->message_text, 2);
        return isset($parts[1]) ? trim($parts[1]) : null;
    }

    private function getPlayerData(string $username): ?array {
        try {
            // Получение основной информации
            $stmt = $this->pdo->prepare(
                "SELECT vk_id, link 
                FROM vk_links 
                WHERE username = ? AND link = 'YES'"
            );
            $stmt->execute([$username]);
            $userData = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$userData) return null;

            // Получение RCON данных
            $rconData = $this->getRconData($username);

            // Получение игрового времени
            $playtime = $this->serverDB->getPlaytime($username);

            // Получение привязанных аккаунтов
            $accounts = $this->botDB->getLinkedAccounts($userData['vk_id']);

            return [
                'username' => $username,
                'vk_id' => $userData['vk_id'],
                'rcon' => $rconData,
                'playtime' => $playtime,
                'accounts' => $accounts,
                'is_admin' => in_array($userData['vk_id'], ADMINS)
            ];
        } catch (PDOException $e) {
            error_log("Player data error: " . $e->getMessage());
            return null;
        }
    }

    private function getRconData(string $username): array {
        $stmt = $this->pdo->prepare(
            "SELECT `rank`, banned, ban_reason, ban_time 
            FROM vk_rcon 
            WHERE nickname = ?"
        );
        $stmt->execute([$username]);
        $data = $stmt->fetch(PDO::FETCH_ASSOC);

        return $data ?: [
            'rank' => 'Нету',
            'banned' => 'NO',
            'ban_reason' => 'Нету',
            'ban_time' => 'Нету'
        ];
    }
/**/
    private function formatPlayerInfoMessage(array $playerData): string {
        $username = $playerData['username'];
        $vk_id = $playerData['vk_id'];
        $rcon = $playerData['rcon'];
        $playtime = $playerData['playtime'];
        $authInfo = $this->serverDB->getAuthInfo($username);
        $lastDate = $this->serverDB->getLastDate($username);
        $accounts = $playerData['accounts'];

        $adminBadge = $playerData['is_admin'] ? EMOJIS['crown'] . " " : "";
        $adminNote = $playerData['is_admin'] ? "\n\n" . EMOJIS['crown'] . " - Администратор Бота" : "";

        return 
            EMOJIS['bymaga2'] . " | Информация по нику: $username\n\n" .
            EMOJIS['newbie'] . " | VK ID: [id$vk_id|$vk_id] $adminBadge\n" .
            EMOJIS['keychik'] . " | Доступ: {$rcon['rank']}\n" .
            EMOJIS['joystick'] . " | Всего наиграно: {$playtime['hours']} ч. {$playtime['minutes']} м.\n" .
            EMOJIS['link'] . " | Привязанные аккаунты: " . implode(', ', $accounts) . "\n" .
            EMOJIS['zamochek_i_key'] . " | Последний вход: \n ~ Дата - {$lastDate} \n ~ IP - {$authInfo['last_ip']} \n ~ Порт - {$authInfo['last_port']}\n" . 
            EMOJIS['chepi'] . " | Бан в консоли:\n" .
            " ~ Забанен: {$rcon['banned']}\n" .
            " ~ Причина: {$rcon['ban_reason']}\n" .
            " ~ Длительность: до {$rcon['ban_time']} MSK" .
            $adminNote;
    }

    private function sendMessage(string $message, array $params = []): void {
        $this->APIshka->sendMessage($this->user_id, $message, $params);
    }
}