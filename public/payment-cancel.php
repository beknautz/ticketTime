<?php
require_once dirname(__DIR__) . '/config/config.php';
require_once BASE_PATH . '/includes/auth.php';

$pageTitle = 'Payment Cancelled';
require_once BASE_PATH . '/includes/header.php';
require_once BASE_PATH . '/includes/nav.php';
?>
<main class="container py-5 text-center">
  <div class="display-1 text-warning"><i class="bi bi-x-circle"></i></div>
  <h1 class="fw-bold mt-3">Payment Cancelled</h1>
  <p class="lead text-muted">Your payment was not completed. No charge has been made.</p>
  <div class="mt-4 d-flex gap-3 justify-content-center">
    <a href="<?= SITE_URL ?>/public/cart.php" class="btn btn-primary btn-lg">
      <i class="bi bi-arrow-left me-1"></i> Return to Cart
    </a>
    <a href="<?= SITE_URL ?>/public/events.php" class="btn btn-outline-secondary btn-lg">
      Browse Events
    </a>
  </div>
</main>
<?php require_once BASE_PATH . '/includes/footer.php'; ?>
