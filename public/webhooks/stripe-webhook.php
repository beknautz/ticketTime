<?php
declare(strict_types=1);

// Raw body must be read before any framework bootstrapping
$payload   = @file_get_contents('php://input');
$sigHeader = $_SERVER['HTTP_STRIPE_SIGNATURE'] ?? '';

require_once dirname(__DIR__, 2) . '/config/config.php';
require_once BASE_PATH . '/includes/order-fulfillment.php';

// Log all webhook attempts
Logger::info('Stripe webhook received', ['sig' => substr($sigHeader, 0, 30)]);

if (empty($payload)) {
    http_response_code(400);
    echo 'Empty payload';
    exit;
}

if (empty($sigHeader)) {
    http_response_code(400);
    echo 'Missing signature';
    exit;
}

try {
    $payment = new Payment();
    $event   = $payment->verifyWebhookSignature($payload, $sigHeader);
} catch (\Throwable $e) {
    Logger::error('Webhook signature verification failed', ['error' => $e->getMessage()]);
    http_response_code(400);
    echo 'Signature verification failed: ' . $e->getMessage();
    exit;
}

$eventType = $event['type'] ?? '';
$eventId   = $event['id'] ?? '';

Logger::info('Webhook event', ['type' => $eventType, 'id' => $eventId]);

// ── payment_intent.succeeded ───────────────────────────────────────────────
if ($eventType === 'payment_intent.succeeded') {
    $pi = $event['data']['object'];
    handlePaymentIntentSucceeded($pi);
}

// ── payment_intent.payment_failed ─────────────────────────────────────────
elseif ($eventType === 'payment_intent.payment_failed') {
    $pi = $event['data']['object'];
    handlePaymentIntentFailed($pi);
}

// ── charge.refunded ────────────────────────────────────────────────────────
elseif ($eventType === 'charge.refunded') {
    $charge = $event['data']['object'];
    handleChargeRefunded($charge);
}

http_response_code(200);
echo 'OK';

// ────────────────────────────────────────────────────────────────────────────
function handlePaymentIntentSucceeded(array $pi): void
{
    $piId   = $pi['id'];
    $amount = (int)$pi['amount'];

    $orderModel = new Order();
    $order      = $orderModel->getByPaymentIntent($piId);

    if (!$order) {
        Logger::error('Webhook: order not found for payment intent', ['pi' => $piId]);
        return;
    }

    // Idempotency: already processed
    if ($order['status'] === 'paid') {
        Logger::info('Webhook: order already paid', ['order' => $order['public_order_id']]);
        return;
    }

    // Verify amount (Stripe amount is in cents)
    $expectedCents = amountInCents((float)$order['total']);
    if ($amount !== $expectedCents) {
        Logger::error('Webhook: amount mismatch', [
            'pi'       => $piId,
            'expected' => $expectedCents,
            'received' => $amount,
        ]);
        return;
    }

    // Verify currency
    if (strtoupper($pi['currency']) !== strtoupper(CURRENCY)) {
        Logger::error('Webhook: currency mismatch', ['pi' => $piId, 'currency' => $pi['currency']]);
        return;
    }

    fulfillPaidOrder($order, $pi['status'] ?? 'succeeded');
}

function handlePaymentIntentFailed(array $pi): void
{
    $piId       = $pi['id'];
    $orderModel = new Order();
    $order      = $orderModel->getByPaymentIntent($piId);

    if (!$order) {
        Logger::error('Webhook: order not found for failed pi', ['pi' => $piId]);
        return;
    }

    if ($order['status'] !== 'pending') return; // Already handled

    $orderModel->markFailed((int)$order['order_id']);

    // Release reserved quantities
    $ttModel = new TicketType();
    $items   = $orderModel->getItems((int)$order['order_id']);
    foreach ($items as $item) {
        $ttModel->releaseQuantity((int)$item['ticket_type_id'], (int)$item['quantity']);
    }

    Logger::info('Order marked failed', ['order' => $order['public_order_id'], 'pi' => $piId]);
}

function handleChargeRefunded(array $charge): void
{
    $piId = $charge['payment_intent'] ?? null;
    if (!$piId) return;

    $orderModel = new Order();
    $order      = $orderModel->getByPaymentIntent($piId);

    if (!$order) {
        Logger::info('Webhook: refund for unknown order', ['pi' => $piId]);
        return;
    }

    $db = Database::getInstance();

    $refundedAmount = (int)$charge['amount_refunded'];
    $totalAmount    = (int)$charge['amount'];

    if ($refundedAmount >= $totalAmount) {
        // Full refund
        $db->prepare("UPDATE orders SET status = 'refunded' WHERE order_id = ?")->execute([$order['order_id']]);
        $db->prepare("UPDATE tickets SET status = 'refunded' WHERE order_id = ?")->execute([$order['order_id']]);
        Logger::info('Order fully refunded', ['order' => $order['public_order_id']]);
    } else {
        // Partial refund
        $db->prepare("UPDATE orders SET status = 'partially_refunded' WHERE order_id = ?")->execute([$order['order_id']]);
        Logger::info('Order partially refunded', ['order' => $order['public_order_id'], 'amount' => $refundedAmount]);
    }
}
