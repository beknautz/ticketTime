<?php
// Not used — event-edit.php uses standard multipart form upload.
http_response_code(404);
header('Content-Type: application/json');
echo json_encode(['error' => 'Not found']);
