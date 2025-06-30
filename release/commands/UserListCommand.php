<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/error.log');
require_once __DIR__.'/../config.php';

class UserListCommand {
    private $pdo;
    private $botDB;
    private $APIshka;
    private $user_id;
    private $message_text;
    private const PAGE_SIZE = 3;

    public function __construct($pdo, $botDB, $APIshka, $user_id, $message_text) {
        $this->pdo = $pdo;
        $this->botDB = $botDB;
        $this->APIshka = $APIshka;
        $this->user_id = $user_id;
        $this->message_text = $message_text;
    }

    public function execute() {
        // Проверка прав пользователя
        if (!$this->hasPermissions()) {
            $this->sendMessage(EMOJIS['blocked'] . " | У вас недостаточно прав для использования этой команды.");
            return;
        }

        // Получение номера страницы
        $page = $this->getPageNumber();
        if ($page === null) {
            $this->sendMessage(EMOJIS['blocked'] . " | Неверный формат. Используйте: " . BOT_SETTINGS['bot_prefix'] . "user-list [номер страницы]");
            return;
        }

        // Получение данных
        $totalUsers = $this->getTotalUsersCount();
        $totalPages = $this->calculateTotalPages($totalUsers);
        
        // Проверка допустимости страницы
        if ($page < 1 || $page > $totalPages) {
            $this->sendMessage(EMOJIS['blocked'] . " | Неверный номер страницы. Доступно страниц: $totalPages");
            return;
        }

        // Получение пользователей для страницы
        $users = $this->getUsersForPage($page);
        $this->sendUserList($users, $page, $totalPages);
    }

    private function hasPermissions(): bool {
        // Получаем выбранный аккаунт пользователя
        $selectedAccount = $this->botDB->getSelectedAccount($this->user_id);
        if (!$selectedAccount) return false;

        // Получаем ранг пользователя
        $userRank = $this->botDB->getPlayerRank($selectedAccount);
        if (!$userRank) return false;

        // Проверяем разрешенные ранги для этой команды
        $allowedRanks = BOT_RANKS['allowed_ranks']['user_list'] ?? [];
        return in_array($userRank, $allowedRanks);
    }

    private function getPageNumber(): ?int {
        $parts = explode(' ', $this->message_text, 2);
        if (!isset($parts[1])) return 1; // По умолчанию первая страница
        
        $pageInput = trim($parts[1]);
        return is_numeric($pageInput) ? (int)$pageInput : null;
    }

    private function getTotalUsersCount(): int {
        try {
            $stmt = $this->pdo->query("SELECT COUNT(*) FROM vk_rcon");
            return (int)$stmt->fetchColumn();
        } catch (PDOException $e) {
            error_log("Total users count error: " . $e->getMessage());
            return 0;
        }
    }

    private function calculateTotalPages(int $totalUsers): int {
        return max(1, ceil($totalUsers / self::PAGE_SIZE));
    }

    private function getUsersForPage(int $page): array {
        $offset = ($page - 1) * self::PAGE_SIZE;
        
        try {
            $stmt = $this->pdo->prepare(
                "SELECT nickname, `rank` 
                FROM vk_rcon 
                LIMIT :limit OFFSET :offset"
            );
            
            $stmt->bindValue(':limit', self::PAGE_SIZE, PDO::PARAM_INT);
            $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
            $stmt->execute();
            
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Users page error: " . $e->getMessage());
            return [];
        }
    }

    private function sendUserList(array $users, int $currentPage, int $totalPages): void {
        if (empty($users)) {
            $this->sendMessage(EMOJIS['blocked'] . " | Нет данных для страницы $currentPage.");
            return;
        }

        $message = EMOJIS['korobochka'] . " | Список всех аккаунтов в боте\n\n";
        
        foreach ($users as $user) {
            $message .= EMOJIS['joystick'] . " | Ник: {$user['nickname']}\n";
            $message .= EMOJIS['crown'] . " | Доступ: {$user['rank']}\n";
            $message .= "------------------------------------\n";
        }
        
        $message .= EMOJIS['page'] . " | Страница $currentPage из $totalPages";
        $this->sendMessage($message);
    }

    private function sendMessage(string $message, array $params = []): void {
        $this->APIshka->sendMessage($this->user_id, $message, $params);
    }
}