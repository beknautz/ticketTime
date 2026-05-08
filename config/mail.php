<?php
declare(strict_types=1);

defined('MAIL_FROM_NAME')    || define('MAIL_FROM_NAME',    getenv('MAIL_FROM_NAME')    ?: SITE_NAME);
defined('MAIL_FROM_ADDRESS') || define('MAIL_FROM_ADDRESS', getenv('MAIL_FROM_ADDRESS') ?: 'tickets@toppenishrodeotickets.com');
defined('MAIL_REPLY_TO')     || define('MAIL_REPLY_TO',     getenv('MAIL_REPLY_TO')     ?: SUPPORT_EMAIL);

// Driver: sendgrid | smtp | mail
defined('MAIL_DRIVER') || define('MAIL_DRIVER', getenv('MAIL_DRIVER') ?: 'sendgrid');

// SendGrid — set SENDGRID_API_KEY env var on server to persist across git pulls
defined('SENDGRID_API_KEY') || define('SENDGRID_API_KEY', getenv('SENDGRID_API_KEY') ?: 'SG.REPLACE_ME');

// SMTP settings (used when MAIL_DRIVER=smtp)
defined('MAIL_HOST')       || define('MAIL_HOST',       getenv('MAIL_HOST')       ?: 'smtp.sendgrid.net');
defined('MAIL_PORT')       || define('MAIL_PORT',       (int)(getenv('MAIL_PORT') ?: 587));
defined('MAIL_USERNAME')   || define('MAIL_USERNAME',   getenv('MAIL_USERNAME')   ?: 'apikey');
defined('MAIL_PASSWORD')   || define('MAIL_PASSWORD',   getenv('MAIL_PASSWORD')   ?: '');
defined('MAIL_ENCRYPTION') || define('MAIL_ENCRYPTION', getenv('MAIL_ENCRYPTION') ?: 'tls');
