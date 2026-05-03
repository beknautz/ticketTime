<?php
// Temporary diagnostic — delete this file after use
header('Content-Type: text/plain');

echo 'PHP version: ' . PHP_VERSION . "\n";
echo '__DIR__: ' . __DIR__ . "\n";
echo 'dirname 1: ' . dirname(__DIR__, 1) . "\n";
echo 'dirname 2: ' . dirname(__DIR__, 2) . "\n";

$configPath = dirname(__DIR__, 2) . '/config/config.php';
echo 'config path: ' . $configPath . "\n";
echo 'config exists: ' . (file_exists($configPath) ? 'YES' : 'NO') . "\n";

$dbPath = dirname(__DIR__, 2) . '/config/database.php';
echo 'database.php exists: ' . (file_exists($dbPath) ? 'YES' : 'NO') . "\n";

$includePath = dirname(__DIR__, 2) . '/includes/helpers.php';
echo 'helpers.php exists: ' . (file_exists($includePath) ? 'YES' : 'NO') . "\n";

// Try loading config and catch any error
try {
    ob_start();
    require_once $configPath;
    ob_end_clean();
    echo 'config loaded: OK' . "\n";
    echo 'BASE_PATH: ' . BASE_PATH . "\n";
    echo 'SITE_URL: ' . SITE_URL . "\n";
} catch (Throwable $e) {
    ob_end_clean();
    echo 'config load FAILED: ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine() . "\n";
}
