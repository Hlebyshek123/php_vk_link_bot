<?php

namespace avoidmefull;

use pocketmine\command\{Command, CommandSender};
use pocketmine\event\Listener;
use pocketmine\event\player\{PlayerJoinEvent, PlayerQuitEvent};
use pocketmine\Player;
use pocketmine\plugin\PluginBase;
use mysqli;

class playtime extends PluginBase implements Listener
{
    public $connection;
    private $times = [];
    private static $instance = null;

    public function onEnable()
    {
        self::$instance = $this;
        $this->getServer()->getPluginManager()->registerEvents($this, $this);
        $this->getLogger()->info("\n§e===================\n§aPlay§fTime §aВключен\n§fLet's play!\n§e===================");
        $this->initDatabase();
    }

    private function initDatabase()
    {
        $this->connection = new mysqli("178.236.243.51", "hlebuser", "hRN-V2Fv!Bt_Zb@", "server_data");
        
        if ($this->connection->connect_error) {
            $this->getLogger()->alert("Ошибка подключения: " . $this->connection->connect_error);
            return;
        }

        $query = "CREATE TABLE IF NOT EXISTS `playtime` (
            `nickname` VARCHAR(64) UNIQUE NOT NULL,
            `time` INT NOT NULL DEFAULT 0,
            `session_time` INT NOT NULL DEFAULT 0
        )";
        
