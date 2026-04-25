<?php
$cartCount = getCartItemCount();
$customer  = currentCustomer();
?>
<nav class="navbar navbar-expand-lg navbar-dark sticky-top" style="background:#0a0c10;border-bottom:1px solid #2a3148">
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
      <ul class="navbar-nav align-items-center gap-1">
        <!-- Cart -->
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

        <!-- Customer account -->
        <?php if ($customer): ?>
          <li class="nav-item dropdown">
            <a class="nav-link dropdown-toggle" href="#" data-bs-toggle="dropdown">
              <i class="bi bi-person-circle me-1"></i><?= e($customer['first_name']) ?>
            </a>
            <ul class="dropdown-menu dropdown-menu-end">
              <li><h6 class="dropdown-header"><?= e($customer['first_name'] . ' ' . $customer['last_name']) ?></h6></li>
              <li><a class="dropdown-item" href="<?= SITE_URL ?>/public/customer/dashboard.php">
                <i class="bi bi-ticket-perforated me-2"></i>My Tickets
              </a></li>
              <li><a class="dropdown-item" href="<?= SITE_URL ?>/public/customer/profile.php">
                <i class="bi bi-person-gear me-2"></i>My Profile
              </a></li>
              <li><hr class="dropdown-divider"></li>
              <li><a class="dropdown-item" href="<?= SITE_URL ?>/public/customer/logout.php">
                <i class="bi bi-box-arrow-right me-2"></i>Sign Out
              </a></li>
            </ul>
          </li>
        <?php else: ?>
          <li class="nav-item">
            <a class="nav-link" href="<?= SITE_URL ?>/public/customer/login.php">
              <i class="bi bi-person me-1"></i>Sign In
            </a>
          </li>
          <li class="nav-item">
            <a class="nav-link btn btn-outline-light btn-sm px-3 ms-1"
               href="<?= SITE_URL ?>/public/customer/register.php">
              Register
            </a>
          </li>
        <?php endif; ?>

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
