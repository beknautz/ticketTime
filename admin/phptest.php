<?php
// Diagnostic test — DELETE AFTER USE
echo "Step 1: PHP running " . PHP_VERSION . "<br>\n"; flush();

require_once dirname(__DIR__) . '/config/config.php';
echo "Step 2: config.php OK<br>\n"; flush();

$eventModel = new Event();
$event = $eventModel->getById(3);
echo "Step 3: getById(3) = " . ($event ? $event['event_name'] : 'NOT FOUND') . "<br>\n"; flush();

echo "Step 4: event_start=" . var_export($event['event_start'] ?? null, true) . "<br>\n"; flush();
echo "Step 5: event_end=" . var_export($event['event_end'] ?? null, true) . "<br>\n"; flush();
echo "Step 6: sale_start=" . var_export($event['sale_start'] ?? null, true) . "<br>\n"; flush();
echo "Step 7: sale_end=" . var_export($event['sale_end'] ?? null, true) . "<br>\n"; flush();
echo "Step 8: event_image=" . var_export($event['event_image'] ?? null, true) . "<br>\n"; flush();
echo "Step 9: status=" . var_export($event['status'] ?? null, true) . "<br>\n"; flush();

echo "Step 10: testing e(event_name)...<br>\n"; flush();
$_ = htmlspecialchars($event['event_name'] ?? '', ENT_QUOTES, 'UTF-8');
echo "Step 11: OK — $_ <br>\n"; flush();

echo "Step 12: testing strtotime(event_start)...<br>\n"; flush();
$ts = strtotime($event['event_start'] ?? '');
echo "Step 13: strtotime = " . var_export($ts, true) . "<br>\n"; flush();

echo "Step 14: testing date(event_start)...<br>\n"; flush();
$d = $ts ? date('Y-m-d\TH:i', $ts) : '';
echo "Step 15: date = $d<br>\n"; flush();

echo "Step 16: testing sale_start block...<br>\n"; flush();
$_ = ($event && $event['sale_start']) ? date('Y-m-d\TH:i', strtotime($event['sale_start'])) : '';
echo "Step 17: sale_start = $_<br>\n"; flush();

echo "Step 18: testing sale_end block...<br>\n"; flush();
$_ = ($event && $event['sale_end']) ? date('Y-m-d\TH:i', strtotime($event['sale_end'])) : '';
echo "Step 19: sale_end = $_<br>\n"; flush();

echo "Step 20: testing event_image block...<br>\n"; flush();
if (!empty($event['event_image'])) {
    echo "Step 21: image exists: " . htmlspecialchars($event['event_image'], ENT_QUOTES, 'UTF-8') . "<br>\n";
} else {
    echo "Step 21: no image<br>\n";
}
flush();

echo "Step 22: testing status loop...<br>\n"; flush();
foreach (['draft', 'active', 'closed', 'archived'] as $s) {
    $selected = ($event['status'] ?? 'draft') === $s ? 'selected' : '';
}
echo "Step 23: status loop OK<br>\n"; flush();

echo "Step 24: including admin-header...<br>\n"; flush();
$pageTitle = 'Diagnostic';
require_once __DIR__ . '/includes/admin-header.php';
echo "<p>Step 25: admin-header OK</p>\n"; flush();

echo "<p>Step 26: including admin-footer...</p>\n"; flush();
require_once __DIR__ . '/includes/admin-footer.php';
