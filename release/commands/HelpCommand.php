<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/error.log');
require_once __DIR__.'/../config.php';

class HelpCommand {
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
        $parts = explode(' ', $this->message_text, 2);
        $help_type = isset($parts[1]) ? strtolower($parts[1]) : 'база';

        switch ($help_type) {
            case 'база':
                $this->basicHelp();
                break;
            
            case 'админ':
                $this->adminHelp();
                break;
            
            case 'рукво':
                $this->managementHelp();
                break;
            
            default:
                $this->sendMessage(
                    EMOJIS['blocked'] . " | Неизвестный раздел помощи.");
        }
    }

    private function basicHelp() {
        $message = 
            EMOJIS['bymaga'] . " | Помощь Базированная\n\n" .
            EMOJIS['star'] . " | " . BOT_SETTINGS['bot_prefix'] . "привязка [ник] [код] - Привязать аккаунт\n" .
            EMOJIS['heart_razbito'] . " | " . BOT_SETTINGS['bot_prefix'] . "отвязка - Отвязать аккаунт\n" .
            EMOJIS['joystick'] . " | " . BOT_SETTINGS['bot_prefix'] . "акк [аргумент] - Управление аккаунтами:\n" .
            " ~ 1 - Список аккаунтов\n" .
            " ~ 2 [номер] - Выбор аккаунта\n" .
            " ~ 3 - Профиль аккаунта\n" .
            " ~ 4 [пароль] - Восстановление пароля\n" .
            " ~ 5 [номер_сервера] - кик с сервера.\n" .
            " ~ 6 [skin/cid] - сбросить защиту.\n" .
            EMOJIS['computer'] . " | " . BOT_SETTINGS['rcon_prefix'] . "rcon - Консоль сервера";

        $this->sendMessage($message);
    }

    private function adminHelp() {
        $account = $this->botDB->getSelectedAccount($this->user_id);
        
        if (!$account) {
            $this->sendMessage(
                EMOJIS['blocked'] . " | Вы не выбрали аккаунт!");
            return;
        }

        try {
            $rank = $this->botDB->getPlayerRank($account);
            
            if (!$rank) {
            $this->sendMessage(
                EMOJIS['blocked'] . " | У вас нет прав или вы не привязаны.");
            return;
        }

            $allowed_ranks = $this->getAllowedRanks('admin_help');
            
            if ($rank && in_array($rank, $allowed_ranks)) {
                $message =
                    EMOJIS['molitsa'] . " | Помощь Админская\n\n" . EMOJIS['chepi'] . " | " . BOT_SETTINGS['bot_prefix'] . "vkban [никнейм] [время в часах] [причина] - заблокать консоль и донат.возможности.\n" . EMOJIS['chepi'] . " | " . BOT_SETTINGS['bot_prefix'] . "vkpardon [никнейм] - разблокать консоль и донат.возможности\n" . EMOJIS['pismo_otpravka'] . " | " . BOT_SETTINGS['rcon_prefix'] . "vksay [сообщение] - отправить сообщение без приписки [server]\n" . EMOJIS['korobochka'] . " | " . BOT_SETTINGS['bot_prefix'] . "user-list [страница] - список аккаунтов.\n " . EMOJIS['zvezda'] . " | " . BOT_SETTINGS['bot_prefix'] . "player-info [ник] - информация об игроке.";
                
                $this->sendMessage($message);
            } else {
                $this->sendMessage(EMOJIS['blocked'] . " | У вас недостаточно прав!");
            }
        } catch (PDOException $e) {
            error_log("[HelpCommand] Database error: " . $e->getMessage());
            $this->sendMessage(EMOJIS['blocked'] . " | Ошибка базы данных!");
        }
    }

    private function managementHelp() {
        if (in_array($this->user_id, ADMINS)) {
            $message =
                EMOJIS['crown'] . " | Помощь руководству\n\n" .
                EMOJIS['star'] . " | " . BOT_SETTINGS['manage_prefix'] . "sg [ник] [доступ] - Изменить/выдать игроку доступ.\n" .
                EMOJIS['pismo_otpravka'] . " | " . BOT_SETTINGS['manage_prefix'] . "рассылка [текст] - Массовая рассылка всем привязанным.\n" .
                EMOJIS['chepi'] . " | " . BOT_SETTINGS['manage_prefix'] . "pardon-all [ник_админа] - снять ВК-бан всем кого забанил админ нигадяй"
                EMOJIS['computer'] . " | RCON команды(/rcon):\n" .
                EMOJIS['zamochek'] . " | give-acs [ник] [привилегия] [вк_доступ] - Выдача доступа\n" .
                EMOJIS['plusik'] . " | force-add [ник] [вк_айди] - Принудительная привязка\n" .
                EMOJIS['minusik'] . " | force-delete [ник] - Принудительная отвязка\n" .
                EMOJIS['zvezda'] . " | status-vk [ник] - статус привязки.";
            
            $this->sendMessage($message);
        } else {
            $this->sendMessage(EMOJIS['blocked'] . " | Хмм что-то мне подсказывает что вы не администратор?");
        }
    }

    private function getAllowedRanks(string $bot_cmd): array {
        return BOT_RANKS['allowed_ranks'][$bot_cmd] ?? [];
    }

    private function sendMessage(string $message, array $params = []): void {
        $this->APIshka->sendMessage($this->user_id, $message, $params);
    }
}