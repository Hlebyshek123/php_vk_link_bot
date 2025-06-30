<?php
class BotDatabase {
    private $pdo;
    
    public function __construct(array $config) {
        try {
            $dsn = "mysql:host={$config['ip']};dbname={$config['dbname']}";
            $this->pdo = new PDO(
                $dsn, 
                $config['user'], 
                $config['password'],
                [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
            );
        } catch (PDOException $e) {
            die("Ошибка подключения к базе данных Бота: " . $e->getMessage());
        }
    }
    
    public function getConnection(): PDO {
        return $this->pdo;
    }

    /**
     * Получение ранга игрока по никнейму
     */
    public function getPlayerRank(string $username): ?string {
        try {
            $stmt = $this->pdo->prepare("SELECT `rank` FROM `vk_rcon` WHERE `nickname` = ?");
            $stmt->execute([$username]);
            return $stmt->fetchColumn() ?: null;
        } catch (PDOException $e) {
            error_log("Rank fetch error: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Получение выбранного аккаунта пользователя
     */
    public function getSelectedAccount(int $vk_id): ?string {
        try {
            $stmt = $this->pdo->prepare("SELECT `selected_account` FROM `user_settings` WHERE `vk_id` = ?");
            $stmt->execute([$vk_id]);
            return $stmt->fetchColumn() ?: null;
        } catch (PDOException $e) {
            error_log("Account fetch error: " . $e->getMessage());
            return null;
        }
    }
    
    /**
     * Обновление выбранного аккаунта пользователя
     */
    public function updateSelectedAccount(int $vk_id, string $account): void {
    try {
        $stmt = $this->pdo->prepare(
            "INSERT INTO user_settings (vk_id, selected_account) 
            VALUES (?, ?) 
            ON DUPLICATE KEY UPDATE selected_account = ?"
        );
        $stmt->execute([$vk_id, $account, $account]);
    } catch (PDOException $e) {
        error_log("Update selected account error: " . $e->getMessage());
    }
}

    /**
     * временное хранение пароля до подтверждения
     */
    public function storeTempPassword(int $vk_id, string $username, string $password): void {
    try {
        $stmt = $this->pdo->prepare(
            "INSERT INTO temp_data (vk_id, username, temp_password) 
            VALUES (?, ?, ?) 
            ON DUPLICATE KEY UPDATE 
                username = VALUES(username), 
                temp_password = VALUES(temp_password)"
        );
        $stmt->execute([$vk_id, $username, $password]);
    } catch (PDOException $e) {
        error_log("Store temp password error: " . $e->getMessage());
        throw $e;
    }
}

    /**
     * Получение временного пароля для подтверждения
     */
    public function getTempPassword(int $vk_id): ?array {
    try {
        $stmt = $this->pdo->prepare("SELECT username, temp_password FROM temp_data WHERE vk_id = ?");
        $stmt->execute([$vk_id]);
        $data = $stmt->fetch(PDO::FETCH_ASSOC);
        return ($data && !empty($data['temp_password'])) ? $data : null;
    } catch (PDOException $e) {
        error_log("Get temp password error: " . $e->getMessage());
        return null;
    }
}

    /**
     * Очистка временного пароля подтверждения
     */

    public function clearTempPassword(int $vk_id): void {
    try {
        $stmt = $this->pdo->prepare("DELETE FROM temp_data WHERE vk_id = ?");
        $stmt->execute([$vk_id]);
    } catch (PDOException $e) {
        error_log("Clear temp password error: " . $e->getMessage());
    }
}

public function isPlayerLinked(string $username): bool {
    try {
        $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM vk_rcon WHERE nickname = ? AND `rank` IS NOT NULL");
        $stmt->execute([$username]);
        return (bool)$stmt->fetchColumn();
    } catch (PDOException $e) {
        error_log("Player link check error: " . $e->getMessage());
        return false;
    }
}

public function getVkIdByNickname(string $username): int {
    try {
        $stmt = $this->pdo->prepare("SELECT vk_id FROM vk_rcon WHERE nickname = ?");
        $stmt->execute([$username]);
        $result = $stmt->fetchColumn();
        return $result ? (int)$result : null;
    } catch (PDOException $e) {
        error_log("Get VK ID error: " . $e->getMessage());
        return null;
    }
}

    public function getLastPasswordResetTime(int $vk_id): ?string {
    try {
        $stmt = $this->pdo->prepare("SELECT last_reset_time FROM others WHERE vk_id = ?");
        $stmt->execute([$vk_id]);
        return $stmt->fetchColumn();
    } catch (PDOException $e) {
        error_log("Get last reset time error: " . $e->getMessage());
        return null;
    }
}

    public function updateLastPasswordResetTime(int $vk_id): void {
    try {
        $stmt = $this->pdo->prepare(
            "INSERT INTO others (vk_id, last_reset_time) 
            VALUES (?, NOW()) 
            ON DUPLICATE KEY UPDATE last_reset_time = NOW()"
        );
        $stmt->execute([$vk_id]);
    } catch (PDOException $e) {
        error_log("Update last reset time error: " . $e->getMessage());
    }
}

    public function getLastKickTime(int $vk_id): ?string {
        try {
            $stmt = $this->pdo->prepare("SELECT last_kick_time FROM others WHERE vk_id = ?");
            $stmt->execute([$vk_id]);
            return $stmt->fetchColumn();
        } catch (PDOException $e) {
            error_log("Get last kick time error: " . $e->getMessage());
            return null;
        }
    }

    public function updateLastKickTime(int $vk_id): void {
        try {
            $stmt = $this->pdo->prepare(
                "INSERT INTO others (vk_id, last_kick_time) 
                VALUES (?, NOW()) 
                ON DUPLICATE KEY UPDATE last_kick_time = NOW()"
            );
            $stmt->execute([$vk_id]);
        } catch (PDOException $e) {
            error_log("Update last kick time error: " . $e->getMessage());
        }
    }
    
    public function hasPermissions($selectedAccount, $rank, $bot_cmd): bool {
        if (!$selectedAccount) return false;
        if (!$rank) return false;

        // Проверка разрешенных рангов
        $allowedRanks = BOT_RANKS['allowed_ranks'][$bot_cmd] ?? [];
        return in_array($rank, $allowedRanks);
    }
    
    public function removeBan(string $username): void {
        $stmt = $this->pdo->prepare(
            "UPDATE vk_rcon 
            SET banned = 'NO', 
                ban_reason = NULL, 
                ban_time = NULL, 
                ban_by = NULL 
            WHERE nickname = ?"
        );
        $stmt->execute([$username]);
    }
    
    public function isPlayerBanned(string $username): bool {
        $stmt = $this->pdo->prepare("SELECT banned FROM vk_rcon WHERE nickname = ?");
        $stmt->execute([$username]);
        $data = $stmt->fetch();
        
        return ($data && $data['banned'] === 'YES');
    }
    
    public function updateBan(string $username, string $reason, string $banTime, string $adminAccount): void {
        $stmt = $this->pdo->prepare(
            "UPDATE vk_rcon 
            SET banned = 'YES', 
                ban_reason = ?, 
                ban_time = ?, 
                ban_by = ? 
            WHERE nickname = ?"
        );
        $stmt->execute([$reason, $banTime, $adminAccount, $username]);
    }
    
    public function playerExists(string $username): bool {
        $stmt = $this->pdo->prepare("SELECT nickname FROM vk_rcon WHERE nickname = ?");
        $stmt->execute([$username]);
        return (bool)$stmt->fetch();
    }
    
    public function getLinkedAccounts(int $vk_id): array {
        $stmt = $this->pdo->prepare(
            "SELECT username 
            FROM vk_links 
            WHERE vk_id = ? AND link = 'YES'"
        );
        $stmt->execute([$vk_id]);
        return $stmt->fetchAll(PDO::FETCH_COLUMN);
    }

}