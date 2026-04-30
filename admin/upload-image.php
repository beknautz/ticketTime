<?php
// Image upload via base64-encoded URL-encoded POST.
// This IIS/FastCGI server cannot write PHP temp files (UPLOAD_ERR_CANT_WRITE)
// and does not forward raw request bodies to php://input. Sending the image
// as application/x-www-form-urlencoded reaches PHP via $_POST with no temp file.

require_once dirname(__DIR__) . '/config/config.php';
require_once BASE_PATH . '/includes/auth.php';

header('Content-Type: application/json');

if (!isAdminLoggedIn()) {
    echo json_encode(['error' => 'Not authenticated']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

$token = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
if (!hash_equals(csrfToken(), $token)) {
    echo json_encode(['error' => 'Invalid security token']);
    exit;
}

$maxBytes  = 8 * 1024 * 1024;
$uploadDir = BASE_PATH . '/public/assets/uploads/events/';

$b64 = $_POST['image_b64'] ?? '';
if (!$b64) {
    echo json_encode(['error' => 'No image data received']);
    exit;
}

$imageRaw = base64_decode($b64, true);
if ($imageRaw === false || strlen($imageRaw) === 0) {
    echo json_encode(['error' => 'Invalid image encoding']);
    exit;
}

if (strlen($imageRaw) > $maxBytes) {
    echo json_encode(['error' => 'Image must be under 8 MB']);
    exit;
}

// Validate by magic bytes
$magic  = substr($imageRaw, 0, 12);
$isJpeg = substr($magic, 0, 2) === "\xFF\xD8";
$isPng  = substr($magic, 0, 8) === "\x89PNG\r\n\x1a\n";
$isWebp = substr($magic, 0, 4) === 'RIFF' && substr($magic, 8, 4) === 'WEBP';

if (!$isJpeg && !$isPng && !$isWebp) {
    echo json_encode(['error' => 'Please upload a JPEG, PNG, or WebP image']);
    exit;
}

$ext  = $isWebp ? 'webp' : ($isPng ? 'png' : 'jpg');
$name = 'event-' . bin2hex(random_bytes(8)) . '.' . $ext;

if (!is_dir($uploadDir)) {
    mkdir($uploadDir, 0755, true);
}

if (file_put_contents($uploadDir . $name, $imageRaw) === false) {
    echo json_encode(['error' => 'Could not save image. Check permissions on ' . $uploadDir]);
    exit;
}

echo json_encode(['filename' => $name]);
