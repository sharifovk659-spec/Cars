-- Car logistics fields: upload number, wagon, trailer
ALTER TABLE `cars`
    ADD COLUMN `upload_number` VARCHAR(50) NULL DEFAULT NULL AFTER `upload_date`;

ALTER TABLE `cars`
    ADD COLUMN `vagon` VARCHAR(50) NULL DEFAULT NULL AFTER `upload_number`;

ALTER TABLE `cars`
    ADD COLUMN `treiler` VARCHAR(50) NULL DEFAULT NULL AFTER `vagon`;
