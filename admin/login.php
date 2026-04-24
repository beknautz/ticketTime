<?php
require_once dirname(__DIR__) . '/config/config.php';
require_once BASE_PATH . '/includes/auth.php';

if (isAdminLoggedIn()) {
    redirect(SITE_URL . '/admin/index.php');
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $email    = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if (adminLogin($email, $password)) {
        $redirect = filter_var($_GET['redirect'] ?? '', FILTER_SANITIZE_URL);
        // Only allow same-site redirects
        $redirect = (strpos($redirect, SITE_URL) === 0) ? $redirect : '';
        redirect($redirect ?: SITE_URL . '/admin/index.php');
    } else {
        $error = 'Invalid email or password.';
    }
}

$pageTitle = 'Admin Login';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Admin Login &mdash; <?= SITE_NAME ?></title>
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
  <link rel="stylesheet" href="<?= SITE_URL ?>/public/assets/css/style.css">
</head>
<body class="bg-light">
<div class="container py-5">
  <div class="row justify-content-center">
    <div class="col-sm-8 col-md-5 col-lg-4">
      <div class="text-center mb-4">
        <h2 class="fw-bold"><i class="bi bi-ticket-perforated-fill text-primary me-1"></i><?= SITE_NAME ?></h2>
        <p class="text-muted">Admin Portal</p>
      </div>
      <div class="card shadow-sm">
        <div class="card-body p-4">
          <?php if ($error): ?>
            <div class="alert alert-danger"><?= e($error) ?></div>
          <?php endif; ?>
          <form method="post">
            <?= csrfField() ?>
            <div class="mb-3">
              <label class="form-label fw-semibold">Email Address</label>
              <input type="email" name="email" class="form-control form-control-lg"
                     required autofocus autocomplete="username">
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
      <div class="text-center mt-3">
        <a href="<?= SITE_URL ?>/public/index.php" class="text-muted small">
          <i class="bi bi-arrow-left me-1"></i>Back to site
        </a>
      </div>
    </div>
  </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
