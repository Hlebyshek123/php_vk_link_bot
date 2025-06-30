<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/error.log');
require_once __DIR__.'/../config.php';

class BroadCastCommand {
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
            $this->sendMessage(EMOJIS['blocked'] . " | Хмм мне кажется или вы не администратор?");
            return;
        }

        // Извлечение текста рассылки
        $broadcastText = $this->extractBroadcastText();
        if (empty($broadcastText)) {
            $this->sendMessage(EMOJIS['blocked'] . " | Вы не указали сообщение для рассылки.");
            return;
        }

        // Получение списка пользователей
        $recipients = $this->getRecipients();
        if (empty($recipients)) {
            $this->sendMessage(EMOJIS['blocked'] . " | Нет пользователей для рассылки.");
            return;
        }

        // Отправка сообщений
        $successCount = 0;
        $failCount = 0;
        
        foreach ($recipients as $vk_id) {
            try {
                $this->APIshka->sendMessage($vk_id, $broadcastText);
                $successCount++;
                
                // Задержка для соблюдения лимитов VK API (20 сообщений в секунду)
                usleep(50000); // 50ms
            } catch (Exception $e) {
                error_log("Broadcast error to {$vk_id}: " . $e->getMessage());
                $failCount++;
            }
        }

        // Отчет администратору
        $this->sendMessage(
            EMOJIS['tada'] . " | Рассылка завершена!\n" .
            EMOJIS['galochka'] . " | Успешно: {$successCount} пользователей\n" .
            ($failCount > 0 ? EMOJIS['blocked'] . " | Не удалось: {$failCount} пользователей" : "")
        );
    }

    private function extractBroadcastText(): string {
        $prefix = BOT_SETTINGS['manage_prefix'] . 'рассылка';
        return trim(str_replace($prefix, '', $this->message_text));
    }

    private function getRecipients(): array {
        try {
            $stmt = $this->pdo->prepare(
                "SELECT DISTINCT vk_id 
                FROM vk_links 
                WHERE link = 'YES' AND vk_id IS NOT NULL"
            );
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_COLUMN);
        } catch (PDOException $e) {
            error_log("Get recipients error: " . $e->getMessage());
            return [];
        }
    }

    private function sendMessage(string $message, array $params = []): void {
        $this->APIshka->sendMessage($this->user_id, $message, $params);
    }
}