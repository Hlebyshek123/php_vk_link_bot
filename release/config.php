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