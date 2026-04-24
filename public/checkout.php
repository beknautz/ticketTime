<?php
require_once dirname(__DIR__) . '/config/config.php';
require_once BASE_PATH . '/includes/auth.php';

$cart   = getCart();
$totals = getCartTotal();

if (empty($cart)) {
    flashMessage('warning', 'Your cart is empty.');
    redirect(SITE_URL . '/public/events.php');
}

// Get event info from first cart item
$firstItem  = reset($cart);
$eventModel = new Event();
$event      = $eventModel->getById((int)$firstItem['event_id']);

$pageTitle = 'Checkout';
$extraHead = '<meta name="csrf-token" content="' . e(csrfToken()) . '">';
require_once BASE_PATH . '/includes/header.php';
require_once BASE_PATH . '/includes/nav.php';
?>
<main class="container py-4">
  <div class="row justify-content-center">
    <div class="col-lg-10">

      <!-- Steps -->
      <div class="checkout-steps mb-4">
        <div class="checkout-step done"><i class="bi bi-cart3 me-1"></i> Cart</div>
        <div class="checkout-step active"><i class="bi bi-person me-1"></i> Details</div>
        <div class="checkout-step"><i class="bi bi-credit-card me-1"></i> Payment</div>
        <div class="checkout-step"><i class="bi bi-check2-circle me-1"></i> Confirm</div>
      </div>

      <div class="row g-4">
        <!-- Checkout Form -->
        <div class="col-md-7">
          <div class="card shadow-sm">
            <div class="card-header fw-bold bg-white">
              <h5 class="mb-0"><i class="bi bi-person-lines-fill me-2"></i>Your Information</h5>
            </div>
            <div class="card-body">
              <div id="checkoutResponse" class="mb-3"></div>
              <div id="paymentSection" style="display:none">
                <!-- Stripe Payment Element injected here -->
              </div>

              <form id="checkoutForm">
                <?= csrfField() ?>
                <div class="row g-3">
                  <div class="col-sm-6">
                    <label class="form-label fw-semibold">First Name <span class="text-danger">*</span></label>
                    <input type="text" name="first_name" class="form-control form-control-lg"
                           required autocomplete="given-name" placeholder="Jane">
                  </div>
                  <div class="col-sm-6">
                    <label class="form-label fw-semibold">Last Name <span class="text-danger">*</span></label>
                    <input type="text" name="last_name" class="form-control form-control-lg"
                           required autocomplete="family-name" placeholder="Smith">
                  </div>
                  <div class="col-12">
                    <label class="form-label fw-semibold">Email Address <span class="text-danger">*</span></label>
                    <input type="email" name="email" class="form-control form-control-lg"
                           required autocomplete="email" placeholder="jane@example.com">
                    <div class="form-text">Your tickets will be emailed here.</div>
                  </div>
                  <div class="col-12">
                    <label class="form-label fw-semibold">Phone Number</label>
                    <input type="tel" name="phone" class="form-control form-control-lg"
                           autocomplete="tel" placeholder="(555) 555-5555">
                  </div>
                </div>

                <hr class="my-4">

                <!-- Payment Element container (Stripe) -->
                <div id="stripe-payment-element" class="mb-3"></div>
                <div id="paymentError" class="alert alert-danger" style="display:none"></div>

                <button type="submit" id="payBtn"
                        class="btn btn-success btn-lg w-100 fw-bold py-3"
                        data-original='<i class="bi bi-lock-fill me-2"></i>Pay <?= formatMoney($totals['total']) ?>'>
                  <i class="bi bi-lock-fill me-2"></i>Pay <?= formatMoney($totals['total']) ?>
                </button>
                <p class="text-muted text-center small mt-2">
                  <i class="bi bi-shield-check me-1"></i>Your payment is secured by Stripe
                </p>
              </form>
            </div>
          </div>
        </div>

        <!-- Order Summary Sidebar -->
        <div class="col-md-5">
          <div class="card shadow-sm">
            <div class="card-header fw-bold bg-white">
              <h5 class="mb-0"><i class="bi bi-receipt me-2"></i>Order Summary</h5>
            </div>
            <div class="card-body">
              <?php if ($event): ?>
                <div class="mb-3 pb-3 border-bottom">
                  <div class="fw-bold"><?= e($event['event_name']) ?></div>
                  <small class="text-muted">
                    <i class="bi bi-calendar3 me-1"></i><?= formatDate($event['event_start'], 'M j, Y g:i A') ?>
                    <br><i class="bi bi-geo-alt me-1"></i><?= e($event['event_location'] ?? '') ?>
                  </small>
                </div>
              <?php endif; ?>

              <?php foreach ($cart as $item): ?>
                <div class="d-flex justify-content-between mb-2">
                  <div>
                    <span class="fw-semibold small"><?= e($item['ticket_name']) ?></span>
                    <span class="text-muted small"> ×<?= (int)$item['quantity'] ?></span>
                  </div>
                  <span class="small"><?= formatMoney(((float)$item['price'] + (float)$item['service_fee']) * $item['quantity']) ?></span>
                </div>
              <?php endforeach; ?>

              <hr>
              <div class="d-flex justify-content-between small text-muted mb-1">
                <span>Subtotal</span><span><?= formatMoney($totals['subtotal']) ?></span>
              </div>
              <?php if ($totals['fees'] > 0): ?>
              <div class="d-flex justify-content-between small text-muted mb-1">
                <span>Fees</span><span><?= formatMoney($totals['fees']) ?></span>
              </div>
              <?php endif; ?>
              <?php if ($totals['tax'] > 0): ?>
              <div class="d-flex justify-content-between small text-muted mb-1">
                <span>Tax</span><span><?= formatMoney($totals['tax']) ?></span>
              </div>
              <?php endif; ?>
              <div class="d-flex justify-content-between fw-bold fs-5 mt-2">
                <span>Total</span><span class="text-success"><?= formatMoney($totals['total']) ?></span>
              </div>
            </div>
          </div>
        </div>
      </div>

    </div>
  </div>
