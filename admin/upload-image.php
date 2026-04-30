<?php
// Image upload via base64-encoded URL-encoded POST.
$_logFile = dirname(__DIR__) . '/storage/logs/upload_' . date('Y-m-d') . '.log';
function ulog(string $msg): void {
    global $_logFile;
    @file_put_contents($_logFile, date('H:i:s') . ' [b64] ' . $msg . "\n", FILE_APPEND | LOCK_EX);
}
ulog('START method=' . ($_SERVER['REQUEST_METHOD'] ?? '?')
    . ' ct=' . ($_SERVER['CONTENT_TYPE'] ?? '?')
    . ' cl=' . ($_SERVER['CONTENT_LENGTH'] ?? '?'));

require_once dirname(__DIR__) . '/config/config.php';
ulog('config ok');
require_once BASE_PATH . '/includes/auth.php';
ulog('auth ok');

header('Content-Type: application/json');

if (!isAdminLoggedIn()) {
    ulog('not logged in');
    echo json_encode(['error' => 'Not authenticated']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    ulog('wrong method');
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

$token = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
if (!hash_equals(csrfToken(), $token)) {
    ulog('csrf fail');
    echo json_encode(['error' => 'Invalid security token']);
    exit;
}
ulog('csrf ok');

$maxBytes  = 8 * 1024 * 1024;
$uploadDir = BASE_PATH . '/public/assets/uploads/events/';

$b64 = $_POST['image_b64'] ?? '';
ulog('POST keys=' . implode(',', array_keys($_POST)) . ' b64_len=' . strlen($b64));

if (!$b64) {
    ulog('no b64 in POST — POST size=' . strlen(file_get_contents('php://input')));
    echo json_encode(['error' => 'No image data received — POST was empty']);
    exit;
}

$imageRaw = base64_decode($b64, true);
ulog('decoded=' . (is_string($imageRaw) ? strlen($imageRaw) : 'false'));

if ($imageRaw === false || strlen($imageRaw) === 0) {
    echo json_encode(['error' => 'Invalid image encoding']);
    exit;
}

if (strlen($imageRaw) > $maxBytes) {
    echo json_encode(['error' => 'Image must be under 8 MB']);
    exit;
}

$magic  = substr($imageRaw, 0, 12);
$isJpeg = substr($magic, 0, 2) === "\xFF\xD8";
$isPng  = substr($magic, 0, 8) === "\x89PNG\r\n\x1a\n";
$isWebp = substr($magic, 0, 4) === 'RIFF' && substr($magic, 8, 4) === 'WEBP';
ulog('jpeg=' . ($isJpeg?'y':'n') . ' png=' . ($isPng?'y':'n') . ' webp=' . ($isWebp?'y':'n'));

if (!$isJpeg && !$isPng && !$isWebp) {
    echo json_encode(['error' => 'Please upload a JPEG, PNG, or WebP image']);
    exit;
}

$ext  = $isWebp ? 'webp' : ($isPng ? 'png' : 'jpg');
$name = 'event-' . bin2hex(random_bytes(8)) . '.' . $ext;
ulog('writing ' . $name . ' to ' . $uploadDir);

if (!is_dir($uploadDir)) {
    mkdir($uploadDir, 0755, true);
}

$written = file_put_contents($uploadDir . $name, $imageRaw);
ulog('written=' . var_export($written, true));

if ($written === false) {
    echo json_encode(['error' => 'Could not save image. Check permissions on ' . $uploadDir]);
    exit;
}

ulog('SUCCESS ' . $name);
echo json_encode(['filename' => $name]);
