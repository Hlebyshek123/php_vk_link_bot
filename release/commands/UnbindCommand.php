<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/error.log');
require_once __DIR__.'/../config.php';

class UnbindCommand {
    private $pdo;
    private $pdo2;
    private $botDB;
    private $serverDB;
    private $APIshka;
    private $user_id;
    private $message_text;

    public function __construct($pdo, $pdo2, $botDB, $serverDB, $APIshka, $user_id, $message_text) {
        $this->pdo = $pdo;
        $this->pdo2 = $pdo2;
        $this->botDB = $botDB;
        $this->serverDB = $serverDB;
        $this->APIshka = $APIshka;
        $this->user_id = $user_id;
        $this->message_text = $message_text;
    }

    public function execute() {
        $parts = explode(' ', $this->message_text, 3);
        $command = strtolower($parts[0] ?? '');
        $confirmation = strtolower($parts[1] ?? '');

        // Получаем выбранный аккаунт
        $account = $this->getSelectedAccount();
        
        if (!$account) {
            $this->sendMessage(EMOJIS['blocked'] . " | Вы не выбрали аккаунт для отвязки. Используйте " . BOT_SETTINGS['bot_prefix'] . "акк [номер]");
            return;
        }

        // Первый этап: запрос подтверждения
        if ($this->isAccountLinked($account) === true) {
            $allowed_confirms = ['да', 'yes'];
            if (!in_array(strtolower($confirmation), $allowed_confirms)) {
            $this->sendMessage(
                EMOJIS['grystni'] . " | Чтобы отвязать аккаунт '$account', вы должны подтвердить действие\n\n" .
                EMOJIS['pencil'] . " | Напишите: " . BOT_SETTINGS['bot_prefix'] . "отвязка да\n\n" .
                EMOJIS['ostorozno'] . " | Действие необратимо! Новую привязку можно будет сделать через 30 минут"
            );
            return;
        }
        } else {
            $this->sendMessage(EMOJIS['blocked'] . " | Аккаунт не найден или не привязан.");
            return;
        }

        // Второй этап: обработка отвязки
        try {
            if (!$this->isAccountLinked($account)) {
                $this->sendMessage(EMOJIS['blocked'] . " | Аккаунт $account не найден");
                return;
            }

            $this->pdo->beginTransaction();
            
            $this->deleteVkLink($account);
            $this->deleteVkRcon($account);
            $this->updateLastUnlinkTime();
            
            $this->pdo->commit();
            
            $this->sendMessage(EMOJIS['tada'] . " | Аккаунт $account успешно отвязан!");
            
        } catch (PDOException $e) {
            $this->pdo->rollBack();
            error_log("Unbind error: " . $e->getMessage());
            $this->sendMessage(EMOJIS['blocked'] . " | Ошибка базы данных. Обратитесь в поддержку!");
        }
    }

    private function getSelectedAccount(): ?string {
        $stmt = $this->pdo->prepare("SELECT `selected_account` FROM `user_settings` WHERE `vk_id` = ?");
        $stmt->execute([$this->user_id]);
        return $stmt->fetchColumn() ?: null;
    }

    private function isAccountLinked(string $account): bool {
        $stmt = $this->pdo->prepare("SELECT 1 FROM `vk_links` WHERE `vk_id` = ? AND `username` = ?");
        $stmt->execute([$this->user_id, $account]);
        return (bool)$stmt->fetch();
    }

    private function deleteVkLink(string $account): void {
        $stmt = $this->pdo->prepare("DELETE FROM `vk_links` WHERE `vk_id` = ? AND `username` = ?");
        $stmt->execute([$this->user_id, $account]);
    }

    private function deleteVkRcon(string $account): void {
        $stmt = $this->pdo->prepare("DELETE FROM `vk_rcon` WHERE `vk_id` = ? AND `nickname` = ?");
        $stmt->execute([$this->user_id, $account]);
    }

    private function updateLastUnlinkTime(): void {
        $stmt = $this->pdo->prepare(
            "INSERT INTO others (vk_id, last_unlink_time) 
            VALUES (?, NOW()) 
            ON DUPLICATE KEY UPDATE last_unlink_time = NOW()"
        );
        $stmt->execute([$this->user_id]);
    }

    private function sendMessage(string $message, array $params = []): void {
        $this->APIshka->sendMessage($this->user_id, $message, $params);
    }
}