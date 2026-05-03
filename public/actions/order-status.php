<?php
require_once dirname(__DIR__, 2) . '/config/config.php';

$publicOrderId = trim($_GET['order_id'] ?? '');
if (!$publicOrderId) {
    echo '<div class="alert alert-danger">Missing order ID</div>';
    exit;
}

$orderModel = new Order();
$order      = $orderModel->getByPublicId($publicOrderId);

if (!$order) {
    echo '<div class="alert alert-danger">Order not found</div>';
    exit;
}

if ($order['status'] === 'paid') {
    // Payment confirmed — redirect to success page
    header('HX-Redirect: ' . SITE_URL . '/public/payment-success.php?order_id=' . urlencode($publicOrderId));
    echo '<div class="text-center"><div class="display-1 text-success"><i class="bi bi-check-circle-fill"></i></div><h2 class="fw-bold">Payment Confirmed!</h2><p class="text-muted">Redirecting...</p></div>';
    exit;
}

if ($order['status'] === 'failed') {
    echo '<div class="text-center">
      <div class="display-1 text-danger"><i class="bi bi-x-circle-fill"></i></div>
      <h2 class="fw-bold">Payment Failed</h2>
      <p class="text-muted">Your payment could not be processed.</p>
      <a href="' . SITE_URL . '/public/checkout.php" class="btn btn-primary">Try Again</a>
    </div>';
    exit;
}

// Still pending — re-render polling div
echo '<div id="paymentStatus"
     hx-get="' . SITE_URL . '/actions/order-status.php?order_id=' . urlencode($publicOrderId) . '"
     hx-trigger="every 3s"
     hx-swap="outerHTML"
     hx-target="#paymentStatus"
     class="text-center">
  <div class="spinner-border text-primary mb-3" style="width:3rem;height:3rem"></div>
  <h2 class="fw-bold">Processing Payment...</h2>
  <p class="text-muted">Please wait while we confirm your payment.</p>
</div>';
