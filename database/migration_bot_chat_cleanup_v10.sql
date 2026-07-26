-- Bot chat auto-cleanup tracking (message ids + last activity)
CREATE TABLE IF NOT EXISTS `bot_chat_messages` (
    `chat_id` BIGINT NOT NULL,
    `message_id` BIGINT NOT NULL,
    `created_at` INT UNSIGNED NOT NULL,
    PRIMARY KEY (`chat_id`, `message_id`),
    KEY `idx_bot_chat_created` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `bot_chat_activity` (
    `chat_id` BIGINT NOT NULL,
    `last_activity` INT UNSIGNED NOT NULL,
    PRIMARY KEY (`chat_id`),
    KEY `idx_bot_chat_activity_last` (`last_activity`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
