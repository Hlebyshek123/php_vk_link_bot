<?php

namespace VKManager;

use pocketmine\plugin\PluginBase;
use pocketmine\command\Command;
use pocketmine\command\CommandSender;
use pocketmine\command\ConsoleCommandSender;
use pocketmine\event\Listener;
use pocketmine\utils\Config;
use pocketmine\event\player\PlayerCommandPreprocessEvent;
use pocketmine\Player;
use mysqli;

class VKManagerMain extends PluginBase implements Listener {

    private $db;
    private $protectedNicks = [];
    private $vkAccessToken;
    private $vkOwnerId;
    private $cooldowns;
    private $blacklistCommands = ["ban-list", "ban", "kick", "pardon", "mute", "unmute", "addmoney", "broadcast"];
    private $allowedRanks = ["Console", "GlConsole", "Developer", "Administrator", "SeniorAdmin", "admsrv"];
    private $allowedAccess = ["1", "2", "3", "4", "5"];

    public function onEnable(): void {
        $this->saveResource("config.yml");
        $config = new Config($this->getDataFolder() . "config.yml", Config::YAML);
        $this->vkAccessToken = $config->get("vk_access_token", "");
        $this->vkOwnerId = $config->get("vk_owner_id", "");
        $this->protectedNicks = array_map('strtolower', $config->get("protected_nicks", []));

        // MySQL connection
        $this->db = new mysqli(
            $config->get("host", "localhost"),
            $config->get("user", "root"),
            $config->get("password", ""),
            $config->get("database", "vk_bot"),
            $config->get("port", 3306)
        );

        if ($this->db->connect_error) {
            $this->getLogger()->error("MySQL connection failed: " . $this->db->connect_error);
            return;
        }

        $this->getServer()->getPluginManager()->registerEvents($this, $this);
        $this->getLogger()->info("\n§e===================\n§fVK§3Manager §aВключен\n§fСигма бой\n§e===================");
    }

    public function onDisable(): void {
        if ($this->db) {
            $this->db->close();
        }
    }

    public function onCommand(CommandSender $sender, Command $command, $label, array $args): bool {
    $commandName = strtolower($command->getName());
    
    switch ($commandName) {
        case "give-acs":
            return $this->GiveAccessCommand($sender, $args);
        
        case "vkcode":
            return $this->VKCodeCommand($sender);
        
        case "force-add":
            return $this->ForceAddCommand($sender, $args);
        
        case "force-delete":
            return $this->ForceDeleteCommand($sender, $args);
        
        case "status-vk":
            return $this->StatusVKCommand($sender, $args);
        
        case "vkm-help":
            return $this->HelpCommand($sender);
        case "vksay":
            return $this->VKSayCommand($sender, $args);
    }
    
    return false;
}

