<?php
require_once dirname(__DIR__, 2) . '/config/config.php';
require_once BASE_PATH . '/includes/auth.php';

requireAdmin(['admin']);

$checks = [];

// ── Database ──────────────────────────────────────────────────────────────────
try {
    $db = Database::getInstance();
    $db->query("SELECT 1");
    $checks[] = ['label' => 'Database connection', 'ok' => true, 'detail' => DB_HOST . ' / ' . DB_NAME];
} catch (\Throwable $e) {
    $checks[] = ['label' => 'Database connection', 'ok' => false, 'detail' => $e->getMessage()];
}

// ── Stripe key format ─────────────────────────────────────────────────────────
$skSet = strncmp(STRIPE_SECRET_KEY,      'sk_',    3) === 0;
$pkSet = strncmp(STRIPE_PUBLISHABLE_KEY, 'pk_',    3) === 0;
$whSet = strncmp(STRIPE_WEBHOOK_SECRET,  'whsec_', 6) === 0;
$checks[] = ['label' => 'Stripe secret key',      'ok' => $skSet, 'detail' => $skSet ? substr(STRIPE_SECRET_KEY, 0, 8) . '…' : 'Not set (still REPLACE_ME)'];
$checks[] = ['label' => 'Stripe publishable key',  'ok' => $pkSet, 'detail' => $pkSet ? substr(STRIPE_PUBLISHABLE_KEY, 0, 8) . '…' : 'Not set (still REPLACE_ME)'];
$checks[] = ['label' => 'Stripe webhook secret',   'ok' => $whSet, 'detail' => $whSet ? 'whsec_…' : 'Not set (still REPLACE_ME)'];

// ── Stripe API reachability ───────────────────────────────────────────────────
if ($skSet) {
    $ch = curl_init('https://api.stripe.com/v1/account');
    curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_USERPWD => STRIPE_SECRET_KEY . ':', CURLOPT_TIMEOUT => 10]);
    $resp = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlErr  = curl_error($ch);
    curl_close($ch);
    if ($curlErr) {
        $checks[] = ['label' => 'Stripe API reachable', 'ok' => false, 'detail' => 'cURL error: ' . $curlErr];
    } elseif ($httpCode === 200) {
        $data = json_decode($resp, true);
        $checks[] = ['label' => 'Stripe API reachable', 'ok' => true, 'detail' => 'Account: ' . ($data['email'] ?? $data['id'] ?? 'OK')];
    } else {
        $data = json_decode($resp, true);
        $checks[] = ['label' => 'Stripe API reachable', 'ok' => false, 'detail' => 'HTTP ' . $httpCode . ': ' . ($data['error']['message'] ?? $resp)];
    }
} else {
    $checks[] = ['label' => 'Stripe API reachable', 'ok' => false, 'detail' => 'Skipped — key not configured'];
}

// ── cURL extension ────────────────────────────────────────────────────────────
$checks[] = ['label' => 'PHP cURL extension', 'ok' => function_exists('curl_init'), 'detail' => function_exists('curl_init') ? curl_version()['version'] : 'Not loaded'];

// ── Storage directories ───────────────────────────────────────────────────────
foreach (['logs' => LOG_PATH, 'qrcodes' => QR_PATH, 'tickets' => TICKET_PATH] as $name => $path) {
    $exists   = is_dir($path);
    $writable = $exists && is_writable($path);
    if (!$exists) {
        @mkdir($path, 0755, true);
        $exists = is_dir($path);
        $writable = $exists && is_writable($path);
    }
    $checks[] = ['label' => "storage/{$name} writable", 'ok' => $writable, 'detail' => $path . ($writable ? ' ✓' : (!$exists ? ' — directory missing' : ' — not writable'))];
}

// ── Mail / SendGrid config ────────────────────────────────────────────────────
$sgSet = defined('SENDGRID_API_KEY') && strncmp(SENDGRID_API_KEY, 'SG.', 3) === 0 && SENDGRID_API_KEY !== 'SG.REPLACE_ME';
$checks[] = ['label' => 'Mail driver',       'ok' => true,  'detail' => MAIL_DRIVER];
$checks[] = ['label' => 'SendGrid API key',  'ok' => $sgSet, 'detail' => $sgSet ? substr(SENDGRID_API_KEY, 0, 10) . '…' : 'Not set (still SG.REPLACE_ME)'];
$checks[] = ['label' => 'Mail from address', 'ok' => MAIL_FROM_ADDRESS !== 'noreply@tickettime.local', 'detail' => MAIL_FROM_ADDRESS];

if ($sgSet) {
    $ch = curl_init('https://api.sendgrid.com/v3/user/profile');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER     => ['Authorization: Bearer ' . SENDGRID_API_KEY],
        CURLOPT_TIMEOUT        => 10,
    ]);
    $sgResp = curl_exec($ch);
    $sgCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $sgErr  = curl_error($ch);
    curl_close($ch);
    if ($sgErr) {
        $checks[] = ['label' => 'SendGrid API reachable', 'ok' => false, 'detail' => 'cURL error: ' . $sgErr];
    } elseif ($sgCode === 200) {
        $sgData = json_decode($sgResp, true);
        $checks[] = ['label' => 'SendGrid API reachable', 'ok' => true, 'detail' => 'Account: ' . ($sgData['email'] ?? 'OK')];
    } else {
        $sgData = json_decode($sgResp, true);
        $checks[] = ['label' => 'SendGrid API reachable', 'ok' => false, 'detail' => 'HTTP ' . $sgCode . ': ' . ($sgData['errors'][0]['message'] ?? $sgResp)];
    }
} else {
    $checks[] = ['label' => 'SendGrid API reachable', 'ok' => false, 'detail' => 'Skipped — key not configured'];
}

