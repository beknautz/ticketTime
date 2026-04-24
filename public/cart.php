<?php
require_once dirname(__DIR__) . '/config/config.php';
require_once BASE_PATH . '/includes/auth.php';

$cart   = getCart();
$totals = getCartTotal();

$pageTitle = 'Your Cart';
$extraHead = '<meta name="csrf-token" content="' . e(csrfToken()) . '">';
require_once BASE_PATH . '/includes/header.php';
require_once BASE_PATH . '/includes/nav.php';
?>
<main class="container py-4">
  <h1 class="fw-bold mb-4"><i class="bi bi-cart3 me-2"></i>Your Cart</h1>

  <div id="cartResponse" class="mb-3"></div>

  <?php if (empty($cart)): ?>
    <div class="text-center py-5">
      <i class="bi bi-cart-x display-1 text-muted"></i>
      <h3 class="mt-3 text-muted">Your cart is empty</h3>
      <a href="<?= SITE_URL ?>/public/events.php" class="btn btn-primary mt-3">Browse Events</a>
    </div>
  <?php else: ?>
    <div class="row g-4">
      <div class="col-md-8">
        <div class="card shadow-sm" id="cartItems">
          <div class="card-body">
            <table class="table align-middle">
              <thead class="table-light">
                <tr>
                  <th>Ticket</th>
                  <th class="text-center" style="width:120px">Qty</th>
                  <th class="text-end">Price</th>
                  <th class="text-end">Subtotal</th>
                  <th></th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($cart as $key => $item): ?>
                  <tr id="cart-row-<?= e($key) ?>">
                    <td>
                      <div class="fw-semibold"><?= e($item['ticket_name']) ?></div>
                      <small class="text-muted"><?= e($item['event_name'] ?? '') ?></small>
                      <?php if ((float)$item['service_fee'] > 0): ?>
                        <br><small class="text-muted">+ <?= formatMoney((float)$item['service_fee']) ?>/ticket fee</small>
                      <?php endif; ?>
                    </td>
                    <td class="text-center">
                      <div class="input-group input-group-sm" style="width:100px;margin:auto">
                        <button class="btn btn-outline-secondary"
                                hx-post="<?= SITE_URL ?>/actions/cart-remove.php"
                                hx-vals='{"cart_key":"<?= e($key) ?>","action":"decrease"}'
                                hx-target="#cartItems"
                                hx-swap="outerHTML">−</button>
                        <span class="input-group-text border-start-0 border-end-0 bg-white"><?= (int)$item['quantity'] ?></span>
                        <button class="btn btn-outline-secondary"
                                hx-post="<?= SITE_URL ?>/actions/cart-add.php"
                                hx-vals='{"ticket_type_id":"<?= (int)$item['ticket_type_id'] ?>","event_id":"<?= (int)$item['event_id'] ?>","qty_direct":"1"}'
                                hx-target="#cartItems"
                                hx-swap="outerHTML">+</button>
                      </div>
                    </td>
                    <td class="text-end"><?= formatMoney((float)$item['price'] + (float)$item['service_fee']) ?></td>
                    <td class="text-end fw-bold"><?= formatMoney(((float)$item['price'] + (float)$item['service_fee']) * $item['quantity']) ?></td>
                    <td class="text-end">
                      <button class="btn btn-sm btn-outline-danger"
                              hx-post="<?= SITE_URL ?>/actions/cart-remove.php"
                              hx-vals='{"cart_key":"<?= e($key) ?>"}'
                              hx-target="#cartItems"
                              hx-swap="outerHTML"
                              hx-confirm="Remove this item?">
                        <i class="bi bi-trash"></i>
                      </button>
                    </td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        </div>

        <div class="mt-3">
          <a href="<?= SITE_URL ?>/public/events.php" class="btn btn-outline-secondary">
            <i class="bi bi-arrow-left me-1"></i> Continue Shopping
          </a>
        </div>
      </div>

      <div class="col-md-4">
        <div class="card shadow-sm">
          <div class="card-header fw-bold bg-light">Order Summary</div>
          <div class="card-body">
            <div class="d-flex justify-content-between mb-2">
              <span>Subtotal</span><span><?= formatMoney($totals['subtotal']) ?></span>
            </div>
            <?php if ($totals['fees'] > 0): ?>
            <div class="d-flex justify-content-between mb-2 text-muted small">
              <span>Service Fees</span><span><?= formatMoney($totals['fees']) ?></span>
            </div>
            <?php endif; ?>
            <?php if ($totals['tax'] > 0): ?>
            <div class="d-flex justify-content-between mb-2 text-muted small">
              <span>Tax</span><span><?= formatMoney($totals['tax']) ?></span>
            </div>
            <?php endif; ?>
            <hr>
            <div class="d-flex justify-content-between fw-bold fs-5">
              <span>Total</span><span class="text-primary"><?= formatMoney($totals['total']) ?></span>
            </div>
            <a href="<?= SITE_URL ?>/public/checkout.php" class="btn btn-success btn-lg w-100 mt-3 fw-bold">
              <i class="bi bi-lock-fill me-1"></i> Secure Checkout
            </a>
            <p class="text-muted text-center small mt-2">
              <i class="bi bi-shield-check me-1"></i>Secure payment by Stripe
            </p>
          </div>
        </div>
      </div>
    </div>
  <?php endif; ?>
</main>
<?php require_once BASE_PATH . '/includes/footer.php'; ?>
