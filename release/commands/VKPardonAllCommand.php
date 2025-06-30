<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/error.log');
require_once __DIR__.'/../config.php';
require_once __DIR__.'/../Rcon.php';

class VKPardonAllCommand {
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
        // Проверка прав
        $selec_acc = $this->botDB->getSelectedAccount($this->user_id);
        $rank = $this->botDB->getPlayerRank($selec_acc);
        $cmd = "vkpardon_all";
        if (!$this->botDB->hasPermissions($selec_acc, $rank, $cmd)) {
            $this->sendMessage(EMOJIS['blocked'] . " | У вас недостаточно прав для выполнения этой команды.");
            return;
        }
        
        if ($this->botDB->isPlayerBanned($selec_acc)) {
            $this->sendMessage(EMOJIS['chepi'] . " | Вы заблокированы в консоли сервера. Команда более недоступна.");
            return;
        }

        // Парсинг аргументов
        $parts = explode(' ', $this->message_text);
        array_shift($parts); // Удаляем саму команду

        // Проверка количества аргументов
        if (count($parts) < 1) {
            $this->sendMessage(
                EMOJIS['blocked'] . " | Использование: " . BOT_SETTINGS['bot_prefix'] . "pardon-all [ник_администратора]"
            );
            return;
        }

        $abusedAdmin = strtolower(trim($parts[0]));
        $executorAccount = $this->botDB->getSelectedAccount($this->user_id);

        if (!$executorAccount) {
            $this->sendMessage(EMOJIS['blocked'] . " | У вас не выбран аккаунт. Используйте команду !акк 2 [номер].");
            return;
        }

        try {
            // 1. Находим все аккаунты, забаненные указанным администратором
            $bannedAccounts = $this->getBannedByAdmin($abusedAdmin);
            
            if (empty($bannedAccounts)) {
                $this->sendMessage(EMOJIS['blocked'] . " | Администратор $abusedAdmin не банил игроков.");
                return;
            }

            // 2. Разбаниваем все найденные аккаунты
            $unbannedList = [];
            foreach ($bannedAccounts as $account) {
                $this->botDB->removeBan($account);
                $unbannedList[] = $account;
            }

            // 3. Баним самого администратора на 1 год
            $this->banAbuser($abusedAdmin, $executorAccount);

            // 4. Формируем сообщения
            $unbannedCount = count($unbannedList);
            $unbannedNames = implode(', ', $unbannedList);

            // 5. Отправляем broadcast на сервер
            $this->broadcastPardonAll($abusedAdmin, $executorAccount, $unbannedCount, $unbannedNames);

            // 6. Отправляем отчет инициатору
            $this->sendReportToExecutor($executorAccount, $unbannedCount, $unbannedNames, $abusedAdmin);

        } catch (Exception $e) {
            $this->sendMessage(EMOJIS['blocked'] . " | Ошибка: " . $e->getMessage());
        }
    }

    private function getBannedByAdmin(string $adminName): array {
        $stmt = $this->pdo->prepare(
            "SELECT nickname 
            FROM vk_rcon 
            WHERE ban_by = ? AND banned = 'YES'"
        );
        $stmt->execute([$adminName]);
        return $stmt->fetchAll(PDO::FETCH_COLUMN);
    }

    private function banAbuser(string $abusedAdmin, string $executorAccount): void {
        // Рассчитываем время окончания бана (1 год)
        $banEndTime = time() + (8760 * 3600);
        $banTime = date("Y-m-d H:i:s", $banEndTime);
        $reason = "Злоупотребление админ-правами";

        $stmt = $this->pdo->prepare(
            "UPDATE vk_rcon 
            SET banned = 'YES', 
                ban_reason = ?, 
                ban_time = ?, 
                ban_by = ? 
            WHERE nickname = ?"
        );
        $stmt->execute([$reason, $banTime, $executorAccount, $abusedAdmin]);
    }

    private function broadcastPardonAll(string $abusedAdmin, string $executorAccount, int $unbannedCount, string $unbannedNames): void {
        $message = "§f> §aVKPARDON §fИгроком §b{$executorAccount} §fбыли разблокированы §a{$unbannedCount} §fаккаунта(-ов):\n" .
                   "§f> §f{$unbannedNames}\n" .
                   "§f> §fПричина: игрок §c{$abusedAdmin} §fзлоупотребил своими админ-правами на сервере.\n" .
                   "§f> §7• §fАккаунт §c{$abusedAdmin} §fбыл заблокирован в ВК консоли на §61 год";

        // Отправляем broadcast на все сервера
        foreach (SERVERS as $serverName => $serverConfig) {
            $this->sendRconCommand($serverName, "vksay " . $this->escapeRconMessage($message));
        }
    }

    private function escapeRconMessage(string $message): string {
        return str_replace(['"', "'"], '', $message);
    }

    private function sendRconCommand(string $serverName, string $command): void {
        $server = SERVERS[$serverName];
        try {
            $rcon = new Rcon(
                $server['rcon_host'],
                $server['rcon_port'],
                $server['rcon_password'],
                3
            );

            if ($rcon->connect()) {
                $rcon->sendCommand($command);
                $rcon->disconnect();
            }
        } catch (Exception $e) {
            error_log("RCON broadcast error on $serverName: " . $e->getMessage());
        }
    }

    private function sendReportToExecutor(string $executorAccount, int $unbannedCount, string $unbannedNames, string $abusedAdmin): void {
        $message = EMOJIS['galochka'] . " | Вы успешно разблокировали $unbannedCount аккаунта(-ов):\n\n" .
                   EMOJIS['joystick'] . " | Игроки: $unbannedNames\n\n" .
                   EMOJIS['blocked'] . " | Администратор $abusedAdmin был заблокирован на 1 год за злоупотребление админ-правами";

        $this->APIshka->sendMessage($this->user_id, $message);
    }

    private function sendMessage(string $message): void {
        $this->APIshka->sendMessage($this->user_id, $message);
    }
}