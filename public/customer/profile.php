<?php
require_once dirname(dirname(__DIR__)) . '/config/config.php';
requireCustomerLogin();

$customer      = currentCustomer();
$customerModel = new Customer();
$tab           = $_GET['tab'] ?? 'info';
$errors        = [];
$success       = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $action = $_POST['action'] ?? '';

    if ($action === 'update_info') {
        $data = [
            'first_name' => trim($_POST['first_name'] ?? ''),
            'last_name'  => trim($_POST['last_name'] ?? ''),
            'phone'      => trim($_POST['phone'] ?? ''),
            'address'    => trim($_POST['address'] ?? ''),
            'city'       => trim($_POST['city'] ?? ''),
            'state'      => trim($_POST['state'] ?? ''),
            'zip'        => trim($_POST['zip'] ?? ''),
        ];
        if (!$data['first_name']) $errors[] = 'First name is required.';
        if (!$data['last_name'])  $errors[] = 'Last name is required.';

        if (empty($errors)) {
            $customerModel->updateProfile((int)$customer['customer_id'], $data);
            // Refresh session name
            $_SESSION['customer_name'] = $data['first_name'] . ' ' . $data['last_name'];
            flashMessage('success', 'Your information has been updated.');
            redirect(SITE_URL . '/public/customer/profile.php?tab=info');
        }
        $tab = 'info';
    }

    if ($action === 'update_email') {
        $email   = strtolower(trim($_POST['email'] ?? ''));
        $passChk = $_POST['password'] ?? '';
        if (!isValidEmail($email)) {
            $errors[] = 'A valid email address is required.';
        } elseif (!password_verify($passChk, $customer['password_hash'])) {
            $errors[] = 'Current password is incorrect.';
        } elseif ($email !== $customer['email'] && $customerModel->emailExists($email)) {
            $errors[] = 'That email address is already in use.';
        } else {
            $customerModel->updateEmail((int)$customer['customer_id'], $email);
            $_SESSION['customer_email'] = $email;
            flashMessage('success', 'Email address updated.');
            redirect(SITE_URL . '/public/customer/profile.php?tab=security');
        }
        $tab = 'security';
    }

    if ($action === 'update_password') {
        $current  = $_POST['current_password'] ?? '';
        $new      = $_POST['new_password'] ?? '';
        $confirm  = $_POST['confirm_password'] ?? '';
        if (!password_verify($current, $customer['password_hash'])) {
            $errors[] = 'Current password is incorrect.';
        } elseif (strlen($new) < 8) {
            $errors[] = 'New password must be at least 8 characters.';
        } elseif ($new !== $confirm) {
            $errors[] = 'New passwords do not match.';
        } else {
            $customerModel->updatePassword((int)$customer['customer_id'], $new);
            flashMessage('success', 'Password updated successfully.');
            redirect(SITE_URL . '/public/customer/profile.php?tab=security');
        }
        $tab = 'security';
    }

    // Re-fetch customer after any update
    $customer = $customerModel->getById((int)$customer['customer_id']);
}

