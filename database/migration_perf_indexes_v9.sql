-- Speed indexes for common list/search filters (safe, additive only).

ALTER TABLE `cars` ADD INDEX `idx_cars_deleted_created` (`deleted_at`, `created_at`);
ALTER TABLE `cars` ADD INDEX `idx_cars_deleted_status` (`deleted_at`, `status`);
ALTER TABLE `cars` ADD INDEX `idx_cars_contact_phone` (`contact_phone`);
ALTER TABLE `cars` ADD INDEX `idx_cars_upload_number` (`upload_number`);
