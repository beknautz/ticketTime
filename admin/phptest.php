<?php
// Diagnostic test — DELETE AFTER USE
echo "Step 1: PHP running " . PHP_VERSION . "<br>\n"; flush();

require_once dirname(__DIR__) . '/config/config.php';
echo "Step 2: config.php OK, session_status=" . session_status() . "<br>\n"; flush();

echo "Step 3: session data keys = " . implode(', ', array_keys($_SESSION)) . "<br>\n"; flush();
echo "Step 4: isAdminLoggedIn = " . (isAdminLoggedIn() ? 'YES' : 'NO') . "<br>\n"; flush();

echo "Step 5: calling csrfToken()...<br>\n"; flush();
$tok = csrfToken();
echo "Step 6: csrfToken OK, length=" . strlen($tok) . "<br>\n"; flush();

echo "Step 7: calling csrfField()...<br>\n"; flush();
$cf = csrfField();
echo "Step 8: csrfField OK, length=" . strlen($cf) . "<br>\n"; flush();

echo "Step 9: calling e(currentAdminName())...<br>\n"; flush();
$n = e(currentAdminName());
echo "Step 10: name OK = $n<br>\n"; flush();

echo "Step 11: calling e(currentAdminRole())...<br>\n"; flush();
$r = e(currentAdminRole());
echo "Step 12: role OK = $r<br>\n"; flush();

$eventModel = new Event();
$event = $eventModel->getById(3);
echo "Step 13: event loaded = " . ($event ? $event['event_name'] : 'NOT FOUND') . "<br>\n"; flush();

echo "Step 14: all pre-render checks passed — including admin-header next<br>\n"; flush();
$pageTitle = 'Diagnostic';
require_once __DIR__ . '/includes/admin-header.php';
echo "<p>Step 15: admin-header OK</p>\n"; flush();

echo "<p>Step 16: emitting csrfField into form context...</p>\n"; flush();
echo '<form method="post">' . csrfField() . '</form>';
echo "<p>Step 17: form csrf OK</p>\n"; flush();

echo "<p>Step 18: including admin-footer...</p>\n"; flush();
require_once __DIR__ . '/includes/admin-footer.php';
