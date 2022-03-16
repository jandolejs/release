-- Adminer 4.8.1 MySQL 5.5.5-10.5.12-MariaDB dump

SET NAMES utf8;
SET time_zone = '+00:00';
SET foreign_key_checks = 0;
SET sql_mode = 'NO_AUTO_VALUE_ON_ZERO';

SET NAMES utf8mb4;

-- DROP DATABASE IF EXISTS `release`;
CREATE DATABASE `release` /*!40100 DEFAULT CHARACTER SET utf8mb4 */;
USE `release`;

CREATE TABLE `releases` (
    `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
    `pull` int(11) DEFAULT NULL,
    `sha` varchar(100) DEFAULT NULL,
    `base_sha` varchar(100) DEFAULT NULL,
    `deployed_at` datetime DEFAULT NULL,
    `merged_at` datetime DEFAULT NULL COMMENT 'github merge confirmed',
    `manual_action` tinyint(4) DEFAULT NULL,
    `note` text DEFAULT NULL,
    `status` int(11) NOT NULL,
    `branch` varchar(50) DEFAULT NULL,
    `cache_flush` text DEFAULT NULL,
    `user_id` int(11) DEFAULT NULL,
    `files` text DEFAULT NULL,
    PRIMARY KEY (`id`),
    KEY `user_id` (`user_id`),
    CONSTRAINT `releases_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;


CREATE TABLE `settings` (
    `key` varchar(50) NOT NULL,
    `value` text DEFAULT NULL,
    `comment` text DEFAULT NULL,
    `created_at` datetime NOT NULL DEFAULT current_timestamp(),
    `updated_at` datetime NOT NULL DEFAULT '0000-00-00 00:00:00' ON UPDATE current_timestamp(),
    KEY `key` (`key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO `settings` (`key`, `value`, `comment`, `created_at`, `updated_at`) VALUES
('name',	'ReleaseR',	'Name of application',	'2021-10-06 13:14:58',	'0000-00-00 00:00:00');

CREATE TABLE `statistics` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `type` varchar(25) NOT NULL,
    `action` varchar(25) DEFAULT NULL,
    `date` datetime NOT NULL DEFAULT current_timestamp(),
    `value` text NOT NULL,
    `user_id` int(11) DEFAULT NULL,
    PRIMARY KEY (`id`),
    KEY `user_id` (`user_id`),
    CONSTRAINT `statistics_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;


CREATE TABLE `tasks` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `pz` int(11) DEFAULT NULL,
    `pull` int(11) NOT NULL,
    `branch` varchar(50) NOT NULL,
    `status` int(11) DEFAULT 1,
    `release` int(11) unsigned NOT NULL,
    `name` text DEFAULT NULL,
    `sha` text NOT NULL,
    `creator` varchar(50) NOT NULL,
    `manual` tinyint(4) NOT NULL DEFAULT 0,
    `comment` text DEFAULT NULL,
    `mergeable` tinyint(4) NOT NULL,
    `mergeable_state` text NOT NULL,
    `updated_at` datetime DEFAULT NULL,
    `imported_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
    `labels` text DEFAULT '[{}]',
    `fetched` tinyint(4) NOT NULL DEFAULT 0,
    `note` text DEFAULT NULL,
    `object` text DEFAULT NULL,
    `approve` smallint(6) NOT NULL DEFAULT 1,
    PRIMARY KEY (`id`),
    UNIQUE KEY `release_pull` (`release`,`pull`),
    CONSTRAINT `tasks_ibfk_1` FOREIGN KEY (`release`) REFERENCES `releases` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;


CREATE TABLE `users` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `username` varchar(50) NOT NULL,
    `password` text DEFAULT NULL,
    `name` text NOT NULL,
    `email` varchar(50) NOT NULL,
    `role` text DEFAULT NULL,
    `active` smallint(6) DEFAULT 0,
    `image` text DEFAULT NULL,
    `locale` varchar(10) DEFAULT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `email` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

INSERT INTO `users` (`id`, `username`, `password`, `name`, `email`, `role`, `active`, `image`, `locale`) VALUES
(1,	'dolejs',	'$2y$10$MTmd7lnN75xXPYXIucTDL.pompMOCztlrEUeWyYwvwQ3m.gEZlhrS',	'Jan Dolejš',	'jan.dolejs@ateli.cz',	'admin,release',	1,	'https://lh3.googleusercontent.com/a-/AOh14GgZspwSEHdFkpRlkr6DwtIrN8IFb3K-lpZGy9ps=s96-c',	NULL),
(2,	'davidek',	'$2y$10$MTmd7lnN75xXPYXIucTDL.pompMOCztlrEUeWyYwvwQ3m.gEZlhrS',	'Milan Davídek',	'milan.davidek@ateli.cz',	'user',	1,	'https://lh3.googleusercontent.com/a-/AOh14GhOluSJLQZmx01-ZvgJkARjdhozaiXVWBvkB3dt=s96-c',	NULL),
(3,	'soutor',	'$2y$10$MTmd7lnN75xXPYXIucTDL.pompMOCztlrEUeWyYwvwQ3m.gEZlhrS',	'Dominik Soutor',	'dominik.soutor@ateli.cz',	'user',	1,	'https://lh6.googleusercontent.com/-g_kbcGFAXf8/AAAAAAAAAAI/AAAAAAAAAAA/AMZuuclUsdnA-dNAMDzNj1QMj-7gsJdzzg/s96-c/photo.jpg',	NULL);

-- 2021-12-22 16:22:53
