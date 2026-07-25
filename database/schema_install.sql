-- Telegram Cars install schema (production-safe, no DROP, no CREATE DATABASE)
-- Applied once by deploy/migrate.php on empty database

SET FOREIGN_KEY_CHECKS = 0;

CREATE TABLE IF NOT EXISTS `admins` (
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

CREATE TABLE IF NOT EXISTS `cars` (
    `id`             INT UNSIGNED        NOT NULL AUTO_INCREMENT,
    `vin_code`       VARCHAR(17)         NOT NULL,
    `name`           VARCHAR(200)        NOT NULL,
    `description`    TEXT                NULL,
    `receive_date`   DATE                NULL DEFAULT NULL,
    `upload_date`    DATE                NULL DEFAULT NULL,
    `upload_number`  VARCHAR(50)         NULL DEFAULT NULL,
    `vagon`          VARCHAR(50)         NULL DEFAULT NULL,
    `treiler`        VARCHAR(50)         NULL DEFAULT NULL,
    `status`         ENUM('available','reserved','sold','archived') NOT NULL DEFAULT 'available',
    `contact_name`   VARCHAR(150)        NULL DEFAULT NULL,
    `contact_phone`  VARCHAR(30)         NULL DEFAULT NULL,
    `notes`          TEXT                NULL,
    `created_at`     DATETIME            NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`     DATETIME            NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `deleted_at`     DATETIME            NULL DEFAULT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_cars_vin_code` (`vin_code`),
    KEY `idx_cars_status` (`status`),
    KEY `idx_cars_receive_date` (`receive_date`),
    KEY `idx_cars_upload_date` (`upload_date`),
    KEY `idx_cars_created_at` (`created_at`),
    KEY `idx_cars_name` (`name`),
    KEY `idx_cars_deleted_at` (`deleted_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `car_images` (
    `id`          INT UNSIGNED        NOT NULL AUTO_INCREMENT,
    `car_id`      INT UNSIGNED        NOT NULL,
    `image_path`  VARCHAR(500)        NOT NULL,
    `sort_order`  TINYINT UNSIGNED    NOT NULL DEFAULT 1,
    `created_at`  DATETIME            NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_car_images_car_sort` (`car_id`, `sort_order`),
    KEY `idx_car_images_car_id` (`car_id`),
    CONSTRAINT `fk_car_images_car_id`
        FOREIGN KEY (`car_id`) REFERENCES `cars` (`id`)
        ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT `chk_car_images_sort_order`
        CHECK (`sort_order` BETWEEN 1 AND 5)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `telegram_users` (
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

CREATE TABLE IF NOT EXISTS `search_history` (
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
        ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `activity_logs` (
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
        ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT `fk_activity_logs_user_id`
        FOREIGN KEY (`user_id`) REFERENCES `telegram_users` (`id`)
        ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `settings` (
    `id`            INT UNSIGNED        NOT NULL AUTO_INCREMENT,
    `setting_key`   VARCHAR(100)        NOT NULL,
    `setting_value` TEXT                NULL,
    `description`   VARCHAR(255)        NULL DEFAULT NULL,
    `updated_at`    DATETIME            NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_settings_setting_key` (`setting_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;

DROP TRIGGER IF EXISTS `trg_car_images_before_insert`;

DELIMITER $$

CREATE TRIGGER `trg_car_images_before_insert`
BEFORE INSERT ON `car_images`
FOR EACH ROW
BEGIN
    DECLARE image_count INT;

    SELECT COUNT(*) INTO image_count
    FROM `car_images`
    WHERE `car_id` = NEW.`car_id`;

    IF image_count >= 5 THEN
        SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT = 'Ҳар мошин на бештар аз 5 сурат дошта метавонад';
    END IF;
END$$

DELIMITER ;

INSERT INTO `admins` (`username`, `password_hash`, `full_name`, `email`, `is_active`)
SELECT 'admin', '$2y$10$.CFi.NbR62IIp.gnSMq96.s7ZvLzJk39KVONS1xMy2X5Wjqhyb7bW', 'Administrator', 'admin@telegramcars.local', 1
WHERE NOT EXISTS (
    SELECT 1 FROM `admins` WHERE `email` = 'admin@telegramcars.local'
);
