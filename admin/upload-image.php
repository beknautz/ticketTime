<?php
// Direct-stream image upload — bypasses PHP temp file entirely.
// Receives raw binary via php://input (Content-Type: image/*).
require_once dirname(__DIR__) . '/config/config.php';
require_once BASE_PATH . '/includes/auth.php';

requireAdmin(['admin']);
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

// CSRF via request header (no form field available for raw-body requests)
$token = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
if (!hash_equals(csrfToken(), $token)) {
    http_response_code(403);
    echo json_encode(['error' => 'Invalid security token']);
    exit;
}

$maxBytes  = 8 * 1024 * 1024;
$uploadDir = BASE_PATH . '/public/assets/uploads/events/';

// Read raw bytes — no temp file involved
$imageRaw = file_get_contents('php://input', false, null, 0, $maxBytes + 1);

if ($imageRaw === false || strlen($imageRaw) === 0) {
    http_response_code(400);
    echo json_encode(['error' => 'No image data received']);
    exit;
}

if (strlen($imageRaw) > $maxBytes) {
    http_response_code(413);
    echo json_encode(['error' => 'Image must be under 8 MB']);
    exit;
}

// Validate by magic bytes
$magic  = substr($imageRaw, 0, 12);
$isJpeg = substr($magic, 0, 2) === "\xFF\xD8";
$isPng  = substr($magic, 0, 8) === "\x89PNG\r\n\x1a\n";
$isWebp = substr($magic, 0, 4) === 'RIFF' && substr($magic, 8, 4) === 'WEBP';

if (!$isJpeg && !$isPng && !$isWebp) {
    http_response_code(415);
    echo json_encode(['error' => 'Please upload a JPEG, PNG, or WebP image']);
    exit;
}

$ext  = $isWebp ? 'webp' : ($isPng ? 'png' : 'jpg');
$name = 'event-' . bin2hex(random_bytes(8)) . '.' . $ext;

if (!is_dir($uploadDir)) {
    mkdir($uploadDir, 0755, true);
}

if (file_put_contents($uploadDir . $name, $imageRaw) === false) {
    http_response_code(500);
    echo json_encode(['error' => 'Could not write image. Check directory permissions on ' . $uploadDir]);
    exit;
}

echo json_encode(['filename' => $name]);
