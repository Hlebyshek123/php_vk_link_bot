# 🖥 php_vk_link_bot
Bot for managing Minecraft account and console in VK // Бот для управления майнкрафт ником и консолью в ВК.

# 🚀 Установка
Все в **readme.php**

# 📸 Скринчики

**Некоторые скрины из бота**

![Screenshot_20250630-231022](https://github.com/user-attachments/assets/240a6336-78e1-4e84-be08-5c01bcb0b0d9)
![Screenshot_20250630-230822](https://github.com/user-attachments/assets/3d82d210-103a-41ab-8a02-5cada104bb71)
![Screenshot_20250630-230257](https://github.com/user-attachments/assets/1947853f-a5b9-42f9-b4a7-eda34cd1c1be)
![Screenshot_20250630-225602](https://github.com/user-attachments/assets/a7a0dfca-030c-4b62-a17f-27a65e32980e)
![Screenshot_20250630-225342](https://github.com/user-attachments/assets/e874742b-4a4a-4b37-a468-52a45f85f0a1)
![Screenshot_20250630-225407](https://github.com/user-attachments/assets/e525f131-d86f-453b-9e61-7c711cc92920)
![Screenshot_20250630-225438](https://github.com/user-attachments/assets/abfc8047-26bc-4d44-81ab-9bc02741fdae)
![Screenshot_20250630-225306](https://github.com/user-attachments/assets/67c623c8-eb80-449d-b4b4-1e342705926c)


# **✨ Зависимости Бота**

**Вконтакте PHP SDK ^5.131**
```
composer require vk/php-sdk:5.131
```
**Composer**

*Установка curl если нет*
```
sudo apt install curl -y
```
*сам Composer*
```
curl -sS https://getcomposer.org/installer | sudo php -- --install-dir=/usr/local/bin --filename=composer
```
*проверить версию*
```
composer --version
```
*Инициализация Composer*
```
cd vkbot
composer init
```
*установка пакетов*
```
composer require vendor/package
```
*обновить Composer*
```
composer self-update
```
**PHP 8.1**

*1. Обновите систему Ubuntu 22.04*
```
sudo apt update && sudo apt upgrade -y
```
*2. Установите репозитория для php 8.1*
```
sudo apt install software-properties-common -y
```
```
sudo apt install software-properties-common -y && sudo add-apt-repository ppa:ondrej/php -y && sudo apt update
```
*3. База Ядро + CLI*
```
sudo apt install php8.1 -y
```
```
php -v
```
*4. Установка доп.модулей для PHP и Apache*
```
sudo apt install php8.1-curl php8.1-mbstring php8.1-xml php8.1-zip php8.1-gd php8.1-mysql php8.1-pgsql php8.1-sqlite3 php8.1-bcmath -y
```
```
sudo apt install php8.1 libapache2-mod-php8.1 -y && sudo systemctl restart apache2
```
*5. Смена версии PHP если активна не та которая нужна*

*Для CLI*
```
sudo update-alternatives --set php /usr/bin/php8.1
```
*Для Apache*
```
sudo a2dismod php7.4(или версия которая активна) && sudo a2enmod php8.1 && sudo systemctl restart apache2
```
**MySQL**
```
sudo apt install mysql-server -y
```
*Запуск и проверка работоспособности*
```
sudo systemctl start mysql && sudo systemctl enable mysql && sudo systemctl status mysql
```

должно писать active(running)

*Настройка безопасности*

```
sudo mysql_secure_installation
```
Нужно ответить на пару вопросов

1. Y
2. 1
3. введите пароль для пользователя root
4. Y
5. N
6. Y
7. Y

*Настройка удаленного доступа*

Найти файл **mysqld.cnf** по пути
*/etc/mysql/mysql.conf.d/mysqld.cf*
Найти там строчку 
**bind-address** = 127.0.0.1 и заменить на
```
bind-address = 0.0.0.0
```

*Рестарт MySQL*

```
sudo systemctl restart mysql
```

# ⚙️ Зависимости Сервера
(LiteCore 1.1.x)
Плагины:
playtime
vkProtection
VKManager
Auth

# 📝 Конфигурация
```php
<?php
define('VK_API_TOKEN', 'qBbnhg');
// Ключ сообщества
define('CONFIRMATION_TOKEN', 'cc86d241');
// Значение из "строка которую должен вернуть сервер"

define('SECRET_KEY', "hl3");
// Секретный ключ

define('ADMINS', [789, 8161]); // ВК ID администраторов бота

$protectedAdmins = [
    'hleber1',
    'ragebait',
    'hleber2',
    ];

define('PROTECTED_ADMINS', $protectedAdmins);
// Ники защищенные от бана и смены доступа в консоли

define('GROUP_ID', 2194);
// Айди группы бота
define('SRV_SHOP', "shop.sosal.org");
// Ссылка на авто-донат
define('SRV_NAME', "Мой_сервер");
//Имя сервера
$database = [
    "bot" => [
        "ip" => "171",
        "dbname" => "bot_data",
        "user" => "hlr",
        "password" => "hZb@",
    ],
    "server" => [
        "ip" => "171",
        "dbname" => "server_data",
        "user" => "hr",
        "password" => "b@",
    ],
];
define("DATABASE", $database);
// База данных бота и сервера майнкрафт

$bot_settings = [
    "start_time" => time(),
    "max_message_age" => 120, #cek
    "bot_prefix" => '!',
    "rcon_prefix" => '/',
    "manage_prefix" => '$',
    "max_def_acc" => 3,
    "max_admin_acc" => 5,
    "max_unlink_time" => 1800,
    "max_reset_time" => 1800,
    "debug_logging" => true,
    ];
define("BOT_SETTINGS", $bot_settings);
// Настройки бота

$emojis = [
    "blocked" => "&#128683;",
    "galochka" => "&#9989;",
    "tada" => "&#127881;",
    "molitsa" => "&#128591;",
    "computer" => "&#128421;",
    "compas" => "&#129517;",
    "pismo_otpravka" => "&#128233;",
    "zamochek" => "&#128274;",
    "heart_razbito" => "&#128148;",
    "heart" => "&#10084;&#65039;",
    "pencil" => "&#9999;",
    "ostorozno" => "&#10071;",
    "numbero1" => "&#49;&#65039;&#8419;",
    "numbero2" => "&#50;&#65039;&#8419;",
    "grystni" => "&#128546;",
    "plusik" => "&#10133;",
    "minusik" => "&#10134;",
    "voprosik" => "&#10067;",
    "star" => "&#11088;",
    "blestki" => "&#10024;",
    "crown" => "&#128081;",
    "chepi" => "&#9939;&#65039;",
    "korobochka" => "&#128230;",
    "zvezda" => "&#128160;",
    "bymaga" => "&#128209;",
    "bymaga2" => "&#128220;",
    "keychik" => "&#128273;",
    "zamochek_i_key" => "&#128272;",
    "joystick" => "&#128377;&#65039;",
    "clock" => "&#128338;",
    "newbie" => "&#128304;",
    "link" => "&#128279;",
    "page" => "&#128196;",
    ];

define("EMOJIS", $emojis);
// Эмодзи которые использует бот

$bot_ranks = [
    "allowed_ranks" => [
        "admin_help" => ['3', '4', '5'],
        "vkban" => ['3', '4', '5'],
        "vkpardon" => ['3', '4', '5'],
        "vkpardon_all" => ['3', '4', '5 '],#определен в менеджер команды но можно использовать для обычных игроков с нужным уровнем
        "player_info" => ['3', '4', '5'],
        "user_list" => ['3', '4', '5'],
        ],
    "valid_ranks" => [
        "0",
        "1",
        "2",
        "3",
        "4",
        "5",
        ],
    ];

define("BOT_RANKS", $bot_ranks);
// allowed_ranks = каким рангам можно использовать ту или иную команду именно в боте
// valid_ranks = все существующие ранги в боте

$servers = [
    'survival' => [
        'rcon_host' => '171',
        'rcon_port' => 11,
        'rcon_password' => 'sJUYPwSui-',
        ],
    'creative' => [
        'rcon_host' => '171',
        'rcon_post' => 12,
        'rcon_password' => 'jskskdkrntbtbHUpoI',
        ],
    ];

define('SERVERS', $servers);
// RCON данные серверов

$serv_perms = [
    '1' => ['survival'],
    '2' => ['survival'],
    '3' => ['survival'],
    '4' => ['survival'],
    '5' => ['survival', 'creative'],
    ];

define('SERV_PERMS', $serv_perms);
// Разрешает конкретному рангу использовать конкретный сервер

$rcon_ranks = [
    '1' => ["list", "say"],
    '2' => ["list", "say"],
    '3' => ["groups", "list", "say"],
    '4' => ["list", "say"],
    '5' => ['*'],
    ];

define('RCON_RANKS', $rcon_ranks);
// Разрешенные команды для использования в /rcon для конкретного ранга
// * = Всевластие (все возможные команды сервера)
```

# 👑 Получение полных прав
Для того что бы получить все права в боте нужно:
1. Вписать свой вк айди в конфиг бота в поле ``ADMINS``
2. Прописать команду
```$sg [ник] [доступ от 0 до 5]```
3. Что бы защитить свой ник от почти любых команд нужно его ввести в конфиге бота в поле ``PROTECTED_ADMINS`` и в конфиге плагина VKManager в поле ``protected_nicks``

# 💸 Выдача доступа авто-донатом
Что бы выдавать доступ и привилегии через авто-донат нужно использовать команду **give-acs** плагина VKManager.

```
/give-acs [ник] [прива] [доступ от 0 до 5]
```
```
/give-acs RageBait Console 1
```
Если случится такое что игрок не был привязан к боту то сообщение об ошибке придет на тот вк айди который был указан в конфиге vkmanager.

# ⛓️ Привязка к ВК
Чтобы привязать **ВК Профиль** к **нику** игрока нужно чтоб он отправил любое сообщение боту в лс потом зашел на сервер прописал команду **/vkcode** после того как он получит код нужно вернуться к боту и ввести команду
```
!привязка [ник] [вк код]
```
# 🔰 Помощь
*ВК*

```
@zl_hlebyshek
```
