<?php
require_once dirname(__DIR__) . '/config/config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit;
}

$cartKey = trim($_POST['cart_key'] ?? '');
$action  = trim($_POST['action'] ?? 'remove'); // remove | decrease

$cart = getCart();

if ($action === 'decrease' && isset($cart[$cartKey])) {
    $cart[$cartKey]['quantity']--;
    if ($cart[$cartKey]['quantity'] <= 0) {
        unset($cart[$cartKey]);
    }
} elseif (isset($cart[$cartKey])) {
    unset($cart[$cartKey]);
}

setCart($cart);

// Re-render cart table
$cartItems  = '<div id="cartItems">';
if (empty($cart)) {
    $cartItems .= '<div class="text-center py-4 text-muted"><i class="bi bi-cart-x display-4 d-block mb-2"></i>Cart is empty. <a href="' . SITE_URL . '/public/events.php">Browse events</a></div>';
} else {
    $cartItems .= '<table class="table align-middle"><thead class="table-light"><tr><th>Ticket</th><th class="text-center" style="width:120px">Qty</th><th class="text-end">Price</th><th class="text-end">Subtotal</th><th></th></tr></thead><tbody>';
    foreach ($cart as $k => $item) {
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
            e($k), e($item['ticket_name']), e($item['event_name'] ?? ''),
            SITE_URL, e($k), (int)$item['quantity'],
            SITE_URL, (int)$item['ticket_type_id'], (int)$item['event_id'],
            formatMoney((float)$item['price'] + (float)$item['service_fee']),
            formatMoney(((float)$item['price'] + (float)$item['service_fee']) * $item['quantity']),
            SITE_URL, e($k)
        );
    }
    $cartItems .= '</tbody></table>';
}
$cartItems .= '</div>';
echo $cartItems;
