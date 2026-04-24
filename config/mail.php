<?php
declare(strict_types=1);

define('MAIL_FROM_NAME',    getenv('MAIL_FROM_NAME')    ?: SITE_NAME);
define('MAIL_FROM_ADDRESS', getenv('MAIL_FROM_ADDRESS') ?: 'noreply@tickettime.local');
define('MAIL_REPLY_TO',     getenv('MAIL_REPLY_TO')     ?: SUPPORT_EMAIL);

// SMTP settings (used when MAIL_DRIVER=smtp)
define('MAIL_DRIVER',    getenv('MAIL_DRIVER')    ?: 'mail'); // mail | smtp
define('MAIL_HOST',      getenv('MAIL_HOST')      ?: 'smtp.mailtrap.io');
define('MAIL_PORT',      (int)(getenv('MAIL_PORT') ?: 587));
define('MAIL_USERNAME',  getenv('MAIL_USERNAME')  ?: '');
define('MAIL_PASSWORD',  getenv('MAIL_PASSWORD')  ?: '');
define('MAIL_ENCRYPTION', getenv('MAIL_ENCRYPTION') ?: 'tls'); // tls | ssl