    public function onPlayerCommandPreprocess(PlayerCommandPreprocessEvent $event): void {
        $player = $event->getPlayer();
        $username = strtolower($player->getName());
        $message = $event->getMessage();

        $stmt = $this->db->prepare("SELECT banned, ban_reason, ban_time FROM vk_rcon WHERE nickname = ?");
        if ($stmt === false) {
            $this->logMySqlError("Ошибка подготовки запроса (onPlayerCommandPreprocess): " . $this->db->error);
            return;
        }

        $stmt->bind_param("s", $username);
        if (!$stmt->execute()) {
            $this->logMySqlError("Ошибка выполнения запроса (onPlayerCommandPreprocess): " . $stmt->error);
            $stmt->close();
            return;
        }

        $result = $stmt->get_result();
        $banData = $result->fetch_assoc();
        $stmt->close();

        if ($banData && $banData['banned'] === "YES") {
            $command = strtolower(explode(" ", substr($message, 1))[0]);
            
            if (in_array($command, $this->blacklistCommands)) {
                $event->setCancelled(true);
                $player->sendMessage("§f> §7[§c!§7] §fВаши §eдонатерские возможности §fбыли §cограничены из-за нарушения §fправил сообщества §6Hleb§fCraft.\n §f> §fПричина: §6{$banData['ban_reason']}.\n §f> §fВремя: до §6{$banData['ban_time']} §fMSK");
            }
        }
    }

private function GiveAccessCommand(CommandSender $sender, array $args): bool {
    if (!$sender instanceof ConsoleCommandSender) {
        $sender->sendMessage("§7> §7[§c!§7] §fЭту команду можно выполнять §cтолько §fиз консоли сервера.");
        return true;
    }
    
    if (count($args) > 0 && $this->isProtectedNick($args[0])) {
        $sender->sendMessage("§cERROR: §fВзаимодействие с этим игроком запрещено!");
        return true;
    }

    if (count($args) < 3) {
        $sender->sendMessage("§9INFO: §rИспользование: /give-acs [ник_игрока] [привилегия] [вк_доступ]");
        return true;
    }

    $username = strtolower($args[0]);
    $rank = $args[1];
    $access = $args[2];

    // Валидация привилегии
    if (!in_array($rank, $this->allowedRanks)) {
        $sender->sendMessage("§cERROR: §rНедопустимая прива $rank. Допустимые привы: " . implode(", ", $this->allowedRanks));
        return true;
    }
    
    // Валидация уровня доступа
    if (!in_array($access, $this->allowedAccess)) {
        $sender->sendMessage("§cERROR: §rНедопустимый уровень доступа $access. \nДопустимые доступы: " . implode(", ", $this->allowedAccess));
        return true;
    }

    // Проверка пользователя в vk_links
    $stmt = $this->db->prepare("SELECT vk_id, link FROM vk_links WHERE username = ?");
    if ($stmt === false) {
        $this->logMySqlError("Ошибка подготовки запроса (vk_links): " . $this->db->error);
        $sender->sendMessage("§cERROR: §rВнутренняя ошибка сервера");
        return true;
    }
    
    $stmt->bind_param("s", $username);
    if (!$stmt->execute()) {
        $this->logMySqlError("Ошибка выполнения запроса (vk_links): " . $stmt->error);
        $sender->sendMessage("§cERROR: §rОшибка базы данных");
        $stmt->close();
        return true;
    }
    
    $result = $stmt->get_result();
    if ($result->num_rows === 0) {
        $sender->sendMessage("§cERROR: §rИгрок с ником $username не найден в базе данных.");
        $this->sendVkGroupMessage("⚠️ | Выдача не произошла.\nНикнейм не найден в базе данных.\nНик: $username\nРанг: $rank\nВК доступ: $access");
        $stmt->close();
        return true;
    }

    $row = $result->fetch_assoc();
    $stmt->close();

    if ($row['link'] !== "YES") {
        $this->sendVkGroupMessage("⚠️ | Выдача не произошла.\nНикнейм не привязан к ВК.\nНик: $username\nРанг: $rank\nВК доступ: $access.");
        $sender->sendMessage("§cERROR: §rИгрок $username не привязан к ВК. Выдача не произошла.");
        return true;
    }

    // Вставка или обновление записи в vk_rcon
    $stmt = $this->db->prepare("INSERT INTO vk_rcon (nickname, `rank`) VALUES (?, ?) 
                                ON DUPLICATE KEY UPDATE `rank` = VALUES(`rank`)");
    if ($stmt === false) {
        $this->logMySqlError("Ошибка подготовки запроса (vk_rcon): " . $this->db->error);
        $sender->sendMessage("§cERROR: §rВнутренняя ошибка сервера");
        return true;
    }
    
    $stmt->bind_param("ss", $username, $access);
    if (!$stmt->execute()) {
        $this->logMySqlError("Ошибка выполнения запроса (vk_rcon): " . $stmt->error);
        $sender->sendMessage("§cERROR: §rОшибка при сохранении данных");
        $stmt->close();
        return true;
    }
    $stmt->close();

    // Выполнение команды
    $this->getServer()->dispatchCommand(new ConsoleCommandSender(), "setgroup $username $rank");

    // Отправка сообщений
    $privilege = $this->getPrivilegeName($rank);
    $dostyp = $this->getAccessName($access);
    $this->sendVkMessage($row['vk_id'], "❤ | Спасибо за покупку!\n👑 | $username, вам была успешно выдана привилегия $privilege и $dostyp уровень ВК консоли!\n".$this->getHelpMessage($access));
    $sender->sendMessage("§aSUCCESS: §rИгроку $username успешно выдана привилегия $privilege и вк доступ $access.");
    
    return true;
}

private function ForceAddCommand(CommandSender $sender, array $args): bool {
    if (!$sender instanceof ConsoleCommandSender) {
        $sender->sendMessage("§7> §7[§c!§7] §fЭту команду можно выполнять §cтолько §fиз консоли сервера.");
        return true;
    }

    if (count($args) < 2) {
        $sender->sendMessage("§9INFO: §rИспользование: /force-add [никнейм] [Вк_айди]");
        return true;
    }
    
    if (count($args) > 0 && $this->isProtectedNick($args[0])) {
        $sender->sendMessage("§cERROR: §fВзаимодействие с этим игроком запрещено!");
        return true;
    }

    $username = strtolower($args[0]);
    $vk_id = $args[1];

    // Проверка существующих записей
    $checkStmt = $this->db->prepare("SELECT * FROM vk_links WHERE username = ?");
    if ($checkStmt === false) {
        $this->logMySqlError("Ошибка подготовки запроса (force-add): " . $this->db->error);
        $sender->sendMessage("§cERROR: §rВнутренняя ошибка сервера");
        return true;
    }
    
    $checkStmt->bind_param("s", $username);
    if (!$checkStmt->execute()) {
        $this->logMySqlError("Ошибка выполнения запроса (force-add): " . $checkStmt->error);
        $sender->sendMessage("§cERROR: §rОшибка базы данных");
        $checkStmt->close();
        return true;
    }
    
    if ($checkStmt->get_result()->num_rows > 0) {
        $sender->sendMessage("§cERROR: §rИгрок с ником $username уже существует!");
        $checkStmt->close();
        return true;
    }
    $checkStmt->close();

    // Вставка новой записи
    $insertStmt = $this->db->prepare("INSERT INTO vk_links (username, vk_id, link) VALUES (?, ?, 'YES')");
    if ($insertStmt === false) {
        $this->logMySqlError("Ошибка подготовки запроса (force-add): " . $this->db->error);
        $sender->sendMessage("§cERROR: §rВнутренняя ошибка сервера");
        return true;
    }
    
    $insertStmt->bind_param("ss", $username, $vk_id);
    if ($insertStmt->execute()) {
        $sender->sendMessage("§aSUCCESS: §rИгрок $username с ВК ID $vk_id успешно привязан к боту!");
    } else {
        $this->logMySqlError("Ошибка выполнения запроса (force-add): " . $insertStmt->error);
        $sender->sendMessage("§cERROR: §rОшибка при добавлении в базу: §e" . $insertStmt->error);
    }
    
    $insertStmt->close();
    return true;
}

private function VKCodeCommand(CommandSender $sender): bool {
        if (!$sender instanceof Player) {
            $sender->sendMessage("§cERROR: §rЭту команду может использовать только игрок.");
            return true;
        }

        $playerName = $sender->getName();
        
        // Проверка кулдауна
        if ($this->isOnCooldown($playerName)) {
            $remaining = $this->getRemainingCooldown($playerName);
            $sender->sendMessage("§f> §aAuth §7• §fВы §cуже получили §fкод для привязки профиля к ВКонтакте\n§f> §aAuth §7• §fПолучить его еще раз вы сможете через - §a" . $remaining);
            return true;
        }

        $this->VKCodeCommandLogic($playerName, $sender);
        return true;
    }

private function ForceDeleteCommand(CommandSender $sender, array $args): bool {
    if (!$sender instanceof ConsoleCommandSender) {
        $sender->sendMessage("§7> §7[§c!§7] §fЭту команду можно выполнять §cтолько §fиз консоли сервера.");
        return true;
    }
    
    if (count($args) > 0 && $this->isProtectedNick($args[0])) {
        $sender->sendMessage("§cERROR: §fВзаимодействие с этим игроком запрещено!");
        return true;
    }

    if (count($args) < 1) {
        $sender->sendMessage("§9INFO: §fИспольз.: /force-delete [никнейм]");
        return true;
    }

    $username = strtolower($args[0]);

    // Проверка существования записи
    $stmt = $this->db->prepare("SELECT * FROM vk_links WHERE username = ?");
    if ($stmt === false) {
        $this->logMySqlError("Ошибка подготовки запроса (force-delete): " . $this->db->error);
        $sender->sendMessage("§cERROR: §rВнутренняя ошибка сервера");
        return true;
    }
    
    $stmt->bind_param("s", $username);
    if (!$stmt->execute()) {
        $this->logMySqlError("Ошибка выполнения запроса (force-delete): " . $stmt->error);
        $sender->sendMessage("§cERROR: §rОшибка базы данных");
        $stmt->close();
        return true;
    }
    
    $result = $stmt->get_result();
    if ($result->num_rows === 0) {
        $sender->sendMessage("§cERROR: §fИгрок §a$username §cне §fнайден в базе данных!");
        $stmt->close();
        return true;
    }
    $stmt->close();

    // Удаление записи
    $deleteStmt = $this->db->prepare("DELETE FROM vk_links WHERE username = ?");
    if ($deleteStmt === false) {
        $this->logMySqlError("Ошибка подготовки запроса (force-delete): " . $this->db->error);
        $sender->sendMessage("§cERROR: §rВнутренняя ошибка сервера");
        return true;
    }
    
    $deleteStmt->bind_param("s", $username);
    if ($deleteStmt->execute()) {
        $sender->sendMessage("§aSUCCESS: §fАккаунт §a$username §fуспешно отвязан от ВК!");
    } else {
        $this->logMySqlError("Ошибка выполнения запроса (force-delete): " . $deleteStmt->error);
        $sender->sendMessage("§cERROR: §fОшибка при удалении: §e" . $deleteStmt->error);
    }
    
    $deleteStmt->close();
    return true;
}

private function StatusVKCommand(CommandSender $sender, array $args): bool {
    if (!$sender instanceof ConsoleCommandSender) {
        $sender->sendMessage("§7> §7[§c!§7] §fЭту команду можно выполнять §cтолько §fиз консоли сервера.");
        return true;
    }
    
    if (count($args) > 0 && $this->isProtectedNick($args[0])) {
        $sender->sendMessage("§cERROR: §fВзаимодействие с этим игроком запрещено!");
        return true;
    }

    if (count($args) < 1) {
        $sender->sendMessage("§9INFO: §fИспольз.: /status-vk [никнейм]");
        return true;
    }

    $username = strtolower($args[0]);

    // Поиск в базе данных
    $stmt = $this->db->prepare("SELECT * FROM vk_links WHERE username = ?");
    if ($stmt === false) {
        $this->logMySqlError("Ошибка подготовки запроса (status-vk): " . $this->db->error);
        $sender->sendMessage("§cERROR: §rВнутренняя ошибка сервера");
        return true;
    }
    
    $stmt->bind_param("s", $username);
    if (!$stmt->execute()) {
        $this->logMySqlError("Ошибка выполнения запроса (status-vk): " . $stmt->error);
        $sender->sendMessage("§cERROR: §rОшибка базы данных");
        $stmt->close();
        return true;
    }
    
    $result = $stmt->get_result();
    if ($result->num_rows === 0) {
        $sender->sendMessage("§cERROR: §fИгрок §a" . $args[0] . "§c не найден в §fбазе данных!");
        $stmt->close();
        return true;
    }

    $data = $result->fetch_assoc();
    $stmt->close();
    
    // Форматирование данных
    $status = "§9Статус привязки §a" . $args[0] . "\n"
            . "§7Ник: §f" . $data['username'] . "\n"
            . "§7VK ID: §f" . ($data['vk_id'] ?? 'не указан') . "\n"
            . "§7VK код: §f" . ($data['vk_code'] ?? 'не сгенерирован') . "\n"
            . "§7Привязан: " . ($data['link'] === 'YES' ? '§aДа' : '§cНет');

    $sender->sendMessage($status);
    return true;
}

private function HelpCommand(CommandSender $sender): bool {
    if (!$sender instanceof ConsoleCommandSender) {
        $sender->sendMessage("§7> §7[§c!§7] §fЭту команду можно выполнять §cтолько §fиз консоли сервера.");
        return true;
    }
    
    $helpMessage = "§aHELP: §fКоманды §9VKM\n"
                 . " §9/give-acs §a[никнейм] [привилегия] [вк_доступ] §f- выдать доступ к ВК консоли (Привязка обязательна).\n"
                 . " §9/vkcode §f- получить уникальный код для привязки.\n"
                 . " §9/force-add §a[никнейм] [вк_айди] §f- принудительно привязать игрока к боту.\n"
                 . " §9/force-delete §a[никнейм] §f- принудительно удалить игрока из бота.\n"
                 . " §9/status-vk §a[никнейм] §f- узнать статус привязки конкретного игрока.\n"
                 . " §9/vkm-help §f- показать это сообщение.";
                 
    $sender->sendMessage($helpMessage);
    return true;
}

private function VKCodeCommandLogic(string $playerName, Player $player): void {
    $playerNameLower = strtolower($playerName);
    $this->setCooldown($playerName);
    $query = "SELECT vk_code, link FROM vk_links WHERE username = ?";
    $stmt = $this->db->prepare($query);
    
    if ($stmt === false) {
        $this->logMySqlError("Ошибка подготовки запроса (vkcode): " . $this->db->error);
        $player->sendMessage("§cПроизошла ошибка при обработке запроса. Попробуйте позже.");
        return;
    }

    $stmt->bind_param("s", $playerNameLower);
    if (!$stmt->execute()) {
        $this->logMySqlError("Ошибка выполнения запроса (vkcode): " . $stmt->error);
        $player->sendMessage("§cПроизошла ошибка базы данных. Попробуйте позже.");
        $stmt->close();
        return;
    }

    $result = $stmt->get_result();
    if ($result === false) {
        $this->logMySqlError("Ошибка получения результата (vkcode): " . $stmt->error);
        $player->sendMessage("§cПроизошла ошибка обработки данных. Попробуйте позже.");
        $stmt->close();
        return;
    }

    if ($result->num_rows > 0) {
        $row = $result->fetch_assoc();
        
        if ($row['link'] === 'YES') {
            $player->sendMessage("§f> §aAuth §7• §fЭтот аккаунт уже привязан к ВК.");
            $player->sendMessage("§f> §aAuth §7• §fЧтобы привязать к новому ВК, зайдите в бота и напишите §c/отвязка");
        } else {
            $existingCode = $row['vk_code'] ?? 'не сгенерирован';
            $player->sendMessage("§f> §aAuth §7• §fВаш код привязки §bВK: §b" . $existingCode);
            $player->sendMessage($this->getInstructionMessage());
        }
    } else {
        $code = $this->generateCode();
        $player->sendMessage("§f> §aAuth §7• §fВаш код привязки §bВK: §b" . $code);
        $player->sendMessage($this->getInstructionMessage());
        $this->saveCode($playerNameLower, $code);
    }

    $stmt->close();
}

private function VKSayCommand(CommandSender $sender, array $args): bool {
    if (!$sender instanceof ConsoleCommandSender) {
        $sender->sendMessage("§7> §7[§c!§7] §fЭту команду можно выполнять §cтолько §fиз консоли сервера.");
        return true;
    }

    if (count($args) < 1) {
        $sender->sendMessage("§9INFO: §fИспользование: /vksay [сообщение]");
        return true;
    }

    $message = implode(" ", $args);
    $this->getServer()->broadcastMessage($message);
    $sender->sendMessage("§aSUCCESS: §9Сообщение §fуспешно отправлено на сервер!");
    return true;
}


    // ВСПОМОГАТЕЛЬНЫЕ МЕТОДЫ //

    /**
     * Проверяет, находится ли игрок на кулдауне
     */
    private function isOnCooldown(string $playerName): bool {
        $playerNameLower = strtolower($playerName);
        return isset($this->cooldowns[$playerNameLower]) && 
               (time() - $this->cooldowns[$playerNameLower]) < 600; // 600 секунд = 10 минут
    }

    /**
     * Устанавливает время последнего использования команды
     */
    private function setCooldown(string $playerName): void {
        $playerNameLower = strtolower($playerName);
        $this->cooldowns[$playerNameLower] = time();
    }

    /**
     * Возвращает оставшееся время кулдауна в формате М:СС
     */
    private function getRemainingCooldown(string $playerName): string {
        $playerNameLower = strtolower($playerName);
        if (!isset($this->cooldowns[$playerNameLower])) {
            return "0:00";
        }

        $remaining = 600 - (time() - $this->cooldowns[$playerNameLower]);
        $minutes = floor($remaining / 60);
        $seconds = $remaining % 60;
        
        return sprintf("%d:%02d", $minutes, $seconds);
    }
    
    private function isProtectedNick(string $nick): bool {
        return in_array(strtolower($nick), $this->protectedNicks);
    }
    
    private function generateCode(): string {
        $characters = 'ABCDEFGHKMNPQRSTUVWXYZabcdefghkmnpqrstuvwxyz123456789';
        $code = '';
        $length = strlen($characters);
        for ($i = 0; $i < 8; $i++) {
            $code .= $characters[mt_rand(0, $length - 1)];
        }
        return $code;
    }

    private function saveCode(string $playerName, string $code): void {
        $playerNameLower = strtolower($playerName);
        $stmt = $this->db->prepare("INSERT INTO vk_links (username, vk_code) VALUES (?, ?)");
        
        if ($stmt === false) {
            $this->logMySqlError("Ошибка подготовки запроса (saveCode): " . $this->db->error);
            return;
        }
        
        $stmt->bind_param("ss", $playerNameLower, $code);
        if (!$stmt->execute()) {
            $this->logMySqlError("Ошибка выполнения запроса (saveCode): " . $stmt->error);
        }
        $stmt->close();
    }

    private function getInstructionMessage(): string {
        return "§f> §aAuth §7• §fИнструкция по привязке §aаккаунта §fк §bВK §fсообществу! \n §f1. Найти в §bBK §fсообщество @hleb_craft\n §f2. Написать боту §a!привязка [ник] [код] §fи ваш аккаунт будет привязан к §bВК";
    }

    private function getPrivilegeName(string $rank): string {
        $privileges = [
            "SeniorAdmin" => "Главный Администратор",
            "Administrator" => "Администратор",
            "admsrv" => "Администратор+",
            "Developer" => "Разработчик",
            "GlConsole" => "Главная Консоль",
            "Console" => "Консоль"
        ];
        return $privileges[$rank] ?? "";
    }
    
    private function getAccessName (string $access): string {
        $accesses = [
            "1" => "1 уровень",
            "2" => "2 уровень",
            "3" => "3 уровень",
            "4" => "4 уровень",
            "5" => "5 уровень"];
            return $accesses[$access] ?? "";
    }

    private function getHelpMessage(string $access): string {
        return in_array($access, ["2", "3", "4", "5"]) 
            ? "📰 | !помощь\n💠 | !помощь админ" 
            : "📰 | !помощь";
    }

    private function sendVkMessage($vk_id, $message) {
        if (empty($this->vkAccessToken)) {
            $this->getLogger()->warning("Не установлен VK access token!");
            return;
        }

        $randomId = rand(100000, 1e6);
        $requestParams = [
            'user_id' => $vk_id,
            'message' => $message,
            'random_id' => $randomId,
            'access_token' => $this->vkAccessToken,
            'v' => '5.131'
        ];

        $this->sendVkRequest('https://api.vk.com/method/messages.send', $requestParams);
    }

    private function sendVkGroupMessage($message) {
        if (empty($this->vkAccessToken) || empty($this->vkOwnerId)) {
            $this->getLogger()->warning("Не установлен VK token или owner ID!");
            return;
        }

        $randomId = rand(100000, 1e6);
        $requestParams = [
            'user_id' => $this->vkOwnerId,
            'message' => $message,
            'random_id' => $randomId,
            'access_token' => $this->vkAccessToken,
            'v' => '5.131'
        ];

        $this->sendVkRequest('https://api.vk.com/method/messages.send', $requestParams);
    }
    
    private function sendVkRequest($url, $params) {
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($params));
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
        curl_setopt($ch, CURLOPT_TIMEOUT, 5);

        $response = curl_exec($ch);
        if ($response === false) {
            $this->getLogger()->error('Ошибка cURL: ' . curl_error($ch));
        }
        curl_close($ch);
    }
    
    private function sendVkUserMessage(string $username, string $message): void {
    $stmt = $this->db->prepare("SELECT vk_id FROM vk_links WHERE username = ?");
    if ($stmt === false) {
        $this->logMySqlError("Ошибка подготовки запроса (sendVkUserMessage): " . $this->db->error);
        return;
    }
    
    $stmt->bind_param("s", $username);
    if (!$stmt->execute()) {
        $this->logMySqlError("Ошибка выполнения запроса (sendVkUserMessage): " . $stmt->error);
        $stmt->close();
        return;
    }
    
    $result = $stmt->get_result();
    if ($result->num_rows > 0) {
        $row = $result->fetch_assoc();
        $this->sendVkMessage($row['vk_id'], $message);
    }
    $stmt->close();
}
    
    private function logMySqlError(string $message): void {
        $this->getLogger()->error("Ошибка MySQL: " . $message);
    }
}