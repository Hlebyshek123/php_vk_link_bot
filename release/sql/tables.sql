-- Хранит привязку ВК к никнеймам
CREATE TABLE `vk_links` (
  `username` VARCHAR(20) PRIMARY KEY,
  `vk_id` INT NOT NULL,
  `vk_code` VARCHAR(11) NOT NULL,
  `link` ENUM('YES','NO') NOT NULL DEFAULT 'NO'
);

-- Хранит RCON-доступ и привилегии
CREATE TABLE `vk_rcon` (
  `nickname` VARCHAR(20) PRIMARY KEY,
  `vk_id` INT NOT NULL,
  `rank` VARCHAR(20) DEFAULT NULL,
  `banned` ENUM('YES','NO') NOT NULL DEFAULT 'NO',
  `ban_reason` TEXT DEFAULT NULL,
  `ban_time` DATETIME DEFAULT NULL,
  `selected_server` VARCHAR(50) DEFAULT NULL
);

-- Хранит Настройки пользователя
CREATE TABLE `user_settings` (
  `vk_id` INT PRIMARY KEY,
  `selected_account` VARCHAR(20) DEFAULT NULL
);

-- Хранит кулдауны команд
CREATE TABLE `cooldowns` (
  `vk_id` INT PRIMARY KEY,
  `last_unlink_time` DATETIME DEFAULT NULL,
  `last_reset_time` DATETIME DEFAULT NULL,
  `last_kick_time` DATETIME DEFAULT NULL
);

-- Хранит временные даные пользователя
CREATE TABLE `temp_data` (
  `vk_id` INT PRIMARY KEY,
  `username` VARCHAR(20) DEFAULT NULL,
  `temp_password` VARCHAR(255) DEFAULT NULL
);