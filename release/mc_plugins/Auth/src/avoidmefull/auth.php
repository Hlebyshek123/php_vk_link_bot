н<?php

namespace avoidmefull;

use pocketmine\command\{Command, CommandSender};
use pocketmine\event\block\{BlockBreakEvent, BlockPlaceEvent};
use pocketmine\event\entity\{EntityDamageByEntityEvent, EntityDamageEvent};
use pocketmine\event\Listener;
use pocketmine\event\player\{PlayerChatEvent,
    PlayerCommandPreprocessEvent,
    PlayerDropItemEvent,
    PlayerInteractEvent,
    PlayerJoinEvent,
    PlayerMoveEvent,
    PlayerPreLoginEvent,
    PlayerQuitEvent
};
use pocketmine\Player;
use pocketmine\plugin\PluginBase;
use pocketmine\scheduler\CallbackTask;
use pocketmine\utils\Config;
use AllowDynamicProperties;
use mysqli;
use Exception;

class auth extends PluginBase implements Listener
{
    
    private $data;
    private $players;
    private $register = [], $attempts = [], $auth_timeout = [];

    public $users = [];

    public function onEnable()
    {
        $this->getServer()->getPluginManager()->registerEvents($this, $this);

        $this->mysqlConnect();
        $this->checkDatabase();

        $folder = "/servers/linux/plugins/Auth/";
        if (!is_dir($folder)) @mkdir($folder);
        $this->players = new Config($folder . "players.json", Config::JSON);

        $this->getServer()->getScheduler()->scheduleRepeatingTask(new CallbackTask([$this, "addTitle"], []), 20);
        $this->checkPlayers();
    }

    public function onDisable()
    {
        foreach ($this->players->getAll() as $name => $port) {
            if ($port == $this->getServer()->getPort()) {
                $this->players->remove($name);
                $this->players->save();
            }
        }

        if ($this->data) $this->data->close();
    }

    private function mysqlConnect()
    {
        $config = [
            'host' => "178.51",
            'user' => "hlser",
            'pass' => "hR@",
            'db'   => "server_data"
        ];
    
        $this->data = new \mysqli(
            $config['host'],
            $config['user'],
            $config['pass'],
            $config['db']
        );

        if ($this->data->connect_error) {
            $this->logMySqlError("Ошибка подключения: " . $this->data->connect_error);
            $this->data = null;
        }
    }

    public function getMySQLConnection()
    {
        // Если подключение уже существует
        if ($this->data instanceof \mysqli) {
            // Проверяем живость соединения через ping()
            if ($this->data->ping()) {
                return true;
            }
            
            // Если соединение мертвое, пересоздаём
            $this->mysqlConnect();
        } else {
            // Создаём новое подключение если его нет
            $this->mysqlConnect();
        }

        // Проверяем результат подключения
        return $this->data !== null;
    }

    private function checkDatabase()
    {
        if ($this->data) {
            $query = "CREATE TABLE IF NOT EXISTS `users` (
                `nickname` VARCHAR(32) PRIMARY KEY,
                `password` VARCHAR(128) NOT NULL,
                `reg_ip` VARCHAR(45) NOT NULL,
                `last_ip` VARCHAR(45) NOT NULL,
                `last_device` VARCHAR(64) DEFAULT '',
                `last_port` INT DEFAULT 0,
                `last_date` INT DEFAULT 0
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8;";
            
            if (!$this->data->query($query)) {
                $this->logMySqlError("Ошибка создания таблицы users: " . $this->data->error);
            }
        }
    }