$pageTitle = 'My Profile';
require_once BASE_PATH . '/includes/header.php';
require_once BASE_PATH . '/includes/nav.php';
?>
<main class="container py-4">
  <div class="row justify-content-center">
    <div class="col-lg-8">

      <div class="d-flex justify-content-between align-items-center mb-4">
        <h2 class="fw-bold mb-0">My Profile</h2>
        <a href="<?= SITE_URL ?>/public/customer/dashboard.php" class="btn btn-outline-secondary">
          <i class="bi bi-ticket-perforated me-1"></i>My Tickets
        </a>
      </div>

      <?= renderFlash() ?>

      <?php if ($errors): ?>
        <div class="alert alert-danger">
          <ul class="mb-0"><?php foreach ($errors as $err): ?><li><?= e($err) ?></li><?php endforeach; ?></ul>
        </div>
      <?php endif; ?>

      <!-- Tabs -->
      <ul class="nav nav-tabs mb-4">
        <li class="nav-item">
          <a class="nav-link <?= $tab === 'info' ? 'active' : '' ?>"
             href="?tab=info"><i class="bi bi-person me-1"></i>Contact Info</a>
        </li>
        <li class="nav-item">
          <a class="nav-link <?= $tab === 'security' ? 'active' : '' ?>"
             href="?tab=security"><i class="bi bi-shield-lock me-1"></i>Security</a>
        </li>
      </ul>

      <?php if ($tab === 'info'): ?>
        <!-- Contact Information -->
        <div class="card shadow-sm">
          <div class="card-header fw-bold">Contact Information</div>
          <div class="card-body">
            <form method="post">
              <?= csrfField() ?>
              <input type="hidden" name="action" value="update_info">
              <div class="row g-3">
                <div class="col-sm-6">
                  <label class="form-label fw-semibold">First Name <span class="text-danger">*</span></label>
                  <input type="text" name="first_name" class="form-control"
                         value="<?= e($customer['first_name']) ?>" required>
                </div>
                <div class="col-sm-6">
                  <label class="form-label fw-semibold">Last Name <span class="text-danger">*</span></label>
                  <input type="text" name="last_name" class="form-control"
                         value="<?= e($customer['last_name']) ?>" required>
                </div>
                <div class="col-sm-6">
                  <label class="form-label fw-semibold">Phone Number</label>
                  <input type="tel" name="phone" class="form-control"
                         value="<?= e($customer['phone'] ?? '') ?>">
                </div>
                <div class="col-sm-6">
                  <label class="form-label fw-semibold">Email Address</label>
                  <input type="email" class="form-control" value="<?= e($customer['email']) ?>" disabled>
                  <div class="form-text">
                    <a href="?tab=security">Change email</a> in Security tab.
                  </div>
                </div>
                <div class="col-12">
                  <label class="form-label fw-semibold">Street Address</label>
                  <input type="text" name="address" class="form-control"
                         value="<?= e($customer['address'] ?? '') ?>"
                         placeholder="123 Main St" autocomplete="street-address">
                </div>
                <div class="col-sm-5">
                  <label class="form-label fw-semibold">City</label>
                  <input type="text" name="city" class="form-control"
                         value="<?= e($customer['city'] ?? '') ?>"
                         autocomplete="address-level2">
                </div>
                <div class="col-sm-3">
                  <label class="form-label fw-semibold">State</label>
                  <select name="state" class="form-select" autocomplete="address-level1">
                    <option value="">—</option>
                    <?php
                    $states = ['AL','AK','AZ','AR','CA','CO','CT','DE','FL','GA','HI','ID','IL','IN','IA',
                               'KS','KY','LA','ME','MD','MA','MI','MN','MS','MO','MT','NE','NV','NH','NJ',
                               'NM','NY','NC','ND','OH','OK','OR','PA','RI','SC','SD','TN','TX','UT','VT',
                               'VA','WA','WV','WI','WY'];
                    foreach ($states as $st):
                    ?>
                      <option value="<?= $st ?>" <?= ($customer['state'] ?? '') === $st ? 'selected' : '' ?>><?= $st ?></option>
                    <?php endforeach; ?>
                  </select>
                </div>
                <div class="col-sm-4">
                  <label class="form-label fw-semibold">ZIP Code</label>
                  <input type="text" name="zip" class="form-control"
                         value="<?= e($customer['zip'] ?? '') ?>"
                         autocomplete="postal-code" maxlength="10">
                </div>
                <div class="col-12">
                  <button type="submit" class="btn btn-primary fw-bold">
                    <i class="bi bi-save me-1"></i>Save Changes
                  </button>
                </div>
              </div>
            </form>
          </div>
        </div>

      <?php else: ?>
        <!-- Security -->
        <div class="card shadow-sm mb-4">
          <div class="card-header fw-bold">Change Email Address</div>
          <div class="card-body">
            <form method="post">
              <?= csrfField() ?>
              <input type="hidden" name="action" value="update_email">
              <div class="mb-3">
                <label class="form-label fw-semibold">New Email Address</label>
                <input type="email" name="email" class="form-control"
                       value="<?= e($customer['email']) ?>" required>
              </div>
              <div class="mb-3">
                <label class="form-label fw-semibold">Current Password (to confirm)</label>
                <input type="password" name="password" class="form-control" required>
              </div>
              <button type="submit" class="btn btn-primary">Update Email</button>
            </form>
          </div>
        </div>

        <div class="card shadow-sm">
          <div class="card-header fw-bold">Change Password</div>
          <div class="card-body">
            <form method="post">
              <?= csrfField() ?>
              <input type="hidden" name="action" value="update_password">
              <div class="mb-3">
                <label class="form-label fw-semibold">Current Password</label>
                <input type="password" name="current_password" class="form-control" required>
              </div>
              <div class="mb-3">
                <label class="form-label fw-semibold">New Password</label>
                <input type="password" name="new_password" class="form-control"
                       required minlength="8">
                <div class="form-text">Minimum 8 characters.</div>
              </div>
              <div class="mb-3">
                <label class="form-label fw-semibold">Confirm New Password</label>
                <input type="password" name="confirm_password" class="form-control" required>
              </div>
              <button type="submit" class="btn btn-primary">Update Password</button>
            </form>
          </div>
        </div>
      <?php endif; ?>

    </div>
  </div>
</main>
<?php require_once BASE_PATH . '/includes/footer.php'; ?>