</main>

<?php
$extraScript = '<script src="https://js.stripe.com/v3/"></script>';
$stripeKey   = STRIPE_PUBLISHABLE_KEY;
$createOrderUrl = SITE_URL . '/actions/checkout-create-order.php';
$confirmUrl     = SITE_URL . '/actions/checkout-confirm.php';
$successUrl     = SITE_URL . '/public/payment-success.php';
?>
<script>
(function() {
  let stripe, elements, paymentElement;
  let orderCreated = false;
  let orderId = null;
  let clientSecret = null;

  const form    = document.getElementById('checkoutForm');
  const payBtn  = document.getElementById('payBtn');
  const errDiv  = document.getElementById('paymentError');

  // Step 1: On form submit, create order and PaymentIntent, then mount Stripe
  form.addEventListener('submit', async function(e) {
    e.preventDefault();

    if (orderCreated) {
      // Step 2: confirm payment if order already created
      await confirmPayment();
      return;
    }

    PaymentUI.disableSubmit();
    errDiv.style.display = 'none';

    const formData = new FormData(form);

    try {
      const resp = await fetch('<?= $createOrderUrl ?>', {
        method: 'POST',
        body: formData,
        headers: { 'X-CSRF-Token': document.querySelector('meta[name="csrf-token"]').content }
      });
      const data = await resp.json();

      if (!data.success) {
        showError(data.error || 'Could not create order. Please try again.');
        return;
      }

      orderId      = data.order_id;
      clientSecret = data.client_secret;

      // Mount Stripe Payment Element
      stripe   = Stripe('<?= $stripeKey ?>');
      elements = stripe.elements({ clientSecret });
      paymentElement = elements.create('payment');
      paymentElement.mount('#stripe-payment-element');

      orderCreated = true;
      payBtn.innerHTML = '<i class="bi bi-lock-fill me-2"></i>Complete Payment';
      payBtn.disabled = false;

    } catch (err) {
      showError('Network error. Please try again.');
    }
  });

  async function confirmPayment() {
    PaymentUI.disableSubmit();

    const { error } = await stripe.confirmPayment({
      elements,
      confirmParams: {
        return_url: '<?= $successUrl ?>?order_id=' + orderId,
      },
      redirect: 'if_required'
    });

    if (error) {
      showError(error.message);
    } else {
      // Payment succeeded without redirect (e.g. 3D Secure handled inline)
      window.location.href = '<?= $successUrl ?>?order_id=' + orderId;
    }
  }

  function showError(msg) {
    errDiv.textContent = msg;
    errDiv.style.display = 'block';
    PaymentUI.enableSubmit();
    errDiv.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
  }
})();
</script>

<?php require_once BASE_PATH . '/includes/footer.php'; ?>
