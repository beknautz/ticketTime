<?php
require_once dirname(dirname(__DIR__)) . '/config/config.php';

if (isCustomerLoggedIn()) {
    redirect(SITE_URL . '/public/customer/dashboard.php');
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();

    $email    = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if (!$email || !$password) {
        $error = 'Please enter your email and password.';
    } else {
        $customerModel = new Customer();
        $customer      = $customerModel->verifyPassword($email, $password);

        if ($customer) {
            customerLogin($customer);
            $customerModel->linkOrdersByEmail($customer['customer_id'], $customer['email']);
            $redirect = filter_var($_POST['redirect'] ?? $_GET['redirect'] ?? '', FILTER_SANITIZE_URL);
            redirect((strpos($redirect, SITE_URL) === 0 ? $redirect : '') ?: SITE_URL . '/public/customer/dashboard.php');
        } else {
            $error = 'Invalid email or password.';
        }
    }
}

$pageTitle = 'Sign In';
require_once BASE_PATH . '/includes/header.php';
require_once BASE_PATH . '/includes/nav.php';
?>
<main class="container py-5">
  <div class="row justify-content-center">
    <div class="col-sm-8 col-md-6 col-lg-4">
      <div class="text-center mb-4">
        <i class="bi bi-ticket-perforated-fill display-4 text-primary"></i>
        <h2 class="fw-bold mt-2">My Account</h2>
        <p class="text-muted">Sign in to view your tickets and order history.</p>
      </div>

      <?php if ($error): ?>
        <div class="alert alert-danger"><?= e($error) ?></div>
      <?php endif; ?>
      <?= renderFlash() ?>

      <div class="card shadow-sm">
        <div class="card-body p-4">
          <form method="post">
            <?= csrfField() ?>
            <input type="hidden" name="redirect" value="<?= e($_GET['redirect'] ?? '') ?>">
            <div class="mb-3">
              <label class="form-label fw-semibold">Email Address</label>
              <input type="email" name="email" class="form-control form-control-lg"
                     value="<?= e($_POST['email'] ?? '') ?>"
                     required autofocus autocomplete="email">
            </div>
            <div class="mb-4">
              <label class="form-label fw-semibold">Password</label>
              <input type="password" name="password" class="form-control form-control-lg"
                     required autocomplete="current-password">
            </div>
            <button type="submit" class="btn btn-primary btn-lg w-100 fw-bold">
              <i class="bi bi-box-arrow-in-right me-1"></i>Sign In
            </button>
          </form>
        </div>
      </div>

      <p class="text-center mt-3 text-muted">
        Don't have an account?
        <a href="<?= SITE_URL ?>/public/customer/register.php">Create one free</a>
      </p>
      <p class="text-center">
        <a href="<?= SITE_URL ?>/public/customer/forgot-password.php" class="text-muted small">
          Forgot your password?
        </a>
      </p>
    </div>
  </div>
</main>
<?php require_once BASE_PATH . '/includes/footer.php'; ?>
