-- Keep only Sharjah and Dubai as receive locations.
UPDATE `cars`
SET `receive_location` = 'sharjah'
WHERE `receive_location` IS NULL
   OR TRIM(`receive_location`) = ''
   OR `receive_location` NOT IN ('sharjah', 'dubai');