// ── PHP version ───────────────────────────────────────────────────────────────
$phpOk = version_compare(PHP_VERSION, '7.4', '>=');
$checks[] = ['label' => 'PHP version', 'ok' => $phpOk, 'detail' => PHP_VERSION];

// ── SITE_URL / APP_ENV ────────────────────────────────────────────────────────
$checks[] = ['label' => 'SITE_URL',  'ok' => true, 'detail' => SITE_URL];
$checks[] = ['label' => 'APP_ENV',   'ok' => true, 'detail' => APP_ENV . (APP_DEBUG ? ' (debug ON)' : '')];

// ── Test email send ───────────────────────────────────────────────────────────
$testEmailResult = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['send_test_email'])) {
    verifyCsrf();
    $testTo = trim($_POST['test_email'] ?? '');
    if ($testTo && filter_var($testTo, FILTER_VALIDATE_EMAIL) && $sgSet) {
        $payload = [
            'personalizations' => [['to' => [['email' => $testTo]]]],
            'from'    => ['email' => MAIL_FROM_ADDRESS, 'name' => MAIL_FROM_NAME],
            'subject' => 'TicketTime Test Email',
            'content' => [['type' => 'text/plain', 'value' => 'This is a test email from ' . SITE_NAME . ' diagnostics.']],
        ];
        $ch = curl_init('https://api.sendgrid.com/v3/mail/send');
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => json_encode($payload),
            CURLOPT_HTTPHEADER     => ['Authorization: Bearer ' . SENDGRID_API_KEY, 'Content-Type: application/json'],
            CURLOPT_TIMEOUT        => 15,
        ]);
        $sgTestResp = curl_exec($ch);
        $sgTestCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $sgTestErr  = curl_error($ch);
        curl_close($ch);

        if ($sgTestErr) {
            $testEmailResult = ['ok' => false, 'detail' => 'cURL error: ' . $sgTestErr];
        } elseif ($sgTestCode === 202) {
            $testEmailResult = ['ok' => true, 'detail' => 'Sent! Check inbox at ' . $testTo];
        } else {
            $decoded = json_decode($sgTestResp, true);
            $msg = '';
            if (!empty($decoded['errors'])) {
                $msg = implode('; ', array_column($decoded['errors'], 'message'));
            }
            $testEmailResult = ['ok' => false, 'detail' => 'HTTP ' . $sgTestCode . ': ' . ($msg ?: $sgTestResp)];
        }
    } else {
        $testEmailResult = ['ok' => false, 'detail' => 'Invalid email or SendGrid key not configured.'];
    }
}

$allOk = !in_array(false, array_column($checks, 'ok'), true);

$pageTitle = 'Diagnostics';
require_once __DIR__ . '/includes/admin-header.php';
?>
<div class="d-flex justify-content-between align-items-center mb-4">
  <h2 class="fw-bold mb-0">System Diagnostics</h2>
  <span class="badge bg-<?= $allOk ? 'success' : 'danger' ?> fs-6"><?= $allOk ? 'All checks passed' : 'Issues found' ?></span>
</div>

<div class="card shadow-sm">
  <div class="card-body p-0">
    <table class="table mb-0 align-middle">
      <thead class="table-light">
        <tr><th style="width:260px">Check</th><th style="width:80px">Status</th><th>Detail</th></tr>
      </thead>
      <tbody>
        <?php foreach ($checks as $c): ?>
          <tr>
            <td class="fw-semibold small"><?= e($c['label']) ?></td>
            <td>
              <?php if ($c['ok']): ?>
                <span class="badge bg-success"><i class="bi bi-check-lg"></i> OK</span>
              <?php else: ?>
                <span class="badge bg-danger"><i class="bi bi-x-lg"></i> Fail</span>
              <?php endif; ?>
            </td>
            <td class="small text-<?= $c['ok'] ? 'muted' : 'danger fw-semibold' ?>"><?= e($c['detail']) ?></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>

<!-- Test Email -->
<div class="card shadow-sm mt-4">
  <div class="card-header fw-bold"><i class="bi bi-envelope me-1"></i>Send Test Email</div>
  <div class="card-body">
    <?php if ($testEmailResult): ?>
      <div class="alert alert-<?= $testEmailResult['ok'] ? 'success' : 'danger' ?> mb-3">
        <?= e($testEmailResult['detail']) ?>
      </div>
    <?php endif; ?>
    <form method="post" class="d-flex gap-2 align-items-end">
      <?= csrfField() ?>
      <div>
        <label class="form-label small fw-semibold mb-1">Recipient email</label>
        <input type="email" name="test_email" class="form-control form-control-sm"
               style="width:280px" placeholder="you@example.com"
               value="<?= e($_POST['test_email'] ?? '') ?>">
      </div>
      <button type="submit" name="send_test_email" value="1" class="btn btn-primary btn-sm">
        <i class="bi bi-send me-1"></i>Send Test
      </button>
    </form>
    <div class="form-text mt-1">Sends directly via SendGrid API — bypasses the Mailer class to isolate issues.</div>
  </div>
</div>

<?php require_once __DIR__ . '/includes/admin-footer.php'; ?>
