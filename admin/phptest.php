<?php
// Diagnostic test — DELETE AFTER USE
require_once dirname(__DIR__) . '/config/config.php';
require_once BASE_PATH . '/includes/auth.php';

$eventModel = new Event();
$event = $eventModel->getById(3);

// Write steps to a log file AND output them — this bypasses IIS output buffering issues
$log = BASE_PATH . '/storage/logs/phptest_' . date('His') . '.txt';
$steps = [];

function step(int $n, string $msg) use (&$steps, $log) {
    $steps[] = "Step $n: $msg";
    @file_put_contents($log, implode("\n", $steps), LOCK_EX);
    echo "Step $n: $msg<br>\n";
    flush();
}

step(1, 'event loaded: ' . ($event ? $event['event_name'] : 'NOT FOUND'));
step(2, 'event_start=' . var_export($event['event_start'] ?? null, true));
step(3, 'event_end=' . var_export($event['event_end'] ?? null, true));
step(4, 'sale_start=' . var_export($event['sale_start'] ?? null, true));
step(5, 'sale_end=' . var_export($event['sale_end'] ?? null, true));
step(6, 'event_image=' . var_export($event['event_image'] ?? null, true));
step(7, 'status=' . var_export($event['status'] ?? null, true));
step(8, 'event_description length=' . strlen($event['event_description'] ?? ''));

step(9, 'testing e() on event_name...');
$_ = htmlspecialchars($event['event_name'] ?? '', ENT_QUOTES, 'UTF-8');
step(10, 'e(event_name) OK');

step(11, 'testing strtotime(event_start)...');
$ts = strtotime($event['event_start'] ?? '');
step(12, 'strtotime OK: ' . var_export($ts, true));

step(13, 'testing date() on event_start...');
$d = $ts ? date('Y-m-d\TH:i', $ts) : '';
step(14, 'date OK: ' . $d);

step(15, 'testing e() on date result...');
$_ = htmlspecialchars($d, ENT_QUOTES, 'UTF-8');
step(16, 'e(date) OK');

step(17, 'testing sale_start conditional...');
$_ = ($event && $event['sale_start']) ? date('Y-m-d\TH:i', strtotime($event['sale_start'])) : '';
step(18, 'sale_start OK: ' . $_);

step(19, 'testing sale_end conditional...');
$_ = ($event && $event['sale_end']) ? date('Y-m-d\TH:i', strtotime($event['sale_end'])) : '';
step(20, 'sale_end OK: ' . $_);

step(21, 'testing event_image block...');
if (!empty($event['event_image'])) {
    $_ = htmlspecialchars($event['event_image'], ENT_QUOTES, 'UTF-8');
    step(22, 'event_image e() OK: ' . $_);
} else {
    step(22, 'event_image empty/null — skipped');
}

step(23, 'testing status select loop...');
foreach (['draft', 'active', 'closed', 'archived'] as $s) {
    $selected = ($event['status'] ?? 'draft') === $s ? 'selected' : '';
}
step(24, 'status loop OK');

step(25, 'including admin-footer...');
require_once __DIR__ . '/includes/admin-footer.php';
// footer outputs </body></html> etc — reaching here means footer is fine

step(26, 'COMPLETE — all sections passed');
