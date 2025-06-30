<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/error.log');
require_once __DIR__.'/../config.php';

class SetGroupCommand {
    private $pdo;
    private $APIshka;
    private $user_id;
    private $message_text;

    public function __construct($pdo, $APIshka, $user_id, $message_text) {
        $this->pdo = $pdo;
        $this->APIshka = $APIshka;
        $this->user_id = $user_id;
        $this->message_text = $message_text;
    }

    public function execute() {
        // Проверка прав администратора
        if (!in_array($this->user_id, ADMINS)) {
            $this->sendMessage(EMOJIS['blocked'] . " | Увы но вы явно не администратор извиняйте.");
            return;
        }

        // Парсинг аргументов
        $parts = explode(' ', $this->message_text, 3);
        if (count($parts) < 3) {
            $this->sendMessage(EMOJIS['blocked'] . " | Неверный формат команды.\n Используйте: " . BOT_SETTINGS['manage_prefix'] . "sg [ник] [доступ]");
            return;
        }

        $username = strtolower(trim($parts[1]));
        $protectedAdmins = PROTECTED_ADMINS;
        $rank = trim($parts[2]);

        // Проверка валидности ранга
        
        if (!in_array($rank, BOT_RANKS['valid_ranks'])) {
            $this->sendMessage(
                EMOJIS['blocked'] . " | Неверный доступ.\n" .
                EMOJIS['crown'] . " | Доступные доступы: " . implode(', ', BOT_RANKS['valid_ranks'])
            );
            return;
        }
        
        if (in_array($username, $protectedAdmins)) {
            $this->sendMessage(EMOJIS['crown'] . " | Вы не можете взаимодействовать с этим ником.");
            return;
        }

        try {
            // Проверка привязки аккаунта
            $stmt = $this->pdo->prepare(
                "SELECT vk_id, link FROM vk_links 
                WHERE username = ? AND link = 'YES'"
            );
            $stmt->execute([$username]);
            $account = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$account) {
                $this->sendMessage(EMOJIS['blocked'] . " | Аккаунт '$username' не привязан или привязка не завершена.");
                return;
            }

            // Обновление или добавление ранга
            $stmt = $this->pdo->prepare(
                "INSERT INTO vk_rcon (nickname, vk_id, `rank`)
                VALUES (?, ?, ?)
                ON DUPLICATE KEY UPDATE `rank` = VALUES(`rank`)"
            );
            $stmt->execute([$username, $account['vk_id'], $rank]);

            $this->sendMessage(
                EMOJIS['galochka'] . " | Доступ '$rank' уровня успешно выдан игроку [id{$account['vk_id']}|$username]!");
        } catch (PDOException $e) {
            error_log("SetGroup error: " . $e->getMessage());
            $this->sendMessage(EMOJIS['blocked'] . " | Ошибка базы данных.");
        }
    }

    private function sendMessage(string $message, array $params = []): void {
        $this->APIshka->sendMessage($this->user_id, $message, $params);
    }
}