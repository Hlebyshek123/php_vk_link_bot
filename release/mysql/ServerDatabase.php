<?php
class ServerDatabase {
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
            die("Ошибка подключения к серверной базе данных: " . $e->getMessage());
        }
    }
    
    public function getConnection(): PDO {
        return $this->pdo;
    }
    
    /**
     * Получение времени игры игрока на сервере
     */
    public function getPlaytime(string $username): array {
    try {
        $stmt = $this->pdo->prepare("SELECT time FROM playtime WHERE nickname = ?");
        $stmt->execute([$username]);
        $seconds = $stmt->fetchColumn() ?: 0;
        
        return [
            'hours' => (int)($seconds / 3600),
            'minutes' => (int)(($seconds % 3600) / 60)
        ];
    } catch (PDOException $e) {
        error_log("Get playtime error: " . $e->getMessage());
        return ['hours' => 0, 'minutes' => 0];
    }
}

    /**
     * Получение последний сессии игрока на сервере
     */
    public function getLastSession(string $username): int {
    try {
        $stmt = $this->pdo->prepare("SELECT session_time FROM playtime WHERE nickname = ?");
        $stmt->execute([$username]);
        $seconds = $stmt->fetchColumn() ?: 0;
        return (int)($seconds / 60);
    } catch (PDOException $e) {
        error_log("Get last session error: " . $e->getMessage());
        return 0;
    }
}

    /**
     * Получение последней даты входа игрока на сервер
     */
    public function getLastDate(string $username): string {
    try {
        $stmt = $this->pdo->prepare("SELECT last_date FROM users WHERE nickname = ?");
        $stmt->execute([$username]);
        $timestamp = $stmt->fetchColumn();
        return $timestamp ? date('Y-m-d H:i:s', $timestamp) : 'Неизвестно';
    } catch (PDOException $e) {
        error_log("Get last date error: " . $e->getMessage());
        return 'Неизвестно';
    }
}

    /**
     * Обновление пароля игрока на сервере
     */
    public function updatePassword(string $username, string $password): void {
    if (empty($password)) {
        throw new InvalidArgumentException("Пароль не может быть пустым.");
    }
    $password;
    $stmt = $this->pdo->prepare("UPDATE users SET password = ? WHERE nickname = ?");
    $stmt->execute([$password, $username]);
}

    /**
     * Получение авторизационной информации об игре на сервере
     */
    public function getAuthInfo(string $username): array {
    try {
        $stmt = $this->pdo->prepare("SELECT last_ip, last_device, last_port FROM users WHERE nickname = ?");
        $stmt->execute([$username]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: ['last_ip' => 'Неизвестно', 'last_device' => 'Неизвестно', 'last_port' => 'Неизвестно'];
    } catch (PDOException $e) {
        error_log("Get auth info error: " . $e->getMessage());
        return ['last_ip' => 'Неизвестно', 'last_device' => 'Неизвестно', 'last_port' => 'Неизвестно'];
    }
}


    public function removeSkinProtection(string $username): bool {
        try {
            $stmt = $this->pdo->prepare("DELETE FROM skin WHERE player = ?");
            $stmt->execute([$username]);
            return $stmt->rowCount() > 0;
        } catch (PDOException $e) {
            error_log("Remove skin protection error: " . $e->getMessage());
            return false;
        }
    }

    public function removeCidProtection(string $username): bool {
        try {
            $stmt = $this->pdo->prepare("DELETE FROM cid WHERE player = ?");
            $stmt->execute([$username]);
            return $stmt->rowCount() > 0;
        } catch (PDOException $e) {
            error_log("Remove CID protection error: " . $e->getMessage());
            return false;
        }
    }
}