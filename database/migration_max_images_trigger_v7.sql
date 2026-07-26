-- Remove hard DB limit of 5 photos per car.
-- Photo count is controlled by Admin Settings (max_car_images) in PHP.

DROP TRIGGER IF EXISTS `trg_car_images_before_insert`;
