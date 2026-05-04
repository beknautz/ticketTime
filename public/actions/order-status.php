<?php
require_once dirname(__DIR__, 2) . '/config/config.php';
require_once BASE_PATH . '/includes/order-fulfillment.php';

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

// ── Stripe fallback: if still pending after 45s, check Stripe directly ────────
if ($order['status'] === 'pending' && !empty($order['payment_intent_id'])) {
    $createdAt = strtotime($order['created_at']);
    if ($createdAt && (time() - $createdAt) > 45) {
        try {
            $payment = new Payment();
            $pi      = $payment->retrievePaymentIntent($order['payment_intent_id']);
            if (($pi['status'] ?? '') === 'succeeded') {
                fulfillPaidOrder($order, 'succeeded');
                $order = $orderModel->getByPublicId($publicOrderId); // re-fetch
            }
        } catch (\Throwable $e) {
            Logger::error('order-status Stripe fallback failed', ['error' => $e->getMessage()]);
        }
    }
}

// ── Respond based on current status ──────────────────────────────────────────
if ($order['status'] === 'paid') {
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

// Still pending — keep polling
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
