<?php
declare(strict_types=1);

define('STRIPE_SECRET_KEY',       getenv('STRIPE_SECRET_KEY')       ?: 'sk_test_REPLACE_ME');
define('STRIPE_PUBLISHABLE_KEY',  getenv('STRIPE_PUBLISHABLE_KEY')  ?: 'pk_test_REPLACE_ME');
define('STRIPE_WEBHOOK_SECRET',   getenv('STRIPE_WEBHOOK_SECRET')   ?: 'whsec_REPLACE_ME');
define('STRIPE_API_VERSION',      '2023-10-16');
