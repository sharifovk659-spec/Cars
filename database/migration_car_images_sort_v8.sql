-- Allow many photos per car (admin max_car_images).
-- Old CHECK limited sort_order to 1..5; TINYINT max is 255.

ALTER TABLE `car_images`
    DROP CHECK `chk_car_images_sort_order`;

ALTER TABLE `car_images`
    MODIFY COLUMN `sort_order` INT UNSIGNED NOT NULL DEFAULT 1;
