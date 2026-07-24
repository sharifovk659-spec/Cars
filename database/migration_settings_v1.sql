-- Default settings for Telegram Cars
USE `telegram_cars`;

INSERT INTO `settings` (`setting_key`, `setting_value`, `description`) VALUES
    ('bot_name', 'Telegram Cars', 'Номи бот'),
    ('welcome_message', '👋 <b>Хуш омадед, {name}!</b>\n\n🔍 Лутфан <b>VIN Code</b>-и мошин ё <b>5 рақами охирин</b>-ро фиристед.', 'Матни хушомад'),
    ('not_found_message', '❌ <b>Мошин ёфт нашуд</b>\n\nДар бораи <code>{query}</code> маълумот нест.', 'Матни "мошин ёфт нашуд"'),
    ('contact_phone', '', 'Рақами контакт'),
    ('max_car_images', '5', 'Лимити суратҳо'),
    ('company_name', 'Telegram Cars', 'Номи ширкат'),
    ('company_logo', '', 'Логотип'),
    ('telegram_bot_token', '', 'Token-и Telegram Bot')
ON DUPLICATE KEY UPDATE `setting_key` = `setting_key`;
