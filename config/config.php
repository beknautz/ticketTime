<?php
declare(strict_types=1);

// ── Environment ──────────────────────────────────────────────────────────────
define('APP_ENV', getenv('APP_ENV') ?: 'production'); // development | production
define('APP_DEBUG', APP_ENV === 'development');

// ── Site ─────────────────────────────────────────────────────────────────────
define('SITE_NAME', 'Toppenish Rodeo Tickets');
define('SITE_URL', rtrim(getenv('SITE_URL') ?: 'https://trodeo.enigmaiq.ai', '/'));
define('BASE_PATH', dirname(__DIR__));

// ── Paths ─────────────────────────────────────────────────────────────────────
define('STORAGE_PATH', BASE_PATH . '/storage');
define('QR_PATH',      STORAGE_PATH . '/qrcodes');
define('TICKET_PATH',  STORAGE_PATH . '/tickets');
define('LOG_PATH',     STORAGE_PATH . '/logs');

// ── Currency ─────────────────────────────────────────────────────────────────
define('CURRENCY',        'USD');
define('CURRENCY_SYMBOL', '$');
define('TAX_RATE',        0.00); // e.g. 0.08 for 8%

// ── Session ───────────────────────────────────────────────────────────────────
define('SESSION_LIFETIME', 7200); // seconds

// ── Cart ──────────────────────────────────────────────────────────────────────
define('CART_MAX_ITEMS', 20);

// ── Support ───────────────────────────────────────────────────────────────────
define('SUPPORT_EMAIL', getenv('SUPPORT_EMAIL') ?: 'support@toppenishrodeotickets.com');
define('SUPPORT_PHONE', getenv('SUPPORT_PHONE') ?: '(555) 000-0000');

// ── Error handling ───────────────────────────────────────────────────────────
if (APP_DEBUG) {
    ini_set('display_errors', '1');
    ini_set('display_startup_errors', '1');
    error_reporting(E_ALL);
} else {
    ini_set('display_errors', '0');
    error_reporting(0);
    ini_set('log_errors', '1');
    ini_set('error_log', LOG_PATH . '/php_errors.log');
}

// ── Timezone ─────────────────────────────────────────────────────────────────
date_default_timezone_set(getenv('TZ') ?: 'America/Chicago');

// ── Autoloader (simple) ───────────────────────────────────────────────────────
spl_autoload_register(function (string $class): void {
    $file = BASE_PATH . '/classes/' . $class . '.php';
    if (file_exists($file)) {
        require_once $file;
    }
});

// ── Bootstrap ─────────────────────────────────────────────────────────────────
require_once BASE_PATH . '/config/database.php';
require_once BASE_PATH . '/config/stripe.php';
require_once BASE_PATH . '/config/mail.php';
require_once BASE_PATH . '/includes/helpers.php';
require_once BASE_PATH . '/includes/csrf.php';
require_once BASE_PATH . '/includes/auth.php';
require_once BASE_PATH . '/includes/customer-auth.php';

// Start session
if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params([
        'lifetime' => SESSION_LIFETIME,
        'path'     => '/',
        'secure'   => (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off'),
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}
