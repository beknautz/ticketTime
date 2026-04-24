<?php
/**
 * TicketTime Setup Script
 * Run ONCE to seed data and create storage directories.
 * DELETE this file after use.
 */

$setupKey = getenv('SETUP_KEY') ?: '';
if (PHP_SAPI !== 'cli' && $setupKey && ($_GET['key'] ?? '') !== $setupKey) {
    http_response_code(403);
    die('Forbidden.');
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

try {
    $pdo = Database::getInstance();
    echo "✓ Connected to MySQL ({$nl}DB: " . DB_NAME . "){$nl}";

    // ── Schema ────────────────────────────────────────────────────────────────
    // Check if tables already exist
    $tableCount = (int) $pdo->query("SELECT COUNT(*) FROM information_schema.tables
        WHERE table_schema = DATABASE() AND table_name = 'admins'")->fetchColumn();

    if ($tableCount === 0) {
        echo "Creating tables...{$nl}";
        $schema = file_get_contents(__DIR__ . '/sql/schema.sql');
        // Strip any database-selection statements
        $schema = preg_replace('/^\s*(CREATE\s+DATABASE|USE)\s+[^;]+;/im', '', $schema);
        foreach (array_filter(array_map('trim', explode(';', $schema))) as $stmt) {
            if ($stmt && !preg_match('/^\s*--/', $stmt)) {
                $pdo->exec($stmt);
            }
        }
        echo "✓ Tables created{$nl}";
    } else {
        echo "✓ Tables already exist — skipping schema{$nl}";
    }

    // ── Seed data ─────────────────────────────────────────────────────────────
    $adminCount = (int) $pdo->query("SELECT COUNT(*) FROM admins")->fetchColumn();

    if ($adminCount === 0) {
        $hash    = password_hash('Admin1234!', PASSWORD_BCRYPT, ['cost' => 12]);
        $escaped = $pdo->quote($hash);

        $pdo->exec("INSERT INTO admins (name, email, password_hash, role, status) VALUES
            ('System Admin',  'admin@tickettime.local',      {$escaped}, 'admin',      'active'),
            ('Box Office',    'boxoffice@tickettime.local',  {$escaped}, 'box_office', 'active'),
            ('Gate Scanner',  'scanner@tickettime.local',    {$escaped}, 'scanner',    'active')");

        // Events
        $pdo->exec("INSERT INTO events (event_name, event_slug, event_description, event_location,
                event_start, event_end, sale_start, sale_end, status)
            VALUES
            ('Summer Music Festival 2026','summer-music-festival-2026',
             'An amazing outdoor music festival with top artists, food vendors, and family-friendly fun.',
             'Riverside Amphitheater, 100 River Road, Springfield',
             '2026-07-15 16:00:00','2026-07-15 23:00:00','2026-04-01 00:00:00','2026-07-15 14:00:00','active'),
            ('Tech Conference 2026','tech-conference-2026',
             'A full-day conference covering AI, cloud computing, and the future of software.',
             'Downtown Convention Center, 500 Main St, Springfield',
             '2026-09-20 08:00:00','2026-09-20 18:00:00','2026-04-15 00:00:00','2026-09-19 23:59:00','active')");

        // Ticket types for event 1
        $pdo->exec("INSERT INTO ticket_types
                (event_id,ticket_name,ticket_description,price,service_fee,quantity_available,max_per_order,status,sort_order)
            VALUES
            (1,'General Admission','Access to all general admission areas.',45.00,5.00,500,10,'active',1),
            (1,'VIP Access','Premium viewing area, dedicated bar, meet & greet.',125.00,10.00,50,4,'active',2),
            (1,'Group Pack (4 tickets)','Four GA tickets at a discounted rate.',160.00,15.00,50,2,'active',3)");

        // Ticket types for event 2
        $pdo->exec("INSERT INTO ticket_types
                (event_id,ticket_name,ticket_description,price,service_fee,quantity_available,max_per_order,status,sort_order)
            VALUES
            (2,'Standard Pass','Full conference access including all keynotes.',199.00,12.00,300,5,'active',1),
            (2,'Workshop Add-on','Hands-on afternoon workshop (requires Standard Pass).',79.00,5.00,60,2,'active',2),
            (2,'Virtual Ticket','Live stream access to all main stage sessions.',49.00,3.00,1000,10,'active',3)");

        // Settings
        $settings = [
            ['site_name','TicketTime'],['site_url', SITE_URL],
            ['support_email','support@tickettime.local'],['support_phone','(555) 000-0000'],
            ['tax_rate','0.00'],['currency','USD'],['currency_symbol','$'],
            ['stripe_enabled','1'],['email_from_name','TicketTime'],
            ['email_from_address','noreply@tickettime.local'],
            ['willcall_enabled','1'],['scan_auto_reset_ms','3000'],
        ];
        $stmt = $pdo->prepare("INSERT IGNORE INTO settings (setting_key, setting_value) VALUES (?, ?)");
        foreach ($settings as [$k, $v]) $stmt->execute([$k, $v]);

        echo "✓ Seed data inserted{$nl}";
        echo "{$nl}";
        echo $bold("Default admin credentials:") . $nl;
        echo "  Email:    admin@tickettime.local{$nl}";
        echo "  Password: Admin1234!{$nl}";
        echo "{$nl}";
        echo $bold("⚠  CHANGE THE PASSWORD immediately after first login.") . $nl;
    } else {
        echo "✓ Seed data already exists — skipped{$nl}";
    }

    // ── Storage directories ───────────────────────────────────────────────────
    foreach ([QR_PATH, TICKET_PATH, LOG_PATH] as $dir) {
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
            echo "✓ Created: $dir{$nl}";
        }
    }

    echo "{$nl}";
    echo $bold("✓ Setup complete!") . $nl;
    echo "{$nl}";
    echo "Next steps:{$nl}";
    echo "1. Set your Stripe keys in config/stripe.php{$nl}";
    echo "2. Set your mail config in config/mail.php{$nl}";
    echo "3. Set SITE_URL in config/config.php{$nl}";
    echo "4. Stripe webhook URL: " . SITE_URL . "/webhooks/stripe-webhook.php{$nl}";
    echo "5. DELETE this setup.php file{$nl}";
    echo "6. Login: " . SITE_URL . "/admin/login.php{$nl}";

} catch (\Throwable $e) {
    echo "✗ Error: " . $e->getMessage() . $nl;
    if (APP_DEBUG) {
        echo nl2br(htmlspecialchars($e->getTraceAsString())) . $nl;
    }
}

if ($isWeb) {
    echo '</pre></div></body></html>';
}
