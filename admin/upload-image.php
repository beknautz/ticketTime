<?php
// Standalone AJAX image upload endpoint (reserved for future use).
// event-edit.php uses standard multipart form upload instead.
http_response_code(404);
echo json_encode(['error' => 'Not used']);
