<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/error.log');
require_once __DIR__.'/../config.php';

class BindCommand {
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
        // Проверка времени последней отвязки
        try {
            $stmt = $this->pdo->prepare("SELECT `last_unlink_time` FROM `others` WHERE `vk_id` = ?");
            $stmt->execute([$this->user_id]);
            $row = $stmt->fetch();
            
            if ($row && $row['last_unlink_time']) {
                $elapsed_time = time() - strtotime($row['last_unlink_time']);
                if ($elapsed_time < BOT_SETTINGS['max_unlink_time']) {
                    $remaining = 30 - intval($elapsed_time / 60);
                    $this->sendMessage(EMOJIS['blocked'] . " | Чтоб снова привязать аккаунт, нужно подождать $remaining минут.");
                    return;
                }
            }
        } catch (PDOException $e) {
            error_log("Database error: " . $e->getMessage());
            $this->sendMessage(EMOJIS['blocked'] . " | Ошибка базы данных. Напишите в тех.поддержку!");
            return;
        }

        // Парсинг данных
        $parts = explode(' ', $this->message_text, 3);
        if (count($parts) < 3) {
            $this->sendMessage(EMOJIS['blocked'] . " | Неверный формат. Используйте: /привязка [ник] [код].");
            return;
        }
        $username = strtolower($parts[1]);
        $vk_code = $parts[2];

        // Проверка длины
        if (strlen($username) > 20 || strlen($vk_code) > 10) {
            $this->sendMessage(EMOJIS['blocked'] . " | Слишком длинные данные. Проверьте ник и код.");
            return;
        }

        try {
            // Проверка количества аккаунтов
            $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM `vk_links` WHERE `vk_id` = ?");
            $stmt->execute([$this->user_id]);
            $account_count = $stmt->fetchColumn();

            // Определение лимита
            $max_accounts = in_array($this->user_id, ADMINS) ? BOT_SETTINGS['max_admin_acc'] : BOT_SETTINGS['max_def_acc'];
            if ($account_count >= $max_accounts) {
                $this->sendMessage(EMOJIS['blocked'] . " | Достигнут лимит привязанных аккаунтов ($max_accounts).");
                return;
            }

            // Проверка занятости никнейма
            $stmt = $this->pdo->prepare("SELECT `vk_id` FROM `vk_links` WHERE `username` = ? AND `vk_id` IS NOT NULL");
            $stmt->execute([$username]);
            if ($stmt->fetch()) {
                $this->sendMessage(EMOJIS['blocked'] . " | Аккаунт $username уже привязан к другому профилю!");
                return;
            }

            // Проверка кода и привязка
            $stmt = $this->pdo->prepare("SELECT * FROM `vk_links` WHERE `username` = ? AND `vk_code` = ?");
            $stmt->execute([$username, $vk_code]);
            if ($stmt->fetch()) {
                $this->pdo->beginTransaction();
                
                $this->pdo->prepare("UPDATE `vk_links` SET `vk_id` = ?, `link` = 'YES' WHERE `username` = ? AND `vk_code` = ?")
                    ->execute([$this->user_id, $username, $vk_code]);
                $this->pdo->prepare("INSERT INTO `vk_rcon` (nickname, vk_id, `rank`) VALUES (?, ?, ?)")
                ->execute([$username, $this->user_id, '0']);
                
                $this->pdo->commit();
                $srv_shop = SRV_SHOP;
                $this->sendMessage(EMOJIS['tada'] . " | Вы успешно привязали аккаунт '$username'!\n\n" . EMOJIS['zamochek'] . " | Теперь ваш аккаунт находится под нашей защитой\n\n" . EMOJIS['molitsa'] . " | Будем благодарны, если Вы пожертвуете нам на развитие проекта, купив привилегию - https://{$srv_shop}/");
            } else {
                $this->sendMessage(EMOJIS['blocked'] . " | Неверный ник или код. Проверьте данные.");
            }
        } catch (PDOException $e) {
            $this->pdo->rollBack();
            error_log("Database error: " . $e->getMessage());
            $this->sendMessage(EMOJIS['blocked'] . " | Ошибка базы данных. Напишите в тех.поддержку!");
        }
    }

    private function sendMessage(string $message, array $params = []): void {
        $this->APIshka->sendMessage($this->user_id, $message, $params);
    }

}