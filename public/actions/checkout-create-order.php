<?php
require_once dirname(__DIR__, 2) . '/config/config.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit;
}

verifyCsrf();

$cart   = getCart();
$totals = getCartTotal();

if (empty($cart)) {
    echo json_encode(['success' => false, 'error' => 'Your cart is empty']);
    exit;
}

// Validate input
$firstName = trim($_POST['first_name'] ?? '');
$lastName  = trim($_POST['last_name'] ?? '');
$email     = strtolower(trim($_POST['email'] ?? ''));
$phone     = trim($_POST['phone'] ?? '');

$errors = [];
if (strlen($firstName) < 1) $errors[] = 'First name is required';
if (strlen($lastName) < 1)  $errors[] = 'Last name is required';
if (!isValidEmail($email))  $errors[] = 'Valid email address is required';

if ($errors) {
    echo json_encode(['success' => false, 'error' => implode('. ', $errors)]);
    exit;
}

// Get event_id from cart
$firstItem = reset($cart);
$eventId   = (int)$firstItem['event_id'];

$eventModel = new Event();
$event      = $eventModel->getById($eventId);

if (!$event || $event['status'] !== 'active') {
    echo json_encode(['success' => false, 'error' => 'Event is no longer available']);
    exit;
}

// Validate all ticket types still available
$ttModel = new TicketType();
foreach ($cart as $item) {
    $tt = $ttModel->getById((int)$item['ticket_type_id']);
    if (!$tt || $tt['status'] !== 'active') {
        echo json_encode(['success' => false, 'error' => 'Ticket type "' . $item['ticket_name'] . '" is no longer available']);
        exit;
    }
    $remaining = (int)$tt['quantity_available'] - (int)$tt['quantity_sold'];
    if ($remaining < (int)$item['quantity']) {
        echo json_encode(['success' => false, 'error' => 'Not enough tickets available for "' . $item['ticket_name'] . '"']);
        exit;
    }
}

try {
    $db = Database::getInstance();
    $db->beginTransaction();

    // Reserve quantities (with locking)
    foreach ($cart as $item) {
        if (!$ttModel->reserveQuantity((int)$item['ticket_type_id'], (int)$item['quantity'])) {
            $db->rollBack();
            echo json_encode(['success' => false, 'error' => 'Could not reserve "' . $item['ticket_name'] . '" - another customer may have just purchased the last tickets']);
            exit;
        }
    }

    // Create order
    $orderModel = new Order();
    $orderId    = $orderModel->create([
        'event_id'   => $eventId,
        'first_name' => $firstName,
        'last_name'  => $lastName,
        'email'      => $email,
        'phone'      => $phone,
        'customer_id'=> isCustomerLoggedIn() ? currentCustomerId() : null,
        'subtotal'   => $totals['subtotal'],
        'fee_total'  => $totals['fees'],
        'tax_total'  => $totals['tax'],
        'total'      => $totals['total'],
    ]);

    // Add order items
    foreach ($cart as $item) {
        $lineTotal = ((float)$item['price'] + (float)$item['service_fee']) * $item['quantity'];
        $orderModel->addItem($orderId, [
            'ticket_type_id' => $item['ticket_type_id'],
            'ticket_name'    => $item['ticket_name'],
            'quantity'       => $item['quantity'],
            'unit_price'     => $item['price'],
            'unit_fee'       => $item['service_fee'],
            'line_total'     => $lineTotal,
        ]);
    }

    $db->commit();

    // Create Stripe PaymentIntent
    $payment     = new Payment();
    $amountCents = amountInCents($totals['total']);

    $order = $orderModel->getById($orderId);

    $pi = $payment->createStripePaymentIntent($amountCents, CURRENCY, [
        'order_id'        => $order['public_order_id'],
        'customer_email'  => $email,
        'event_id'        => $eventId,
        'event_name'      => $event['event_name'],
    ]);

    // Store payment intent ID on order
    $orderModel->setPaymentIntent($orderId, $pi['id']);

    echo json_encode([
        'success'        => true,
        'order_id'       => $order['public_order_id'],
        'client_secret'  => $pi['client_secret'],
        'amount'         => $totals['total'],
    ]);

} catch (\Throwable $e) {
    if (isset($db) && $db->inTransaction()) {
        $db->rollBack();
    }
    Logger::error('Order creation failed', ['error' => $e->getMessage()]);
    echo json_encode(['success' => false, 'error' => 'Order creation failed. Please try again.']);
}
