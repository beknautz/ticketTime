<?php
$cartCount = getCartItemCount();
?>
<nav class="navbar navbar-expand-lg navbar-dark bg-primary sticky-top shadow-sm">
  <div class="container">
    <a class="navbar-brand fw-bold" href="<?= SITE_URL ?>/public/index.php">
      <i class="bi bi-ticket-perforated-fill me-1"></i><?= SITE_NAME ?>
    </a>
    <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#mainNav">
      <span class="navbar-toggler-icon"></span>
    </button>
    <div class="collapse navbar-collapse" id="mainNav">
      <ul class="navbar-nav me-auto">
        <li class="nav-item">
          <a class="nav-link" href="<?= SITE_URL ?>/public/events.php">Events</a>
        </li>
      </ul>
      <ul class="navbar-nav">
        <li class="nav-item">
          <a class="nav-link position-relative" href="<?= SITE_URL ?>/public/cart.php">
            <i class="bi bi-cart3"></i> Cart
            <?php if ($cartCount > 0): ?>
              <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger">
                <?= $cartCount ?>
              </span>
            <?php endif; ?>
          </a>
        </li>
        <?php if (isAdminLoggedIn()): ?>
        <li class="nav-item">
          <a class="nav-link" href="<?= SITE_URL ?>/admin/index.php">
            <i class="bi bi-speedometer2"></i> Admin
          </a>
        </li>
        <?php endif; ?>
      </ul>
    </div>
  </div>
</nav>
