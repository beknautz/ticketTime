<?php
require_once dirname(dirname(__DIR__)) . '/config/config.php';

if (isCustomerLoggedIn()) {
    redirect(SITE_URL . '/public/customer/dashboard.php');
}

$errors  = [];
$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();

    $firstName = trim($_POST['first_name'] ?? '');
    $lastName  = trim($_POST['last_name'] ?? '');
    $email     = strtolower(trim($_POST['email'] ?? ''));
    $phone     = trim($_POST['phone'] ?? '');
    $password  = $_POST['password'] ?? '';
    $confirm   = $_POST['confirm_password'] ?? '';

    if (!$firstName)            $errors[] = 'First name is required.';
    if (!$lastName)             $errors[] = 'Last name is required.';
    if (!isValidEmail($email))  $errors[] = 'A valid email address is required.';
    if (strlen($password) < 8)  $errors[] = 'Password must be at least 8 characters.';
    if ($password !== $confirm) $errors[] = 'Passwords do not match.';

    if (empty($errors)) {
        $customerModel = new Customer();
        if ($customerModel->emailExists($email)) {
            $errors[] = 'An account with that email already exists. <a href="' . SITE_URL . '/public/customer/login.php">Sign in instead?</a>';
        } else {
            $customerId = $customerModel->register(compact('email', 'password', 'first_name', 'last_name', 'phone') + [
                'first_name' => $firstName,
                'last_name'  => $lastName,
            ]);
            $customer = $customerModel->getById($customerId);
            $customerModel->linkOrdersByEmail($customerId, $email);
            customerLogin($customer);
            flashMessage('success', 'Welcome, ' . e($firstName) . '! Your account has been created.');
            $redirect = filter_var($_GET['redirect'] ?? '', FILTER_SANITIZE_URL);
            redirect((strpos($redirect, SITE_URL) === 0 ? $redirect : '') ?: SITE_URL . '/public/customer/dashboard.php');
        }
    }
}

$pageTitle = 'Create Account';
require_once BASE_PATH . '/includes/header.php';
require_once BASE_PATH . '/includes/nav.php';
?>
<main class="container py-5">
  <div class="row justify-content-center">
    <div class="col-sm-9 col-md-7 col-lg-5">
      <div class="text-center mb-4">
        <i class="bi bi-person-plus-fill display-4 text-primary"></i>
        <h2 class="fw-bold mt-2">Create Account</h2>
        <p class="text-muted">Save your tickets and purchase history.</p>
      </div>

      <?php if ($errors): ?>
        <div class="alert alert-danger">
          <ul class="mb-0">
            <?php foreach ($errors as $e): ?>
              <li><?= $e ?></li>
            <?php endforeach; ?>
          </ul>
        </div>
      <?php endif; ?>

      <div class="card shadow-sm">
        <div class="card-body p-4">
          <form method="post">
            <?= csrfField() ?>
            <div class="row g-3">
              <div class="col-6">
                <label class="form-label fw-semibold">First Name <span class="text-danger">*</span></label>
                <input type="text" name="first_name" class="form-control"
                       value="<?= e($_POST['first_name'] ?? '') ?>" required autofocus autocomplete="given-name">
              </div>
              <div class="col-6">
                <label class="form-label fw-semibold">Last Name <span class="text-danger">*</span></label>
                <input type="text" name="last_name" class="form-control"
                       value="<?= e($_POST['last_name'] ?? '') ?>" required autocomplete="family-name">
              </div>
              <div class="col-12">
                <label class="form-label fw-semibold">Email Address <span class="text-danger">*</span></label>
                <input type="email" name="email" class="form-control"
                       value="<?= e($_POST['email'] ?? '') ?>" required autocomplete="email">
              </div>
              <div class="col-12">
                <label class="form-label fw-semibold">Phone Number</label>
                <input type="tel" name="phone" class="form-control"
                       value="<?= e($_POST['phone'] ?? '') ?>" autocomplete="tel">
              </div>
              <div class="col-12">
                <label class="form-label fw-semibold">Password <span class="text-danger">*</span></label>
                <input type="password" name="password" class="form-control"
                       required minlength="8" autocomplete="new-password">
                <div class="form-text">Minimum 8 characters.</div>
              </div>
              <div class="col-12">
                <label class="form-label fw-semibold">Confirm Password <span class="text-danger">*</span></label>
                <input type="password" name="confirm_password" class="form-control"
                       required autocomplete="new-password">
              </div>
              <div class="col-12">
                <button type="submit" class="btn btn-primary btn-lg w-100 fw-bold">
                  <i class="bi bi-person-check me-1"></i>Create Account
                </button>
              </div>
            </div>
          </form>
        </div>
      </div>

      <p class="text-center mt-3 text-muted">
        Already have an account?
        <a href="<?= SITE_URL ?>/public/customer/login.php">Sign in</a>
      </p>
    </div>
  </div>
</main>
<?php require_once BASE_PATH . '/includes/footer.php'; ?>
