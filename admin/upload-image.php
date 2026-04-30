<?php
// Direct-stream image upload — bypasses PHP temp file entirely.
// Receives raw binary via php://input (Content-Type: image/*).

// File-based step log — IIS swallows non-2xx response bodies, so we log to disk.
$_log = [];
$_logFile = dirname(__DIR__) . '/storage/logs/upload_' . date('Y-m-d') . '.log';
function ulog(string $msg): void {
    global $_log, $_logFile;
    $line = date('H:i:s') . ' ' . $msg;
    $_log[] = $line;
    @file_put_contents($_logFile, $line . "\n", FILE_APPEND | LOCK_EX);
}

// All responses use HTTP 200 so IIS doesn't swallow the body.
// Errors carry {"error": "..."}, success carries {"filename": "..."}.
function ujson(array $data): void {
    header('Content-Type: application/json');
    echo json_encode($data);
    exit;
}

ulog('=== upload-image.php START ===');
ulog('method=' . ($_SERVER['REQUEST_METHOD'] ?? '?'));
ulog('content-type=' . ($_SERVER['CONTENT_TYPE'] ?? '?'));
ulog('content-length=' . ($_SERVER['CONTENT_LENGTH'] ?? '?'));

require_once dirname(__DIR__) . '/config/config.php';
ulog('config ok');

require_once BASE_PATH . '/includes/auth.php';
ulog('auth ok');

if (!isAdminLoggedIn()) {
    ulog('not logged in');
    ujson(['error' => 'Not authenticated. Please reload the page and log in again.']);
}
ulog('admin ok role=' . ($_SESSION['admin_role'] ?? '?'));

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    ulog('wrong method');
    ujson(['error' => 'Method not allowed']);
}

// CSRF via request header (no form field available for raw-body requests)
$token = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
ulog('csrf header present=' . ($token ? 'yes' : 'NO'));
if (!hash_equals(csrfToken(), $token)) {
    ulog('csrf mismatch token=' . substr($token, 0, 8) . '...');
    ujson(['error' => 'Invalid security token']);
}
ulog('csrf ok');

$maxBytes  = 8 * 1024 * 1024;
$uploadDir = BASE_PATH . '/public/assets/img/events/';
ulog('uploadDir=' . $uploadDir);
ulog('dir_exists=' . (is_dir($uploadDir) ? 'yes' : 'no'));
ulog('dir_writable=' . (is_writable($uploadDir) ? 'yes' : 'no'));

// Read raw bytes — no temp file involved
ulog('reading php://input');
$imageRaw = file_get_contents('php://input', false, null, 0, $maxBytes + 1);
ulog('read bytes=' . (is_string($imageRaw) ? strlen($imageRaw) : 'false'));

if ($imageRaw === false || strlen($imageRaw) === 0) {
    ulog('no data');
    ujson(['error' => 'No image data received']);
}

if (strlen($imageRaw) > $maxBytes) {
    ulog('too large');
    ujson(['error' => 'Image must be under 8 MB']);
}

// Validate by magic bytes
$magic  = substr($imageRaw, 0, 12);
$isJpeg = substr($magic, 0, 2) === "\xFF\xD8";
$isPng  = substr($magic, 0, 8) === "\x89PNG\r\n\x1a\n";
$isWebp = substr($magic, 0, 4) === 'RIFF' && substr($magic, 8, 4) === 'WEBP';
ulog('isJpeg=' . ($isJpeg?'y':'n') . ' isPng=' . ($isPng?'y':'n') . ' isWebp=' . ($isWebp?'y':'n'));

if (!$isJpeg && !$isPng && !$isWebp) {
    ulog('invalid type hex=' . bin2hex(substr($magic, 0, 4)));
    ujson(['error' => 'Please upload a JPEG, PNG, or WebP image']);
}

$ext  = $isWebp ? 'webp' : ($isPng ? 'png' : 'jpg');
$name = 'event-' . bin2hex(random_bytes(8)) . '.' . $ext;
ulog('filename=' . $name);

if (!is_dir($uploadDir)) {
    ulog('creating dir');
    $mkOk = mkdir($uploadDir, 0755, true);
    ulog('mkdir=' . ($mkOk ? 'ok' : 'FAILED errno=' . (function_exists('posix_get_last_error') ? posix_strerror(posix_get_last_error()) : 'n/a')));
}

ulog('writing file');
$written = file_put_contents($uploadDir . $name, $imageRaw);
ulog('written=' . var_export($written, true));

if ($written === false) {
    ulog('WRITE FAILED');
    ujson(['error' => 'Could not write image to ' . $uploadDir . ' — check directory permissions. See upload log for details.']);
}

ulog('SUCCESS filename=' . $name);
ujson(['filename' => $name]);