        if (!$this->connection->query($query)) {
            $this->getLogger()->alert("Ошибка создания таблицы: " . $this->connection->error);
        }
    }

    private function ensureConnection() {
        if (!($this->connection instanceof \mysqli) || !$this->connection->ping()) {
            $this->getLogger()->warning("Соединение с MySQL разорвано, переподключаемся...");
            $this->connection->close();
            $this->initDatabase();
        }
    }

    public static function getInstance()
    {
        return self::$instance;
    }

    public function onDisable()
    {
        if ($this->connection) {
            foreach ($this->times as $name => $time) {
                $this->setTime($name, time() - $time, time() - $time);
            }
            $this->connection->close();
        }
    }

    public function onJoin(PlayerJoinEvent $e)
    {
        $p = $e->getPlayer();
        $name = strtolower($p->getName());
        
        $this->ensureConnection();
        
        if (!$this->connection || $this->connection->connect_error) {
            $this->getLogger()->error("Нет соединения с БД");
            return;
        }
        
        if (!$this->hasTime($name)) {
            $this->registerTime($name, 0, 0);
        }
        
        $this->times[$name] = time();
    }

    public function onQuit(PlayerQuitEvent $e)
    {
        $p = $e->getPlayer();
        $name = strtolower($p->getName());
        
        if (isset($this->times[$name])) {
            $this->savePlayTime($p);
            unset($this->times[$name]);
        }
    }

    private function savePlayTime(Player $player)
    {
        $name = strtolower($player->getName());
        if (isset($this->times[$name])) {
            $this->setTime($name, time() - $this->times[$name], time() - $this->times[$name]);
        }
    }

    public function onCommand(CommandSender $sender, Command $cmd, $label, array $args): bool
    {
        if ($cmd->getName() === "playtime") {
            $this->ensureConnection();
            
            if (!$this->connection || $this->connection->connect_error) {
                $sender->sendMessage("§cОшибка подключения к базе данных");
                return true;
            }

            if (empty($args)) {
                if ($sender instanceof Player) {
                    $this->showPlayerTime($sender);
                } else {
                    $sender->sendMessage("Используйте: /playtime <get|reset|top>");
                }
                return true;
            }

            if (!$sender->isOp()) {
                $sender->sendMessage("§cНедостаточно прав!");
                return true;
            }

            switch (strtolower($args[0])) {
                case "get":
                    $this->handleGetCommand($sender, $args);
                    break;
                case "reset":
                    $this->handleResetCommand($sender, $args);
                    break;
                case "top":
                    $this->handleTopCommand($sender, $args);
                    break;
                default:
                    $sender->sendMessage("Используйте: /playtime <get|reset|top>");
            }
            return true;
        }
        return false;
    }

    private function showPlayerTime(Player $player)
    {
        $name = strtolower($player->getName());
        $session = time() - $this->times[$name];
        $last_session = $this->getSessionTime($name);
        $total = $this->getTotalTime($name);

        $message = "§7(§eОнлайн§7) §fВаше игровое время:\n" .
                   " §e» §fОбщее время: §a" . $this->formatTime($total) . "\n" .
                   " §e» §fПрошлая сессия: §a" . $this->formatTime($last_session) . "\n" .
                   " §e» §fТекущая сессия: §a" . $this->formatTime($session);
        $player->sendMessage($message);
    }

    private function handleGetCommand(CommandSender $sender, array $args)
    {
        if (count($args) < 2) {
            $sender->sendMessage("Используйте: /playtime get <никнейм>");
            return;
        }

        $name = strtolower($args[1]);
        if (!$this->hasTime($name)) {
            $sender->sendMessage("§cИгрок не найден");
            return;
        }

        $total = $this->getTotalTime($name);
        $last_session = $this->getSessionTime($name);
        $session = $this->getServer()->getPlayer($name) ? time() - $this->times[$name] : null;

        $message = "§7(§eОнлайн§7) §fДанные игрока §e{$name}§f:\n" .
                   " §e» §fОбщее время: §a" . $this->formatTime($total) . "\n" .
                   " §e» §fПрошлая сессия: §a" . $this->formatTime($last_session) . "\n" .
                   " §e» §fТекущая сессия: " . ($session ? "§a" . $this->formatTime($session) : "§cне в сети");
        $sender->sendMessage($message);
    }

    private function handleResetCommand(CommandSender $sender, array $args)
    {
        if (count($args) < 2) {
            $sender->sendMessage("Используйте: /playtime reset <никнейм>");
            return;
        }

        $name = strtolower($args[1]);
        if ($this->hasTime($name)) {
            $this->registerTime($name, 0, 0);
            if ($player = $this->getServer()->getPlayer($name)) {
                $this->times[strtolower($name)] = time();
            }
            $sender->sendMessage("§aДанные игрока {$name} сброшены");
        } else {
            $sender->sendMessage("§cИгрок не найден");
        }
    }

    private function handleTopCommand(CommandSender $sender, array $args)
    {
        $result = $this->connection->query("SELECT * FROM `playtime` ORDER BY `time` DESC LIMIT 10");
        if ($result === false) {
            $this->logMySqlError("Ошибка запроса (handleTopCommand): " . $this->connection->error);
            $sender->sendMessage("§cОшибка при получении топа");
            return;
        }
        
        $message = "§7(§eТоп игроков§7)\n";
        $position = 1;

        while ($row = $result->fetch_assoc()) {
            $time = $row['time'] + (isset($this->times[$row['nickname']]) ? time() - $this->times[$row['nickname']] : 0);
            $message .= "§e{$position}. §f{$row['nickname']} - §a" . $this->formatTime($time) . "\n";
            $position++;
        }

        $sender->sendMessage($message);
    }

    private function registerTime(string $name, int $time, int $session) {
        $this->ensureConnection();
        $query = "INSERT INTO playtime (nickname, time, session_time) VALUES (?, ?, ?) 
            ON DUPLICATE KEY UPDATE time=VALUES(time), session_time=VALUES(session_time)";
        $stmt = $this->connection->prepare($query);
        
        if ($stmt === false) {
            $this->logMySqlError("Ошибка подготовки запроса (registerTime): " . $this->connection->error);
            return;
        }
        
        $stmt->bind_param("sii", $name, $time, $session);
        if (!$stmt->execute()) {
            $this->logMySqlError("Ошибка выполнения запроса (registerTime): " . $stmt->error);
        }
        $stmt->close();
    }

    private function hasTime(string $name): bool {
        $this->ensureConnection();
        $query = "SELECT nickname FROM playtime WHERE nickname = ?";
        $stmt = $this->connection->prepare($query);
        
        if ($stmt === false) {
            $this->logMySqlError("Ошибка подготовки запроса (hasTime): " . $this->connection->error);
            return false;
        }
        
        $stmt->bind_param("s", $name);
        if (!$stmt->execute()) {
            $this->logMySqlError("Ошибка выполнения запроса (hasTime): " . $stmt->error);
            $stmt->close();
            return false;
        }
        
        $result = $stmt->get_result();
        $exists = $result->num_rows > 0;
        $stmt->close();
        return $exists;
    }

    private function setTime(string $name, int $time, int $session) {
        $this->ensureConnection();
        $query = "UPDATE playtime SET time = time + ?, session_time = ? WHERE nickname = ?";
        $stmt = $this->connection->prepare($query);
        
        if ($stmt === false) {
            $this->logMySqlError("Ошибка подготовки запроса (setTime): " . $this->connection->error);
            return;
        }
        
        $stmt->bind_param("iis", $time, $session, $name);
        if (!$stmt->execute()) {
            $this->logMySqlError("Ошибка выполнения запроса (setTime): " . $stmt->error);
        }
        $stmt->close();
    }

    private function getTime(string $name): int {
        $this->ensureConnection();
        $query = "SELECT time FROM playtime WHERE nickname = ?";
        $stmt = $this->connection->prepare($query);
        
        if ($stmt === false) {
            $this->logMySqlError("Ошибка подготовки запроса (getTime): " . $this->connection->error);
            return 0;
        }
        
        $stmt->bind_param("s", $name);
        if (!$stmt->execute()) {
            $this->logMySqlError("Ошибка выполнения запроса (getTime): " . $stmt->error);
            $stmt->close();
            return 0;
        }
        
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();
        $time = $row ? (int)$row['time'] : 0;
        $stmt->close();
        return $time;
    }

    private function getSessionTime(string $name): int {
        $this->ensureConnection();
        $query = "SELECT session_time FROM playtime WHERE nickname = ?";
        $stmt = $this->connection->prepare($query);
        
        if ($stmt === false) {
            $this->logMySqlError("Ошибка подготовки запроса (getSessionTime): " . $this->connection->error);
            return 0;
        }
        
        $stmt->bind_param("s", $name);
        if (!$stmt->execute()) {
            $this->logMySqlError("Ошибка выполнения запроса (getSessionTime): " . $stmt->error);
            $stmt->close();
            return 0;
        }
        
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();
        $time = $row ? (int)$row['session_time'] : 0;
        $stmt->close();
        return $time;
    }

    public function getTotalTime(string $name): int {
        $total = $this->getTime($name);
        if (isset($this->times[$name])) {
            $total += time() - $this->times[$name];
        }
        return $total;
    }

    private function formatTime(int $seconds): string {
        $hours = floor($seconds / 3600);
        $minutes = floor(($seconds % 3600) / 60);
        $seconds = $seconds % 60;
        
        $parts = [];
        if ($hours > 0) $parts[] = "{$hours} ч";
        if ($minutes > 0) $parts[] = "{$minutes} м";
        if ($seconds > 0 || empty($parts)) $parts[] = "{$seconds}с";
        
        return implode(" ", $parts);
    }
    
    private function logMySqlError(string $message): void {
        $this->getLogger()->error("Ошибка MySQL: " . $message);
    }
}