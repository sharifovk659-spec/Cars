-- Mini App / Bot welcome message in Russian + clear upload numbers from forms
UPDATE settings
SET setting_value = '👋 <b>Добро пожаловать, {name}!</b>\n\nВведите 4 последние символа vinCode машины'
WHERE setting_key = 'welcome_message';

UPDATE cars
SET upload_number = NULL
WHERE upload_number IS NOT NULL AND TRIM(upload_number) <> '';
