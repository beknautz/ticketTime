<?php
require_once dirname(__DIR__) . '/config/config.php';
require_once BASE_PATH . '/includes/auth.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['result' => 'invalid', 'message' => 'Method not allowed', 'color' => 'red']);
    exit;
}

// Auth required
if (!isAdminLoggedIn()) {
    http_response_code(401);
    echo json_encode(['result' => 'invalid', 'message' => 'Not authenticated', 'color' => 'red']);
    exit;
}

if (!in_array(currentAdminRole(), ['admin', 'scanner'], true)) {
    http_response_code(403);
    echo json_encode(['result' => 'invalid', 'message' => 'Not authorized to scan', 'color' => 'red']);
    exit;
}

// CSRF
$csrfToken = $_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
if (!hash_equals(csrfToken(), $csrfToken)) {
    http_response_code(403);
    echo json_encode(['result' => 'invalid', 'message' => 'Security token mismatch', 'color' => 'red']);
    exit;
}

$qrToken  = trim($_POST['qr_token'] ?? '');
$eventId  = (int)($_POST['event_id'] ?? 0);
$location = trim($_POST['scan_location'] ?? '');

if (!$qrToken || $eventId <= 0) {
    echo json_encode(['result' => 'invalid', 'message' => 'Missing required fields', 'color' => 'red', 'ticket' => null]);
    exit;
}

// Strip URL prefix if a full URL was scanned (e.g. from a QR containing the full ticket URL)
if (strpos($qrToken, 'token=') !== false) {
    parse_str(parse_url($qrToken, PHP_URL_QUERY) ?: '', $qs);
    $qrToken = $qs['token'] ?? $qrToken;
}

$scanner = new Scanner();
$result  = $scanner->scan($qrToken, $eventId, currentAdminId(), $location);

echo json_encode($result);
