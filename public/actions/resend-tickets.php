<?php
require_once dirname(__DIR__, 2) . '/config/config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo htmxAlert('danger', 'Invalid request.');
    exit;
}

$publicOrderId = trim($_POST['public_order_id'] ?? '');

if (!$publicOrderId) {
    echo htmxAlert('danger', 'Missing order ID.');
    exit;
}

$orderModel = new Order();
$order      = $orderModel->getByPublicId($publicOrderId);

if (!$order || $order['status'] !== 'paid') {
    echo htmxAlert('danger', 'Order not found or not yet paid.');
    exit;
}

$items   = $orderModel->getItems((int)$order['order_id']);
$tickets = $orderModel->getTickets((int)$order['order_id']);

if (empty($tickets)) {
    echo htmxAlert('warning', 'No tickets found for this order.');
    exit;
}

$mailer = new Mailer();
$sent   = $mailer->resendTickets($order, $items, $tickets);

if ($sent) {
    echo htmxAlert('success', 'Tickets resent to ' . e($order['customer_email']));
    Logger::info('Tickets resent', ['order' => $publicOrderId, 'email' => $order['customer_email']]);
} else {
    echo htmxAlert('warning', 'Email could not be sent. Please check mail configuration.');
}
