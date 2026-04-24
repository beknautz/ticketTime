<?php
/**
 * TicketTime Setup Script
 *
 * Run this ONCE to initialize the database.
 * DELETE or restrict this file immediately after use.
 *
 * Usage: php setup.php  (CLI)
 *    or: visit https://yourdomain.com/setup.php  (web, once only)
 */

// Basic protection
$setupKey = getenv('SETUP_KEY') ?: '';
if (PHP_SAPI !== 'cli' && $setupKey && ($_GET['key'] ?? '') !== $setupKey) {
    http_response_code(403);
    die('Forbidden. Set SETUP_KEY env var or pass ?key=yourkey');
}

require_once __DIR__ . '/config/config.php';

$isWeb = PHP_SAPI !== 'cli';
$nl    = $isWeb ? "<br>\n" : "\n";
$bold  = fn($s) => $isWeb ? "<strong>$s</strong>" : $s;

if ($isWeb) {
    echo '<!DOCTYPE html><html><head><title>TicketTime Setup</title>';
    echo '<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">';
    echo '</head><body><div class="container py-4"><h1 class="fw-bold">TicketTime Setup</h1><pre class="bg-dark text-light p-4 rounded">';
}

$pdo = null;

try {
    // Connect directly to the configured database
    $dsn = sprintf('mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4', DB_HOST, DB_PORT, DB_NAME);
    $pdo = new PDO($dsn, DB_USER, DB_PASS, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);

    echo "✓ Connected to MySQL{$nl}";
    echo "✓ Database `" . DB_NAME . "` ready{$nl}";

    // Run schema
    $schema = file_get_contents(__DIR__ . '/sql/schema.sql');
    // Remove USE statement (already selected)
    $schema = preg_replace('/^USE\s+\w+;/m', '', $schema);
    // Split on semicolon
    foreach (array_filter(array_map('trim', explode(';', $schema))) as $sql) {
        if ($sql) $pdo->exec($sql);
    }
    echo "✓ Schema created{$nl}";

    // Check if seed already run
    $count = $pdo->query("SELECT COUNT(*) FROM admins")->fetchColumn();
    if ((int)$count === 0) {
        // Generate correct password hash for "Admin1234!"
        $hash = password_hash('Admin1234!', PASSWORD_BCRYPT, ['cost' => 12]);

        // Update seed admin hash
        $seed = file_get_contents(__DIR__ . '/sql/seed.sql');
        $seed = str_replace(
            "'admin@tickettime.local', '\$2y\$12\$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2uheWG/igi.'",
            "'admin@tickettime.local', '" . $seed_hash = $hash . "'",
            $seed
        );

        // Run seed
        $seedSql = file_get_contents(__DIR__ . '/sql/seed.sql');
        // Replace placeholder hash with real one
        $seedSql = preg_replace(
            '/\\\$2y\\\$12\\\$92IXUNpkjO0rOQ5byMi\.Ye4oKoEa3Ro9llC\/\.og\/at2uheWG\/igi\./g',
            addslashes($hash),
            $seedSql
        );

        // Use real hash
        $seedSql = preg_replace('/\$2y\$12\$92IXUNpkjO0rOQ5byMi\.Ye4oKoEa3Ro9llC\/\.og\/at2uheWG\/igi\./', $hash, $seedSql);

        $seedSql = preg_replace('/^USE\s+\w+;/m', '', $seedSql);
        foreach (array_filter(array_map('trim', explode(';', $seedSql))) as $sql) {
            if ($sql) $pdo->exec($sql);
        }
        echo "✓ Seed data inserted{$nl}";
        echo $nl;
        echo $bold("Default admin credentials:") . $nl;
        echo "  Email:    admin@tickettime.local{$nl}";
        echo "  Password: Admin1234!{$nl}";
        echo $nl;
        echo $bold("⚠  CHANGE THE DEFAULT PASSWORD IMMEDIATELY after login.") . $nl;
    } else {
        echo "✓ Seed data already exists - skipped{$nl}";
    }

    // Create storage directories
    $dirs = [QR_PATH, TICKET_PATH, LOG_PATH];
    foreach ($dirs as $dir) {
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
            echo "✓ Created directory: $dir{$nl}";
        }
    }

    echo $nl;
    echo $bold("✓ Setup complete!") . $nl;
    echo $nl;
    echo "Next steps:{$nl}";
    echo "1. Edit config/stripe.php with your Stripe API keys{$nl}";
    echo "2. Edit config/mail.php with your mail server settings{$nl}";
    echo "3. Set SITE_URL in config/config.php{$nl}";
    echo "4. Configure your Stripe webhook URL: " . SITE_URL . "/webhooks/stripe-webhook.php{$nl}";
    echo "5. DELETE or restrict this setup.php file{$nl}";
    echo "6. Login at: " . SITE_URL . "/admin/login.php{$nl}";

} catch (\Throwable $e) {
    echo "✗ Error: " . $e->getMessage() . $nl;
    if (APP_DEBUG) {
        echo $e->getTraceAsString() . $nl;
    }
}

if ($isWeb) {
    echo '</pre></div></body></html>';
}
