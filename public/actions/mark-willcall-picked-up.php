<?php
require_once dirname(__DIR__) . '/config/config.php';
require_once BASE_PATH . '/includes/auth.php';

requireAdmin(['admin', 'box_office', 'scanner']);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo htmxAlert('danger', 'Invalid request.');
    exit;
}

$orderId = (int)($_POST['order_id'] ?? 0);
if (!$orderId) {
    echo htmxAlert('danger', 'Missing order ID.');
    exit;
}

$orderModel = new Order();
$order      = $orderModel->getById($orderId);

if (!$order || $order['status'] !== 'paid') {
    echo htmxAlert('danger', 'Order not found or not paid.');
    exit;
}

$pickedUpBy = currentAdminName();
$result     = $orderModel->markWillCallPickedUp($orderId, $pickedUpBy);

if (!$result) {
    echo htmxAlert('warning', 'Could not update will-call status.');
    exit;
}

// Re-fetch and re-render the order card
$order   = $orderModel->getById($orderId);
$items   = $orderModel->getItems($orderId);
$tickets = $orderModel->getTickets($orderId);
$event   = (new Event())->getById((int)$order['event_id']);
?>
<div class="card shadow-sm mb-3 border-success" id="wc-order-<?= $orderId ?>">
  <div class="card-header bg-success text-white d-flex justify-content-between align-items-center">
    <div>
      <i class="bi bi-check2-all me-1"></i>
      <span class="fw-bold"><?= e($order['public_order_id']) ?></span>
      <span class="badge bg-light text-dark ms-2">Picked Up</span>
    </div>
    <small><?= formatDate($order['picked_up_at'], 'M j, Y g:i A') ?></small>
  </div>
  <div class="card-body">
    <p class="mb-0">
      <strong><?= e($order['customer_first_name'] . ' ' . $order['customer_last_name']) ?></strong>
      — tickets handed over by <?= e($order['picked_up_by']) ?> at <?= formatDate($order['picked_up_at'], 'g:i A') ?>
    </p>
    <div class="mt-3 d-flex gap-2">
      <button class="btn btn-outline-secondary btn-sm"
              hx-post="<?= SITE_URL ?>/actions/resend-tickets.php"
              hx-vals='{"public_order_id":"<?= e($order['public_order_id']) ?>"}'
              hx-target="#resend-done-<?= $orderId ?>"
              hx-swap="innerHTML">
        <i class="bi bi-envelope me-1"></i>Resend Tickets
      </button>
    </div>
    <div id="resend-done-<?= $orderId ?>" class="mt-2"></div>
  </div>
</div>
