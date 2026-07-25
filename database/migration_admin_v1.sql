-- Migration: soft delete, remember me, seed admin
USE `telegram_cars`;

ALTER TABLE `cars`
    ADD COLUMN `deleted_at` DATETIME NULL DEFAULT NULL AFTER `updated_at`;

ALTER TABLE `cars`
    ADD KEY `idx_cars_deleted_at` (`deleted_at`);

ALTER TABLE `admins`
    ADD COLUMN `remember_token` VARCHAR(64) NULL DEFAULT NULL AFTER `last_login_at`,
    ADD COLUMN `remember_expires` DATETIME NULL DEFAULT NULL AFTER `remember_token`;

INSERT INTO `admins` (`username`, `password_hash`, `full_name`, `email`, `is_active`)
SELECT 'admin', '$2y$10$.CFi.NbR62IIp.gnSMq96.s7ZvLzJk39KVONS1xMy2X5Wjqhyb7bW', 'Administrator', 'admin@telegramcars.local', 1
WHERE NOT EXISTS (
    SELECT 1 FROM `admins` WHERE `email` = 'admin@telegramcars.local'
);
