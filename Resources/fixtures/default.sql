SET NAMES utf8;
SET time_zone = '+00:00';
SET foreign_key_checks = 0;
SET sql_mode = 'NO_AUTO_VALUE_ON_ZERO';

CREATE TABLE IF NOT EXISTS `session`
(
    `id` VARCHAR(128) NOT NULL,
    `data` LONGBLOB,
    `lifetime` INTEGER NOT NULL,
    `last_ip` VARCHAR(39) NOT NULL,
    `last_useragent` VARCHAR(255),
    `last_geoip` VARCHAR(255),
    `last_browser` VARCHAR(255),
    `created_at` DATETIME,
    `updated_at` DATETIME,
    PRIMARY KEY (`id`)
) ENGINE=InnoDB;