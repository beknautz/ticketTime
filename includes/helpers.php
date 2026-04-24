<?php
declare(strict_types=1);

function generateToken(int $bytes = 32): string
{
    return bin2hex(random_bytes($bytes));
}

function generatePublicOrderId(): string
{
    // Format: TT-YYYY-XXXXXXXX
    return 'TT-' . date('Y') . '-' . strtoupper(substr(bin2hex(random_bytes(5)), 0, 8));
}

function formatMoney(float $amount): string
{
    return CURRENCY_SYMBOL . number_format($amount, 2);
}

function formatDate(string $dateStr, string $format = 'D, M j, Y g:i A'): string
{
    return date($format, strtotime($dateStr));
}

function sanitize(string $input): string
{
    return htmlspecialchars(trim($input), ENT_QUOTES, 'UTF-8');
}

function e(string $s): string
{
    return htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
}

function redirect(string $url): void
{
    header('Location: ' . $url);
    exit;
}

function isHtmxRequest(): bool
{
    return isset($_SERVER['HTTP_HX_REQUEST']) && $_SERVER['HTTP_HX_REQUEST'] === 'true';
}

function jsonResponse(array $data, int $code = 200): void
{
    http_response_code($code);
    header('Content-Type: application/json');
    echo json_encode($data);
    exit;
}

function htmxAlert(string $type, string $message, string $extra = ''): string
{
    return sprintf(
        '<div class="alert alert-%s alert-dismissible fade show" role="alert">%s<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>%s',
        e($type),
        e($message),
        $extra
    );
}

function getCart(): array
{
    return $_SESSION['cart'] ?? [];
}

function setCart(array $cart): void
{
    $_SESSION['cart'] = $cart;
}

function clearCart(): void
{
    unset($_SESSION['cart']);
}

function getCartTotal(): array
{
    $cart     = getCart();
    $subtotal = 0.0;
    $fees     = 0.0;

    foreach ($cart as $item) {
        $subtotal += (float)$item['price'] * (int)$item['quantity'];
        $fees     += (float)$item['service_fee'] * (int)$item['quantity'];
    }

    $tax   = round(($subtotal + $fees) * TAX_RATE, 2);
    $total = round($subtotal + $fees + $tax, 2);

    return compact('subtotal', 'fees', 'tax', 'total');
}

function getCartItemCount(): int
{
    return array_sum(array_column(getCart(), 'quantity'));
}

function amountInCents(float $amount): int
{
    return (int) round($amount * 100);
}

function isValidEmail(string $email): bool
{
    return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}

function logError(string $message, array $context = []): void
{
    Logger::error($message, $context);
}

function setting(string $key, string $default = ''): string
{
    static $cache = [];
    if (empty($cache)) {
        try {
            $db   = Database::getInstance();
            $rows = $db->query("SELECT setting_key, setting_value FROM settings")->fetchAll();
            foreach ($rows as $row) {
                $cache[$row['setting_key']] = $row['setting_value'];
            }
        } catch (\Throwable $e) {
            // DB not ready yet
        }
    }
    return $cache[$key] ?? $default;
}

function flashMessage(string $type, string $message): void
{
    $_SESSION['flash'] = ['type' => $type, 'message' => $message];
}

function getFlash(): ?array
{
    if (isset($_SESSION['flash'])) {
        $flash = $_SESSION['flash'];
        unset($_SESSION['flash']);
        return $flash;
    }
    return null;
}

function renderFlash(): string
{
    $flash = getFlash();
    if (!$flash) return '';
    return htmxAlert($flash['type'], $flash['message']);
}

function paginationLinks(int $total, int $perPage, int $current, string $baseUrl): string
{
    $pages = (int) ceil($total / $perPage);
    if ($pages <= 1) return '';

    $html = '<nav><ul class="pagination pagination-sm">';
    for ($i = 1; $i <= $pages; $i++) {
        $active = $i === $current ? ' active' : '';
        $sep    = strpos($baseUrl, '?') !== false ? '&' : '?';
        $html  .= "<li class=\"page-item{$active}\"><a class=\"page-link\" href=\"{$baseUrl}{$sep}page={$i}\">{$i}</a></li>";
    }
    $html .= '</ul></nav>';
    return $html;
}
