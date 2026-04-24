<?php
/**
 * One-time admin password reset helper.
 * DELETE this file immediately after use.
 */
require_once __DIR__ . '/config/config.php';

$password = 'Admin1234!';
$hash     = password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);

$db   = Database::getInstance();
$stmt = $db->prepare("UPDATE admins SET password_hash = ?");
$stmt->execute([$hash]);

$count = $stmt->rowCount();

echo "<!DOCTYPE html><html><head>";
echo "<link rel='stylesheet' href='https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css'>";
echo "</head><body><div class='container py-5'>";
echo "<div class='alert alert-success'>";
echo "<h4>✓ Password reset for {$count} admin account(s)</h4>";
echo "<p>All admin accounts now have the password: <strong>{$password}</strong></p>";
echo "<p><a href='https://trodeo.enigmaiq.ai/admin/login.php' class='btn btn-primary'>Go to Admin Login</a></p>";
echo "</div>";
echo "<div class='alert alert-warning'><strong>⚠ Delete this file immediately!</strong> " .
     "It resets passwords without authentication.</div>";
echo "</div></body></html>";
