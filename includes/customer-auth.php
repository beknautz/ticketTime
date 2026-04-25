<?php
declare(strict_types=1);

function isCustomerLoggedIn(): bool
{
    return !empty($_SESSION['customer_id']);
}

function currentCustomerId(): int
{
    return (int)($_SESSION['customer_id'] ?? 0);
}

function currentCustomer(): ?array
{
    if (!isCustomerLoggedIn()) return null;
    static $cache = null;
    if ($cache === null) {
        $cache = (new Customer())->getById(currentCustomerId());
    }
    return $cache;
}

function customerLogin(array $customer): void
{
    session_regenerate_id(true);
    $_SESSION['customer_id']    = $customer['customer_id'];
    $_SESSION['customer_email'] = $customer['email'];
    $_SESSION['customer_name']  = $customer['first_name'] . ' ' . $customer['last_name'];
}

function customerLogout(): void
{
    unset($_SESSION['customer_id'], $_SESSION['customer_email'], $_SESSION['customer_name']);
}

function requireCustomerLogin(): void
{
    if (!isCustomerLoggedIn()) {
        $redirect = urlencode($_SERVER['REQUEST_URI']);
        redirect(SITE_URL . '/public/customer/login.php?redirect=' . $redirect);
    }
}
