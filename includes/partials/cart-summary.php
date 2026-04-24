<?php
// Partial: cart summary panel (used inline and via HTMX)
$cart   = getCart();
$totals = getCartTotal();
?>
<?php if (empty($cart)): ?>
  <p class="text-muted text-center my-3"><i class="bi bi-cart-x fs-3 d-block mb-2"></i>Your cart is empty</p>
<?php else: ?>
  <ul class="list-unstyled mb-3">
    <?php foreach ($cart as $key => $item): ?>
      <li class="d-flex justify-content-between align-items-start mb-2 pb-2 border-bottom">
        <div class="me-2">
          <div class="fw-semibold small"><?= e($item['ticket_name']) ?></div>
          <div class="text-muted small">×<?= (int)$item['quantity'] ?> @ <?= formatMoney((float)$item['price']) ?></div>
        </div>
        <div class="d-flex align-items-center gap-2">
          <span class="fw-bold"><?= formatMoney((float)$item['price'] * $item['quantity']) ?></span>
          <button class="btn btn-sm btn-outline-danger p-0 px-1"
                  hx-post="<?= SITE_URL ?>/actions/cart-remove.php"
                  hx-vals='{"cart_key": "<?= e($key) ?>"}'
                  hx-target="#cartSummary"
                  hx-swap="innerHTML"
                  title="Remove">
            <i class="bi bi-x"></i>
          </button>
        </div>
      </li>
    <?php endforeach; ?>
  </ul>

  <div class="border-top pt-2">
    <div class="d-flex justify-content-between small text-muted">
      <span>Subtotal</span><span><?= formatMoney($totals['subtotal']) ?></span>
    </div>
    <?php if ($totals['fees'] > 0): ?>
    <div class="d-flex justify-content-between small text-muted">
      <span>Fees</span><span><?= formatMoney($totals['fees']) ?></span>
    </div>
    <?php endif; ?>
    <?php if ($totals['tax'] > 0): ?>
    <div class="d-flex justify-content-between small text-muted">
      <span>Tax</span><span><?= formatMoney($totals['tax']) ?></span>
    </div>
    <?php endif; ?>
    <div class="d-flex justify-content-between fw-bold mt-1">
      <span>Total</span><span class="text-primary"><?= formatMoney($totals['total']) ?></span>
    </div>
  </div>

  <a href="<?= SITE_URL ?>/public/cart.php" class="btn btn-primary w-100 mt-3">
    <i class="bi bi-cart-check me-1"></i> Checkout
  </a>
<?php endif; ?>
