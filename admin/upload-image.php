<?php
// Image upload via base64-encoded URL-encoded POST.
// IIS FastCGI does not forward raw request bodies to php://input for any
// non-multipart content type, so we receive the image as $_POST['image_b64'].

$_logFile = dirname(__DIR__) . '/storage/logs/upload_' . date('Y-m-d') . '.log';
function ulog(string $msg): void {
    global $_logFile;
    @file_put_contents($_logFile, date('H:i:s') . ' ' . $msg . "\n", FILE_APPEND | LOCK_EX);
}
function ujson(array $data): void {
    header('Content-Type: application/json');
    echo json_encode($data);
    exit;
}

ulog('=== upload START method=' . ($_SERVER['REQUEST_METHOD'] ?? '?'));

require_once dirname(__DIR__) . '/config/config.php';
require_once BASE_PATH . '/includes/auth.php';

if (!isAdminLoggedIn()) {
    ulog('not logged in');
    ujson(['error' => 'Not authenticated. Please reload the page and log in again.']);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    ujson(['error' => 'Method not allowed']);
}

$token = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
if (!hash_equals(csrfToken(), $token)) {
    ulog('csrf fail');
    ujson(['error' => 'Invalid security token']);
}

$maxBytes  = 8 * 1024 * 1024;
$uploadDir = BASE_PATH . '/public/assets/uploads/events/';

$b64 = $_POST['image_b64'] ?? '';
ulog('image_b64 length=' . strlen($b64));

if (!$b64) {
    ujson(['error' => 'No image data received']);
}

$imageRaw = base64_decode($b64, true);
if ($imageRaw === false || strlen($imageRaw) === 0) {
    ulog('decode failed');
    ujson(['error' => 'Invalid image encoding']);
}

$size = strlen($imageRaw);
ulog('decoded bytes=' . $size);

if ($size > $maxBytes) {
    ujson(['error' => 'Image must be under 8 MB']);
}

// Validate by magic bytes
$magic  = substr($imageRaw, 0, 12);
$isJpeg = substr($magic, 0, 2) === "\xFF\xD8";
$isPng  = substr($magic, 0, 8) === "\x89PNG\r\n\x1a\n";
$isWebp = substr($magic, 0, 4) === 'RIFF' && substr($magic, 8, 4) === 'WEBP';
ulog('jpeg=' . ($isJpeg?'y':'n') . ' png=' . ($isPng?'y':'n') . ' webp=' . ($isWebp?'y':'n'));

if (!$isJpeg && !$isPng && !$isWebp) {
    ujson(['error' => 'Please upload a JPEG, PNG, or WebP image']);
}

$ext  = $isWebp ? 'webp' : ($isPng ? 'png' : 'jpg');
$name = 'event-' . bin2hex(random_bytes(8)) . '.' . $ext;

if (!is_dir($uploadDir)) {
    mkdir($uploadDir, 0755, true);
}

$written = file_put_contents($uploadDir . $name, $imageRaw);
ulog('written=' . var_export($written, true) . ' file=' . $name);

if ($written === false) {
    ujson(['error' => 'Could not write image to ' . $uploadDir]);
}

ulog('SUCCESS ' . $name);
ujson(['filename' => $name]);
