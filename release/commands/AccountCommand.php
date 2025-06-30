<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/error.log');
require_once __DIR__.'/../config.php';
require_once __DIR__.'/../Rcon.php';

class AccountCommand {
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
        $parts = explode(' ', $this->message_text);
        $command = $parts[0] ?? '';
        $arg1 = $parts[1] ?? '';
        $arg2 = $parts[2] ?? '';
        $arg3 = $parts[3] ?? '';

        // Обработка команды без аргументов
        if (count($parts) < 2) {
            $this->sendMessage(
                    EMOJIS['blocked'] . " | Неверные аргументы. Используйте: " . 
                    BOT_SETTINGS['bot_prefix'] . "акк [аргумент]\n ~ 1 - список аккаунтов.\n ~ 2 [номер] - выбор аккаунта.\n ~ 3 - профиль аккаунта.\n ~ 4 [новый_пароль] - восстановление пароля.\n ~ 5 [номер_сервера] - кик с сервера.\n ~ 6 [skin/cid] - сбросить защиту."
                );
            return;
        }

        // Обработка подкоманд
        if ($arg1 === "1") {
            $this->listAccounts();
        } 
        elseif ($arg1 === "2") {
            $this->selectAccount($arg2);
        } 
        elseif ($arg1 === "3") {
            $this->showProfile();
        } 
        elseif ($arg1 === "4") {
            $this->initPasswordReset($arg2);
        }
        elseif ($arg1 === "5") {
            $this->kickAccount($arg2);
        }
        elseif ($arg1 === "6") {
            $this->resetProtection($arg2);
        }
        elseif ($arg1 === "ПОДТВЕРЖДАЮ") {
            $this->confirmPasswordReset();
        } 
        else {
            $this->sendMessage(
                    EMOJIS['blocked'] . " | Неверные аргументы. Используйте: " . 
                    BOT_SETTINGS['bot_prefix'] . "акк [аргумент]\n ~ 1 - список аккаунтов.\n ~ 2 [номер] - выбор аккаунта.\n ~ 3 - профиль аккаунта.\n ~ 4 [новый_пароль] - восстановление пароля.\n ~ 5 [номер_сервера] - кик с сервера.\n ~ 6 [skin/cid] - сбросить защиту."
                );
        }
    }

    private function listAccounts() {
        try {
            $stmt = $this->pdo->prepare("SELECT username FROM vk_links WHERE vk_id = ?");
            $stmt->execute([$this->user_id]);
            $accounts = $stmt->fetchAll(PDO::FETCH_COLUMN);

            $selectedAccount = $this->botDB->getSelectedAccount($this->user_id);

            if (!empty($accounts)) {
                $accountList = [];
                foreach ($accounts as $index => $account) {
                    $emoji = ($account === $selectedAccount) ? EMOJIS['galochka'] : '✨';
                    $accountList[] = "{$emoji} " . ($index + 1) . ". $account";
                }
                
                $this->sendMessage(
                    EMOJIS['joystick'] . " | Ваши привязанные аккаунты:\n" . 
                    implode("\n", $accountList)
                );
            } else {
                $this->sendMessage(EMOJIS['blocked'] . " | У вас нет привязанных аккаунтов.");
            }
        } catch (PDOException $e) {
            error_log("List accounts error: " . $e->getMessage());
            $this->sendMessage(EMOJIS['blocked'] . " | Ошибка базы данных.");
        }
    }

    private function selectAccount($accountNumber) {
        if (empty($accountNumber)) {
            $this->sendMessage(EMOJIS['blocked'] . " | Укажите номер аккаунта.");
            return;
        }

        $accountIndex = (int)$accountNumber - 1;

        try {
            $stmt = $this->pdo->prepare("SELECT username FROM vk_links WHERE vk_id = ?");
            $stmt->execute([$this->user_id]);
            $accounts = $stmt->fetchAll(PDO::FETCH_COLUMN);

            if (isset($accounts[$accountIndex])) {
                $selectedAccount = $accounts[$accountIndex];
                $this->botDB->updateSelectedAccount($this->user_id, $selectedAccount);
                $this->sendMessage(EMOJIS['galochka'] . " | Аккаунт '$selectedAccount' выбран!");
            } else {
                $this->sendMessage(EMOJIS['blocked'] . " | Неверный номер аккаунта.");
            }
        } catch (PDOException $e) {
            error_log("Select account error: " . $e->getMessage());
            $this->sendMessage(EMOJIS['blocked'] . " | Ошибка базы данных.");
        }
    }

    private function showProfile() {
        $selectedAccount = $this->botDB->getSelectedAccount($this->user_id);

        if (!$selectedAccount) {
            $this->sendMessage(EMOJIS['blocked'] . " | Выберите аккаунт через " . BOT_SETTINGS['bot_prefix'] . "акк 2 [номер]");
            return;
        }

        try {
            // Получение основной информации
            $stmt = $this->pdo->prepare("SELECT username FROM vk_links WHERE vk_id = ? AND username = ?");
            $stmt->execute([$this->user_id, $selectedAccount]);
            
            if ($stmt->fetch()) {
                $playtime = $this->serverDB->getPlaytime($selectedAccount);
                $lastSession = $this->serverDB->getLastSession($selectedAccount);
                $lastDate = $this->serverDB->getLastDate($selectedAccount);
                $authInfo = $this->serverDB->getAuthInfo($selectedAccount);
                $rank = $this->botDB->getPlayerRank($selectedAccount);

                $message = EMOJIS['bymaga2'] . " | Профиль $selectedAccount:\n" . EMOJIS['newbie'] . " | ВК ID: {$this->user_id}\n" . EMOJIS['crown'] . " | Доступ: " . ($rank ?: "Нет") . " lvl\n" . EMOJIS['joystick'] . " | Всего наиграно: {$playtime['hours']} ч. {$playtime['minutes']} м.\n" . EMOJIS['clock'] . " | Последняя сессия: {$lastSession} м.\n" . "============\n" . EMOJIS['zamochek_i_key'] . " | Последний вход:\n ~ Дата - {$lastDate} MSK\n ~ IP - {$authInfo['last_ip']}\n ~ Устройство - {$authInfo['last_device']}\n ~ Порт - {$authInfo['last_port']}\n" . "============";
                $this->sendMessage($message);
            } else {
                $this->sendMessage(EMOJIS['blocked'] . " | Аккаунт '{$selectedAccount}' более не привязан к вам.");
            }
        } catch (PDOException $e) {
            error_log("Profile error: " . $e->getMessage());
            $this->sendMessage(EMOJIS['blocked'] . " | Ошибка базы данных.");
        }
    }

    private function initPasswordReset($newPassword) {
        
    $selectedAccount = $this->botDB->getSelectedAccount($this->user_id);
    
    if (!$selectedAccount) {
        $this->sendMessage(EMOJIS['blocked'] . " | Выберите аккаунт через " . BOT_SETTINGS['bot_prefix'] . "акк 2 [номер]");
        return;
    }
    
    if (empty($newPassword)) {
        $this->sendMessage(EMOJIS['blocked'] . " | Укажите новый пароль.");
        return;
    }

    // Проверка сложности пароля
    if (!preg_match('/^[a-zA-Zа-яА-Я0-9.,!?@#$%^&*_]{6,24}$/u', $newPassword)) {
        $message = EMOJIS['blocked'] . " | Пароль должен:\n" .
            "~ Быть длиной 6-24 символа\n" .
            "~ Содержать: латиницу, кириллицу, цифры или символы [.,!?@#$%^&*_]";
        $this->sendMessage($message);
        return;
    }

    // Проверка времени последнего сброса пароля
    try {
        $lastResetTime = $this->botDB->getLastPasswordResetTime($this->user_id);
        if ($lastResetTime) {
            $lastResetTimestamp = strtotime($lastResetTime);
            $currentTime = time();
            $elapsed = $currentTime - $lastResetTimestamp;
            
            // 30 минут = 1800 секунд
            if ($elapsed < BOT_SETTINGS['max_reset_time']) {
                $remaining = 30 - (int)($elapsed / 60);
                $this->sendMessage(
                    EMOJIS['blocked'] . " | Вы уже меняли свой пароль ранее. " .
                    "Повторно сменить пароль вы сможете только через $remaining минут."
                );
                return;
            }
        }
    } catch (Exception $e) {
        error_log("Reset time check error: " . $e->getMessage());
    }

    try {
        $this->botDB->storeTempPassword($this->user_id, $selectedAccount, $newPassword);
        $this->sendMessage(
            EMOJIS['joystick'] . " | Смена пароля для аккаунта '$selectedAccount': \n\n" . 
            EMOJIS['keychik'] . " | Ваш новый пароль: {$newPassword}\n\n" . 
            EMOJIS['pencil'] . " | Для подтверждения введите в чат '" . BOT_SETTINGS['bot_prefix'] . "акк ПОДТВЕРЖДАЮ'"
        );
    } catch (PDOException $e) {
        error_log("Password init error: " . $e->getMessage());
        $this->sendMessage(EMOJIS['blocked'] . " | Ошибка базы данных.");
    }
}

    private function confirmPasswordReset() {
    try {
        $tempData = $this->botDB->getTempPassword($this->user_id);
        
        if (!$tempData) {
            $this->sendMessage(EMOJIS['blocked'] . " | Нет активных запросов на смену пароля.");
            return;
        }

        $this->serverDB->updatePassword($tempData['username'], $tempData['temp_password']);
        $this->botDB->updateLastPasswordResetTime($this->user_id);
        $this->botDB->clearTempPassword($this->user_id);
        $this->sendMessage(EMOJIS['galochka'] . " | Пароль для '{$tempData['username']}' успешно изменен!");
    } catch (PDOException $e) {
        error_log("Password confirm error: " . $e->getMessage());
        $this->sendMessage(EMOJIS['blocked'] . " | Ошибка базы данных.");
    } catch (InvalidArgumentException $e) {
        $this->sendMessage(EMOJIS['blocked'] . " | Ошибка: " . $e->getMessage());
    }
}

    private function kickAccount($serverIndex = null) {
    $selectedAccount = $this->botDB->getSelectedAccount($this->user_id);
    if (!$selectedAccount) {
        $this->sendMessage(EMOJIS['blocked'] . " | Выберите аккаунт через " . BOT_SETTINGS['bot_prefix'] . "акк 2 [номер]");
        return;
    }

    // Если не указан сервер - показать список
    if ($serverIndex === null || $serverIndex === '') {
        $this->sendServerList();
        return;
    }

    // Проверяем валидность номера сервера
    if (!is_numeric($serverIndex)) {
        $this->sendMessage(EMOJIS['blocked'] . " | Укажите номер сервера (цифра)");
        return;
    }

    $serverIndex = (int)$serverIndex;
    $servers = array_keys(SERVERS);
    
    if ($serverIndex < 1 || $serverIndex > count($servers)) {
        $this->sendMessage(EMOJIS['blocked'] . " | Неверный номер сервера. Доступно: 1-" . count($servers));
        return;
    }

    $serverName = $servers[$serverIndex - 1];
    $serverConfig = SERVERS[$serverName];

    try {
        // Проверка кулдауна (5 минут)
        $lastKickTime = $this->botDB->getLastKickTime($this->user_id);
        if ($lastKickTime) {
            $lastKickTimestamp = strtotime($lastKickTime);
            $currentTime = time();
            $elapsed = $currentTime - $lastKickTimestamp;
            
            if ($elapsed < 300) {
                $remaining = 300 - $elapsed;
                $minutes = ceil($remaining / 60);
                $this->sendMessage(
                    EMOJIS['blocked'] . " | Вы сможете использовать кик снова через $minutes минут(-ы)."
                );
                return;
            }
        }

        // Выполнение RCON команды
        $rcon = new Rcon(
            $serverConfig['rcon_host'],
            $serverConfig['rcon_port'],
            $serverConfig['rcon_password'],
            3
        );

        if (!$rcon->connect()) {
            throw new Exception("Ошибка подключения: " . $rcon->getResponse());
        }

        // Отправляем команду кика
        $command = "auth-kick $selectedAccount";
        $response = $rcon->sendCommand($command);
        $rcon->disconnect();

        // Обновляем время последнего кика
        $this->botDB->updateLastKickTime($this->user_id);

        // Форматируем ответ
        $response = trim($response) ?: "Команда выполнена (ответ пустой)";
        $this->sendMessage(
            EMOJIS['galochka'] . " | Команда успешно выполнена на сервере ($serverName): \n$response");
        
    } catch (Exception $e) {
        $this->sendMessage(EMOJIS['blocked'] . " | Ошибка: " . $e->getMessage());
    }
}
    
    private function sendServerList() {
        $servers = array_keys(SERVERS);
        $message = EMOJIS['computer'] . " | Выберите сервер для кика:\n";
        
        foreach ($servers as $i => $server) {
            $message .= ($i + 1) . ". $server\n";
        }
        $this->sendMessage($message);
    }
    
    private function resetProtection($type) {
        $selectedAccount = $this->botDB->getSelectedAccount($this->user_id);
        if (!$selectedAccount) {
            $this->sendMessage(EMOJIS['blocked'] . " | Выберите аккаунт через " . BOT_SETTINGS['bot_prefix'] . "акк 2 [номер]");
            return;
        }

        $type = strtolower(trim($type));
        if (!$type) {
            $this->sendMessage(
                EMOJIS['blocked'] . " | Выберите тип защиты:\n" .
                "~ skin - защита по скину\n" .
                "~ cid - защита по Client ID");
            return;
        }

        try {
            $result = false;
            $message = "";
            
            if ($type === 'skin') {
                $result = $this->serverDB->removeSkinProtection($selectedAccount);
                $message = "защита по скину(SKIN)";
            } 
            elseif ($type === 'cid') {
                $result = $this->serverDB->removeCidProtection($selectedAccount);
                $message = "защита по Client ID";
            } 
            else {
                $this->sendMessage(
                    EMOJIS['blocked'] . " | Неверный тип защиты\n" .
                    "~ skin - защита по скину\n" .
                    "~ cid - защита по Client ID"
                );
                return;
            }

            if ($result) {
                $this->sendMessage(EMOJIS['galochka'] . " | $message для $selectedAccount была сброшена!");
            } else {
                $this->sendMessage(EMOJIS['blocked'] . " | У вас нет этой защиты");
            }
        } catch (Exception $e) {
            $this->sendMessage(EMOJIS['blocked'] . " | Ошибка: " . $e->getMessage());
        }
    }

    private function sendMessage(string $message, array $params = []): void {
        $this->APIshka->sendMessage($this->user_id, $message, $params);
    }
}