    public function registerUser(string $name, string $password, string $ip, string $device = "", int $port = 0, int $date = 0): bool
    {
        if (!$this->data) {
            $this->logMySqlError("Попытка регистрации без подключения к БД");
            return false;
        }

        $stmt = $this->data->prepare("INSERT INTO users (nickname, password, reg_ip, last_ip, last_device, last_port, last_date) 
            VALUES (?, ?, ?, ?, ?, ?, ?) ON DUPLICATE KEY UPDATE password=VALUES(password), last_ip=VALUES(last_ip)");
        
        if ($stmt === false) {
            $this->logMySqlError("Ошибка подготовки запроса (registerUser): " . $this->data->error);
            return false;
        }
        
        $hashed = $password;
        $stmt->bind_param("ssssssi", 
            $name,
            $hashed,
            $ip,
            $ip,
            $device,
            $port,
            $date
        );

        $result = $stmt->execute();
        if (!$result) {
            $this->logMySqlError("Ошибка выполнения запроса (registerUser): " . $stmt->error);
        }
        
        $stmt->close();
        return $result;
    }

    public function getUser(string $name): array
    {
        if (!$this->data) {
            $this->logMySqlError("Попытка получения пользователя без подключения к БД");
            return [];
        }

        $stmt = $this->data->prepare("SELECT * FROM users WHERE nickname = ?");
        if ($stmt === false) {
            $this->logMySqlError("Ошибка подготовки запроса (getUser): " . $this->data->error);
            return [];
        }
        
        $stmt->bind_param("s", $name);
        if (!$stmt->execute()) {
            $this->logMySqlError("Ошибка выполнения запроса (getUser): " . $stmt->error);
            $stmt->close();
            return [];
        }
        
        $result = $stmt->get_result();
        $data = $result->fetch_assoc() ?? [];
        $stmt->close();
        return $data;
    }

    public function setUserAuthInfo(string $name, string $ip, string $device, int $port, int $date): bool
    {
        if (!$this->data) {
            $this->logMySqlError("Попытка обновления данных без подключения к БД");
            return false;
        }

        $stmt = $this->data->prepare("UPDATE users SET last_ip = ?, last_device = ?, last_port = ?, last_date = ? WHERE nickname = ?");
        if ($stmt === false) {
            $this->logMySqlError("Ошибка подготовки запроса (setUserAuthInfo): " . $this->data->error);
            return false;
        }
        
        $stmt->bind_param("ssiis", $ip, $device, $port, $date, $name);
        $result = $stmt->execute();
        if (!$result) {
            $this->logMySqlError("Ошибка выполнения запроса (setUserAuthInfo): " . $stmt->error);
        }
        
        $stmt->close();
        return $result;
    }
    
    public function getCountUsers() {
        $count = 0;

        // Проверка подключения
        if (!$this->data || $this->data->connect_error) {
            $this->logMySqlError("Нет подключения к базе данных (getCountUsers)");
            return 0;
        }

        try {
            // Подготовка запроса
            $stmt = $this->data->prepare("SELECT COUNT(*) AS user_count FROM `users`");
            if (!$stmt) {
                throw new Exception("Ошибка подготовки запроса: " . $this->data->error);
            }

            // Выполнение запроса
            if (!$stmt->execute()) {
                throw new Exception("Ошибка выполнения запроса: " . $stmt->error);
            }

            // Получение результата
            $result = $stmt->get_result();
            if ($result->num_rows > 0) {
                $row = $result->fetch_assoc();
                $count = $row['user_count'];
            }

            // Закрытие запроса
            $stmt->close();

        } catch (Exception $e) {
            $this->logMySqlError($e->getMessage());
            return 0;
        }

        return $count;
    }

    public function addTitle()
    {
        foreach ($this->getServer()->getOnlinePlayers() as $pls) {
            if ($pls->isOnline()) {
                if (isset($this->auth_timeout[$pls->getName()])) {
                    $pls->addTitle("§l§aАвторизация", "§l§fОткройте чат и введите пароль", 0, 30, 0);
                }
            }
        }
    }

    public function checkPlayers()
    {
        foreach ($this->players->getAll() as $name => $port) {
            if ($port == $this->getServer()->getPort()) {
                $value = false;
                foreach ($this->getServer()->getOnlinePlayers() as $pls) {
                    if (strtolower($pls->getName()) == $name) {
                        $value = true;
                        break;
                    }
                }
                if ($value)
                    continue;
                $this->players->remove($name);
                $this->players->save();
            }
        }
    }

    public function setAuthTimer(Player $target)
    {
        $this->getServer()->getScheduler()->scheduleDelayedTask($task = new CallbackTask(array($target, "close"), array("Время авторизации вышло", "§aAuth §8• §fВремя для авторизации аккаунта вышло", true)), 20 * 75);
        $this->auth_timeout[$target->getName()] = $task->getTaskId();
    }

    public function successfulAuth(Player $target)
    {
        if (isset($this->auth_timeout[$target->getName()])) {
            $this->getServer()->getScheduler()->cancelTask($this->auth_timeout[$target->getName()]);
            unset($this->auth_timeout[$target->getName()]);
        }
        $target->addTitle("§a§lМой§fСервер", "§l§a" . $this->getServer()->getPort(), 10, 60, 10);
    }

    public function onChat(PlayerChatEvent $e)
    {
        if (!isset($this->users[strtolower($e->getPlayer()->getName())])) {
            $e->setCancelled(true);
            return true;
        }
        $p = $e->getPlayer();
        $nickname = strtolower($p->getName());
        $message = $e->getMessage();
        $password = explode(" ", $message)[0];
        $result = $this->getUser($nickname);
        $password = $password;
        if ($password === $result["password"]) {
            $e->setCancelled(true);
        }
        return true;
    }

    public function onBreak(BlockBreakEvent $e)
    {
        if (!isset($this->users[strtolower($e->getPlayer()->getName())]))
            $e->setCancelled(true);
    }

    public function onPlace(BlockPlaceEvent $e)
    {
        if (!isset($this->users[strtolower($e->getPlayer()->getName())]))
            $e->setCancelled(true);
    }

    public function onDrop(PlayerDropItemEvent $e)
    {
        if (!isset($this->users[strtolower($e->getPlayer()->getName())]))
            $e->setCancelled(true);
    }

    /**
     * @param PlayerInteractEvent $e
     *
     * @priority        LOWEST
     *
     */
    public function onInteract(PlayerInteractEvent $e)
    {
        if (!isset($this->users[strtolower($e->getPlayer()->getName())]))
            $e->setCancelled(true);
    }

    public function onMove(PlayerMoveEvent $e)
    {
        if (!isset($this->users[strtolower($e->getPlayer()->getName())]))
            $e->setCancelled(true);
    }


    public function onDamage(EntityDamageEvent $e)
    {
        if ($e instanceof EntityDamageByEntityEvent) {
            $p = $e->getEntity();
            if ($p instanceof Player) {
                $damager = $e->getDamager();
                if ($damager instanceof Player) {
                    if (!isset($this->users[strtolower($p->getName())]) || !isset($this->users[strtolower($damager->getName())])) {
                        $e->setCancelled();
                    }
                }
            }
        }
    }

    public function onPreLogin(PlayerPreLoginEvent $e)
    {

        $p = $e->getPlayer();

        if (!$this->getMySQLConnection()) {
            $p->close("Не удалось подключиться к базе данных", "§aAuth §8• §fПроизошла ошибка при подключении к базе данных \n§aAuth §8• §fПросим обратиться в тех.поддержку сервера");
            return true;
        }
        /*
        $this->players->reload();
        if(isset($this->players->getAll()[strtolower($p->getName())])){
            $e->setCancelled(true);
            $e->setKickMessage("§fВаш персонаж §aуже находится §fв игре!");
        }else{
            $this->players->set(strtolower($p->getName()), $this->getServer()->getPort());
            $this->players->save();
        }*/
        return true;

    }

    /**
     * @param PlayerJoinEvent $event
     *
     * @priority LOWEST
     */
    public function onJoin(PlayerJoinEvent $e)
    {

        $e->setJoinMessage("");

        $p = $e->getPlayer();

        $p->setImmobile(true);

        $p->sendMessage("§6• §fДобро пожаловать на проект §l§o§aМой§fСервер§r§f! §r§fСпасибо, что выбрали наши сервера! \n\n§6• §fГруппа сервера§7: §6@my_server §7| §fСайт сервера§7: §6shop.sosal.org");

        $result = $this->getUser($p->getName());
        if (isset($result["password"]) && $result["password"] != null) {
            $address = $p->getAddress();
            if (isset($result["last_ip"]) && $address == $result["last_ip"]) {
                $p->setImmobile(false);
                $this->users[strtolower($p->getName())] = ["ip" => $address];
                $this->setUserAuthInfo($p->getName(), $address, $p->getDeviceModel(), $this->getServer()->getPort(), time());
                $this->getServer()->getScheduler()->scheduleDelayedTask(new CallbackTask(array($this, "successfulAuth"), array($p)), 10);
                $p->sendMessage("\n§a► §fВы авторизовались автоматически, приятной игры!");
                $p->sendMessage("§6! §fВы так же можете поставить защиту, дабы обезопасить ваш аккаунт §7- §6/2fa [skin-on|cid-on]\n§6! §fПривязать аккаунт к сообществу в ВК §7- §6/vkcode");
            } else {
                $this->attempts[strtolower($p->getName())] = 0;
                $this->setAuthTimer($p);
                $p->sendMessage("\n§b► §fПожалуйста, авторизуйтесь, введя §bпароль §fв чат");
                $p->sendMessage("§6! §fЕсли вы зашли впервые, то §6смените §fникнейм на любой другой");
            }
        } else {
            if (!isset($this->attempts[strtolower($p->getName())]))
                $this->attempts[strtolower($p->getName())] = 1;
            $this->setAuthTimer($p);
            $p->sendMessage("\n§b► §fЧтобы зарегистрироваться, придумайте §bпароль §fи введите его в чат");
        }

    }

    public function onQuit(PlayerQuitEvent $e)
    {

        $e->setQuitMessage("");

        $p = $e->getPlayer();

        $this->players->reload();
        if (isset($this->players->getAll()[strtolower($p->getName())])) {
            $this->players->remove(strtolower($p->getName()));
            $this->players->save();
        }

        if (isset($this->users[strtolower($p->getName())]))
            unset($this->users[strtolower($p->getName())]);

        if (isset($this->register[strtolower($p->getName())]))
            unset($this->register[strtolower($p->getName())]);

        if (isset($this->auth_timeout[$p->getName()])) {
            $this->getServer()->getScheduler()->cancelTask($this->auth_timeout[$p->getName()]);
            unset($this->auth_timeout[$p->getName()]);
        }

        if ($p->hasEffect(15))
            $p->removeEffect(15);
    }

    /**
     *
     * @param PlayerCommandPreprocessEvent $e
     * @priority LOWEST
     *
     */
    public function onCommandPreprocess(PlayerCommandPreprocessEvent $e): bool
    {

        $message = $e->getMessage();

        $p = $e->getPlayer();
        $nickname = strtolower($p->getName());
        $address = $p->getAddress();

        $login_message = "\n§b► §fПожалуйста, авторизуйтесь, введя §bпароль §fв чат \n" .
            "§6! §fЕсли вы зашли впервые, то §6смените §fникнейм на любой другой";

        if (!isset($this->users[$nickname]) || (isset($this->users[$nickname]) && $this->users[$nickname]["ip"] !== $address)) {

            $e->setCancelled(true);

            if (!$this->getMySQLConnection()) {
                $p->sendMessage("§c► §fПроизошла ошибка при подключении к базе данных");
                return true;
            }

            if (count(explode("/", $message)) > 1) {
                $p->sendMessage($login_message);
                $e->setCancelled();
                return true;
            }

            $password = explode(" ", $message)[0];

            $result = $this->getUser($nickname);

            if (isset($result["password"]) && $result["password"] != null) {

                $password = $password;
                if ($password === $result["password"]) {
                    $this->users[$nickname] = ["ip" => $address];
                    $this->setUserAuthInfo($nickname, $address, $p->getDeviceModel(), $this->getServer()->getPort(), time());
                    $p->setImmobile(false);
                    unset($this->attempts[$nickname]);
                    $this->successfulAuth($p);
                    $p->sendMessage("\n§a► §fВы §aуспешно §fавторизовались, приятной игры!");
                    $p->sendMessage("§6! §fВы так же можете поставить защиту, дабы обезопасить ваш аккаунт §7- §6/2fa [skin-on|cid-on]\n§6! §fПривязать аккаунт к сообществу в ВК §7- §6/vkcode");
                } else {
                    if (isset($this->attempts[$nickname])) {
                        $this->attempts[$nickname]++;
                    } else {
                        return true;
                    }
                    if ($this->attempts[$nickname] < 5) {
                        $message = "§c► §fВы ввели неверный пароль";
                        if ($this->attempts[$nickname] < 4)
                            $message .= ", осталось §c" . ((int)5 - $this->attempts[$nickname]) . " §fпопытки";
                        else
                            $message .= ", осталась §cпоследняя §fпопытка";
                        $p->sendMessage($message);
                    } else {
                        unset($this->attempts[$nickname]);
                        $p->close("Попытка взлома / подбора паролей", "§aAuth §8• §fВы использовали все попытки для ввода пароля");
                    }
                }

            } else {

                if (!isset($this->register[$nickname])) {
                    if (preg_match("/^[0-9a-zA-Zа-яА-Я.,!?@#$%^&*_]{6,24}$/", $password)) {
                        $this->register[$nickname] = $password;
                        $p->sendMessage("§b► §fВведите пароль в чат ещё раз");
                        return true;
                    }
                    $p->sendMessage("§c► §fПароль должен состоять из §7[§c6-24§7] §fсимволов и может включать в себя§7: \n §c» §fЛатиницу §7[§ca-z§7] | §fКириллицу §7[§cа-я§7] | §fЦифры §7[§c0-9§7] | §fСпец. символы §7[§c.,!?@#$%^&*_§7]");
                    return true;
                }

                if ($password === $this->register[$nickname]) {

                    $password = $password;
                    $this->users[$nickname] = ["ip" => $address];
                    $this->registerUser($nickname, $password, $address, $p->getDeviceModel(), $this->getServer()->getPort(), time());
                    $p->setImmobile(false);
                    $this->successfulAuth($p);

                    if (!isset($result["nickname"])) {
                        $count = $this->getCountUsers();
                        $players = [];
                        foreach ($this->getServer()->getOnlinePlayers() as $pls)
                            if (isset($this->users[strtolower($pls->getName())]))
                                $players[] = $pls;
                        $this->getServer()->broadcastMessage("\n§c! §fНа сервере новый игрок §l§b" . $p->getName() . " §r§7- §fименно он становится §c" . $count . " игроком §fсервера\n", $players);
                    } else
                        $p->sendMessage("\n");

                    $p->sendMessage("§a► §fВы §aуспешно §fзарегистрировались, ваш пароль §7- §b" . $password);
                    $p->sendMessage("§6! §fВы так же можете поставить защиту, дабы обезопасить ваш аккаунт §7- §6/2fa [skin-on|cid-on]\n§6! §fПривязать аккаунт к сообществу в ВК §7- §6/vkcode");

                } else {
                    unset($this->register[$nickname]);
                    $p->sendMessage("§c► §fПароли не совпадают, попробуйте еще раз");
                }
            }

        }
        return true;

    }

    public function onCommand(CommandSender $sender, Command $command, $label, array $args): bool
    {
        switch(strtolower($command->getName())) {
            case "auth-kick":
                if ($sender->isOp()) {
                    if (isset($args[0])) {
                        $target = $this->getServer()->getPlayerExact($args[0]);
                        if ($target instanceof Player) {
                            $target->close("Удаленное отключение от сервера", "§aAuth §8• §fВаш аккаунт был удаленно отключен от сервера");
                            $sender->sendMessage("§aAuth §8• §fИгрок §a" . strtolower($args[0]) . " §fбыл отключен от сервера");
                            return true;
                        }
                        $sender->sendMessage("§aAuth §8• §fИгрок §a" . strtolower($args[0]) . " §fне найден");
                    } else {
                        $sender->sendMessage("§aAuth §8• §fИспользование §7- §a/auth-kick <name>");
                    }
                } else {
                    $sender->sendMessage("§aAuth §8• §fУ вас недостаточно прав");
                }
                break;
                
            case "auth-set-password":
                // Команда доступна только из консоли
                if(!($sender instanceof \pocketmine\command\ConsoleCommandSender)) {
                    $sender->sendMessage("§cЭта команда доступна только из консоли сервера");
                    return true;
                }
                
                // Проверка аргументов
                if(count($args) < 2) {
                    $sender->sendMessage("§aAuth §8• §fИспользование §7- §a/auth-set-password <name> <newpass>");
                    return true;
                }
                
                // Получаем данные
                $username = strtolower($args[0]);
                $newPassword = $args[1];
                
                // Проверяем подключение к БД
                if(!$this->getMySQLConnection()) {
                    $sender->sendMessage("§cОшибка подключения к базе данных");
                    return true;
                }
                
                // Ищем пользователя
                $userData = $this->getUser($username);
                if(empty($userData)) {
                    $sender->sendMessage("§cИгрок §e" . $args[0] . " §cне найден в базе данных");
                    return true;
                }
                
                // Обновляем пароль
                $hashedPassword = $newPassword;
                $stmt = $this->data->prepare("UPDATE users SET password = ? WHERE nickname = ?");
                if ($stmt === false) {
                    $this->logMySqlError("Ошибка подготовки запроса (auth-set-password): " . $this->data->error);
                    $sender->sendMessage("§cОшибка при подготовке запроса");
                    return true;
                }
                
                $stmt->bind_param("ss", $hashedPassword, $username);
                
                if($stmt->execute()) {
                    $sender->sendMessage("§aПароль для §b" . $args[0] . " §aуспешно изменен!");
                } else {
                    $this->logMySqlError("Ошибка выполнения запроса (auth-set-password): " . $stmt->error);
                    $sender->sendMessage("§cОшибка при обновлении пароля");
                }
                
                $stmt->close();
                return true;
                break;
        }
        return true;
    }
    
    private function logMySqlError(string $message): void {
        $this->getLogger()->error("Ошибка MySQL: " . $message);
    }
}