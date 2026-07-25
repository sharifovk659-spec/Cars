-- Force admin credentials for web + mini app: admin / admin123
UPDATE `admins`
SET
    `username` = 'admin',
    `password_hash` = '$2y$10$.CFi.NbR62IIp.gnSMq96.s7ZvLzJk39KVONS1xMy2X5Wjqhyb7bW',
    `full_name` = COALESCE(NULLIF(TRIM(`full_name`), ''), 'Administrator'),
    `email` = COALESCE(NULLIF(TRIM(`email`), ''), 'admin@telegramcars.local'),
    `is_active` = 1;

INSERT INTO `admins` (`username`, `password_hash`, `full_name`, `email`, `is_active`)
SELECT 'admin', '$2y$10$.CFi.NbR62IIp.gnSMq96.s7ZvLzJk39KVONS1xMy2X5Wjqhyb7bW', 'Administrator', 'admin@telegramcars.local', 1
WHERE NOT EXISTS (
    SELECT 1 FROM `admins`
);
