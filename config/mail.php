<?php
declare(strict_types=1);

// Read overrides from settings.json (gitignored) — survives git pulls
$_mailSettings = [];
$_mailSettingsFile = defined('STORAGE_PATH') ? STORAGE_PATH . '/settings.json' : '';
if ($_mailSettingsFile && file_exists($_mailSettingsFile)) {
    $_mailSettings = json_decode(file_get_contents($_mailSettingsFile), true) ?: [];
}

defined('MAIL_FROM_NAME')    || define('MAIL_FROM_NAME',    $_mailSettings['mail_from_name']    ?? getenv('MAIL_FROM_NAME')    ?: SITE_NAME);
defined('MAIL_FROM_ADDRESS') || define('MAIL_FROM_ADDRESS', $_mailSettings['mail_from_address'] ?? getenv('MAIL_FROM_ADDRESS') ?: 'tickets@toppenishrodeotickets.com');
defined('MAIL_REPLY_TO')     || define('MAIL_REPLY_TO',     getenv('MAIL_REPLY_TO')     ?: SUPPORT_EMAIL);

// Driver: sendgrid | smtp | mail
defined('MAIL_DRIVER') || define('MAIL_DRIVER', getenv('MAIL_DRIVER') ?: 'sendgrid');

// SendGrid — key stored via Admin → Settings → Mail, falls back to env var then placeholder
defined('SENDGRID_API_KEY') || define('SENDGRID_API_KEY', $_mailSettings['sendgrid_api_key'] ?? getenv('SENDGRID_API_KEY') ?: 'SG.REPLACE_ME');

unset($_mailSettings, $_mailSettingsFile);

// SMTP settings (used when MAIL_DRIVER=smtp)
defined('MAIL_HOST')       || define('MAIL_HOST',       getenv('MAIL_HOST')       ?: 'smtp.sendgrid.net');
defined('MAIL_PORT')       || define('MAIL_PORT',       (int)(getenv('MAIL_PORT') ?: 587));
defined('MAIL_USERNAME')   || define('MAIL_USERNAME',   getenv('MAIL_USERNAME')   ?: 'apikey');
defined('MAIL_PASSWORD')   || define('MAIL_PASSWORD',   getenv('MAIL_PASSWORD')   ?: '');
defined('MAIL_ENCRYPTION') || define('MAIL_ENCRYPTION', getenv('MAIL_ENCRYPTION') ?: 'tls');
