-- Telegram Cars — MySQL Schema
-- Database: telegram_cars

CREATE DATABASE IF NOT EXISTS `telegram_cars`
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;

USE `telegram_cars`;

SET FOREIGN_KEY_CHECKS = 0;

-- --------------------------------------------------------
-- admins — корбарони панели идоракунӣ
-- --------------------------------------------------------
DROP TABLE IF EXISTS `activity_logs`;
DROP TABLE IF EXISTS `search_history`;
DROP TABLE IF EXISTS `car_images`;
DROP TABLE IF EXISTS `cars`;
DROP TABLE IF EXISTS `telegram_users`;
DROP TABLE IF EXISTS `settings`;
DROP TABLE IF EXISTS `admins`;

SET FOREIGN_KEY_CHECKS = 1;

CREATE TABLE `admins` (
    `id`            INT UNSIGNED        NOT NULL AUTO_INCREMENT,
    `username`      VARCHAR(50)         NOT NULL,
    `password_hash` VARCHAR(255)        NOT NULL,
    `full_name`     VARCHAR(100)        NOT NULL DEFAULT '',
    `email`         VARCHAR(150)        NULL DEFAULT NULL,
    `is_active`     TINYINT(1)          NOT NULL DEFAULT 1,
    `last_login_at` DATETIME            NULL DEFAULT NULL,
    `remember_token` VARCHAR(64)        NULL DEFAULT NULL,
    `remember_expires` DATETIME         NULL DEFAULT NULL,
    `created_at`    DATETIME            NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`    DATETIME            NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_admins_username` (`username`),
    UNIQUE KEY `uq_admins_email` (`email`),
    KEY `idx_admins_is_active` (`is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- cars — маълумоти мошинҳо
-- --------------------------------------------------------
CREATE TABLE `cars` (
    `id`             INT UNSIGNED        NOT NULL AUTO_INCREMENT,
    `vin_code`       VARCHAR(17)         NOT NULL COMMENT 'VinCode — такрорӣ не',
    `name`           VARCHAR(200)        NOT NULL COMMENT 'Номи мошин',
    `description`    TEXT                NULL COMMENT 'Тавсиф',
    `receive_date`   DATE                NULL DEFAULT NULL COMMENT 'Рӯзи қабул',
    `upload_date`    DATE                NULL DEFAULT NULL COMMENT 'Рӯзи боргирӣ',
    `status`         ENUM(
                         'available',
                         'reserved',
                         'sold',
                         'archived'
                     )                   NOT NULL DEFAULT 'available' COMMENT 'Статус',
    `contact_name`   VARCHAR(150)        NULL DEFAULT NULL COMMENT 'Номи контакт',
    `contact_phone`  VARCHAR(30)         NULL DEFAULT NULL COMMENT 'Телефони контакт',
    `notes`          TEXT                NULL COMMENT 'Эзоҳ',
    `created_at`     DATETIME            NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'Санаи сохтан',
    `updated_at`     DATETIME            NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `deleted_at`     DATETIME            NULL DEFAULT NULL COMMENT 'Soft delete',
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_cars_vin_code` (`vin_code`),
    KEY `idx_cars_status` (`status`),
    KEY `idx_cars_receive_date` (`receive_date`),
    KEY `idx_cars_upload_date` (`upload_date`),
    KEY `idx_cars_created_at` (`created_at`),
    KEY `idx_cars_name` (`name`),
    KEY `idx_cars_deleted_at` (`deleted_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- car_images — суратҳои мошин (1 то 5 адад)
-- --------------------------------------------------------
CREATE TABLE `car_images` (
    `id`          INT UNSIGNED        NOT NULL AUTO_INCREMENT,
    `car_id`      INT UNSIGNED        NOT NULL,
    `image_path`  VARCHAR(500)        NOT NULL,
    `sort_order`  INT UNSIGNED        NOT NULL DEFAULT 1 COMMENT 'Тартиби сурат',
    `created_at`  DATETIME            NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_car_images_car_sort` (`car_id`, `sort_order`),
    KEY `idx_car_images_car_id` (`car_id`),
    CONSTRAINT `fk_car_images_car_id`
        FOREIGN KEY (`car_id`) REFERENCES `cars` (`id`)
        ON DELETE CASCADE
        ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- telegram_users — корбарони Telegram
-- --------------------------------------------------------
CREATE TABLE `telegram_users` (
    `id`             INT UNSIGNED        NOT NULL AUTO_INCREMENT,
    `telegram_id`    BIGINT UNSIGNED     NOT NULL,
    `username`       VARCHAR(100)        NULL DEFAULT NULL,
    `first_name`     VARCHAR(100)        NULL DEFAULT NULL,
    `last_name`      VARCHAR(100)        NULL DEFAULT NULL,
    `language_code`  VARCHAR(10)         NULL DEFAULT NULL,
    `is_blocked`     TINYINT(1)          NOT NULL DEFAULT 0,
    `created_at`     DATETIME            NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`     DATETIME            NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_telegram_users_telegram_id` (`telegram_id`),
    KEY `idx_telegram_users_username` (`username`),
    KEY `idx_telegram_users_is_blocked` (`is_blocked`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- search_history — таърихи ҷустуҷӯ
-- --------------------------------------------------------
CREATE TABLE `search_history` (
    `id`             INT UNSIGNED        NOT NULL AUTO_INCREMENT,
    `user_id`        INT UNSIGNED        NOT NULL,
    `search_query`   VARCHAR(255)        NOT NULL,
    `vin_code`       VARCHAR(17)         NULL DEFAULT NULL,
    `results_count`  INT UNSIGNED        NOT NULL DEFAULT 0,
    `searched_at`    DATETIME            NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_search_history_user_id` (`user_id`),
    KEY `idx_search_history_vin_code` (`vin_code`),
    KEY `idx_search_history_searched_at` (`searched_at`),
    CONSTRAINT `fk_search_history_user_id`
        FOREIGN KEY (`user_id`) REFERENCES `telegram_users` (`id`)
        ON DELETE CASCADE
        ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- activity_logs — журнали фаъолият
-- --------------------------------------------------------
CREATE TABLE `activity_logs` (
    `id`           INT UNSIGNED        NOT NULL AUTO_INCREMENT,
    `admin_id`     INT UNSIGNED        NULL DEFAULT NULL,
    `user_id`      INT UNSIGNED        NULL DEFAULT NULL,
    `action`       VARCHAR(100)        NOT NULL,
    `entity_type`  VARCHAR(50)         NULL DEFAULT NULL,
    `entity_id`    INT UNSIGNED        NULL DEFAULT NULL,
    `details`      TEXT                NULL,
    `ip_address`   VARCHAR(45)         NULL DEFAULT NULL,
    `created_at`   DATETIME            NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_activity_logs_admin_id` (`admin_id`),
    KEY `idx_activity_logs_user_id` (`user_id`),
    KEY `idx_activity_logs_action` (`action`),
    KEY `idx_activity_logs_entity` (`entity_type`, `entity_id`),
    KEY `idx_activity_logs_created_at` (`created_at`),
    CONSTRAINT `fk_activity_logs_admin_id`
        FOREIGN KEY (`admin_id`) REFERENCES `admins` (`id`)
        ON DELETE SET NULL
        ON UPDATE CASCADE,
    CONSTRAINT `fk_activity_logs_user_id`
        FOREIGN KEY (`user_id`) REFERENCES `telegram_users` (`id`)
        ON DELETE SET NULL
        ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- settings — танзимоти система
-- --------------------------------------------------------
CREATE TABLE `settings` (
    `id`            INT UNSIGNED        NOT NULL AUTO_INCREMENT,
    `setting_key`   VARCHAR(100)        NOT NULL,
    `setting_value` TEXT                NULL,
    `description`   VARCHAR(255)        NULL DEFAULT NULL,
    `updated_at`    DATETIME            NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_settings_setting_key` (`setting_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Photo count limit is enforced in PHP via Admin Settings (max_car_images).
-- No hard-coded MySQL trigger for image count.
