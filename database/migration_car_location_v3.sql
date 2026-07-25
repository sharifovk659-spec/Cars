-- Receive location: Sharjah, Dubai, etc.
ALTER TABLE `cars`
    ADD COLUMN `receive_location` VARCHAR(50) NULL DEFAULT 'sharjah' AFTER `description`;

UPDATE `cars`
SET `receive_location` = 'sharjah'
WHERE `receive_location` IS NULL OR TRIM(`receive_location`) = '';
