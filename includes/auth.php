<?php
declare(strict_types=1);

function isAdminLoggedIn(): bool
{
    return !empty($_SESSION['admin_id']) && !empty($_SESSION['admin_role']);
}

function requireAdmin(array $allowedRoles = ['admin', 'box_office', 'scanner']): void
{
    if (!isAdminLoggedIn()) {
        redirect(SITE_URL . '/admin/login.php?redirect=' . urlencode($_SERVER['REQUEST_URI']));
    }
    if (!in_array($_SESSION['admin_role'], $allowedRoles, true)) {
        http_response_code(403);
        die('Access denied. Your role does not have permission to view this page.');
    }
}

function requireRole(string ...$roles): void
{
    requireAdmin($roles);
}

function currentAdminId(): int
{
    return (int)($_SESSION['admin_id'] ?? 0);
}

function currentAdminRole(): string
{
    return $_SESSION['admin_role'] ?? '';
}

function currentAdminName(): string
{
    return $_SESSION['admin_name'] ?? 'Admin';
}

function adminLogin(string $email, string $password): bool
{
    $db   = Database::getInstance();
    $stmt = $db->prepare("SELECT * FROM admins WHERE email = ? AND status = 'active' LIMIT 1");
    $stmt->execute([strtolower(trim($email))]);
    $admin = $stmt->fetch();

    if (!$admin || !password_verify($password, $admin['password_hash'])) {
        return false;
    }

    // Update last login
    $db->prepare("UPDATE admins SET last_login = NOW() WHERE admin_id = ?")
       ->execute([$admin['admin_id']]);

    session_regenerate_id(true);
    $_SESSION['admin_id']   = $admin['admin_id'];
    $_SESSION['admin_email']= $admin['email'];
    $_SESSION['admin_name'] = $admin['name'];
    $_SESSION['admin_role'] = $admin['role'];

    return true;
}

function adminLogout(): void
{
    session_unset();
    session_destroy();
    session_start();
    session_regenerate_id(true);
}

function canAdmin(): bool
{
    return currentAdminRole() === 'admin';
}

function canBoxOffice(): bool
{
    return in_array(currentAdminRole(), ['admin', 'box_office'], true);
}

function canScan(): bool
{
    return in_array(currentAdminRole(), ['admin', 'scanner'], true);
}
