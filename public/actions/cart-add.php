<?php
require_once dirname(__DIR__, 2) . '/config/config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit;
}

$ticketTypeId = (int)($_POST['ticket_type_id'] ?? 0);
$eventId      = (int)($_POST['event_id'] ?? 0);
$qty          = (int)($_POST['qty'] ?? $_POST['qty_direct'] ?? 1);

// Support for direct qty from input
if (isset($_POST['qty-' . $ticketTypeId])) {
    $qty = (int)$_POST['qty-' . $ticketTypeId];
}

if ($ticketTypeId <= 0 || $eventId <= 0 || $qty <= 0) {
    echo htmxAlert('danger', 'Invalid request.');
    exit;
}

$ttModel = new TicketType();
$tt      = $ttModel->getById($ticketTypeId);

if (!$tt || $tt['status'] !== 'active' || (int)$tt['event_id'] !== $eventId) {
    echo htmxAlert('danger', 'Ticket type not available.');
    exit;
}

$remaining = (int)$tt['quantity_available'] - (int)$tt['quantity_sold'];
if ($remaining <= 0) {
    echo htmxAlert('danger', 'Sorry, this ticket type is sold out.');
    exit;
}

$cart = getCart();
$key  = 'tt_' . $ticketTypeId;

// Check max per order
$currentQty = isset($cart[$key]) ? (int)$cart[$key]['quantity'] : 0;
$addQty     = min($qty, (int)$tt['max_per_order'] - $currentQty, $remaining);

if ($addQty <= 0) {
    echo htmxAlert('warning', 'You\'ve reached the maximum quantity for this ticket type.');
    exit;
}

if (isset($cart[$key])) {
    $cart[$key]['quantity'] += $addQty;
} else {
    $eventModel = new Event();
    $event      = $eventModel->getById($eventId);

    $cart[$key] = [
        'ticket_type_id' => $ticketTypeId,
        'event_id'       => $eventId,
        'ticket_name'    => $tt['ticket_name'],
        'event_name'     => $event['event_name'] ?? '',
        'price'          => (float)$tt['price'],
        'service_fee'    => (float)$tt['service_fee'],
        'quantity'       => $addQty,
        'max_per_order'  => (int)$tt['max_per_order'],
    ];
}

setCart($cart);

// Return updated cart table body if request is from cart page, otherwise just a success message
if (isHtmxRequest()) {
    $cartItems = '<table class="table align-middle"><thead class="table-light"><tr><th>Ticket</th><th class="text-center" style="width:120px">Qty</th><th class="text-end">Price</th><th class="text-end">Subtotal</th><th></th></tr></thead><tbody>';
    foreach (getCart() as $k => $item) {
        $cartItems .= sprintf(
            '<tr id="cart-row-%s"><td><div class="fw-semibold">%s</div><small class="text-muted">%s</small></td>
             <td class="text-center"><div class="input-group input-group-sm" style="width:100px;margin:auto">
               <button class="btn btn-outline-secondary" hx-post="%s/actions/cart-remove.php" hx-vals=\'{"cart_key":"%s","action":"decrease"}\' hx-target="#cartItems" hx-swap="outerHTML">−</button>
               <span class="input-group-text border-start-0 border-end-0 bg-white">%d</span>
               <button class="btn btn-outline-secondary" hx-post="%s/actions/cart-add.php" hx-vals=\'{"ticket_type_id":"%d","event_id":"%d","qty_direct":"1"}\' hx-target="#cartItems" hx-swap="outerHTML">+</button>
             </div></td>
             <td class="text-end">%s</td>
             <td class="text-end fw-bold">%s</td>
             <td class="text-end"><button class="btn btn-sm btn-outline-danger" hx-post="%s/actions/cart-remove.php" hx-vals=\'{"cart_key":"%s"}\' hx-target="#cartItems" hx-swap="outerHTML"><i class="bi bi-trash"></i></button></td></tr>',
            e($k),
            e($item['ticket_name']),
            e($item['event_name'] ?? ''),
            SITE_URL, e($k),
            (int)$item['quantity'],
            SITE_URL, (int)$item['ticket_type_id'], (int)$item['event_id'],
            formatMoney((float)$item['price'] + (float)$item['service_fee']),
            formatMoney(((float)$item['price'] + (float)$item['service_fee']) * $item['quantity']),
            SITE_URL, e($k)
        );
    }
    $cartItems .= '</tbody></table>';

    // Check if request is from cart page (hx-target="cartItems") or event page
    $target = $_SERVER['HTTP_HX_TARGET'] ?? '';
    if ($target === 'cartItems') {
        echo '<div id="cartItems">' . $cartItems . '</div>';
    } else {
        echo htmxAlert('success', 'Added ' . $addQty . ' × ' . e($tt['ticket_name']) . ' to your cart! <a href="' . SITE_URL . '/public/cart.php" class="alert-link">View Cart</a>');
    }
} else {
    redirect(SITE_URL . '/public/cart.php');
}
