<?php
require_once dirname(__DIR__, 2) . '/config/config.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false]);
    exit;
}

$publicOrderId = trim($_POST['order_id'] ?? '');
if (!$publicOrderId) {
    echo json_encode(['success' => false, 'error' => 'Missing order ID']);
    exit;
}

$orderModel = new Order();
$order      = $orderModel->getByPublicId($publicOrderId);

if (!$order) {
    echo json_encode(['success' => false, 'error' => 'Order not found']);
    exit;
}

echo json_encode([
    'success' => true,
    'status'  => $order['status'],
    'paid'    => $order['status'] === 'paid',
]);
