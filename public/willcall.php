<?php
require_once dirname(__DIR__) . '/config/config.php';
require_once BASE_PATH . '/includes/auth.php';

requireAdmin(['admin', 'box_office', 'scanner']);

$pageTitle = 'Will Call';
$extraHead = '<meta name="csrf-token" content="' . e(csrfToken()) . '">';
require_once BASE_PATH . '/includes/header.php';
?>
<nav class="navbar navbar-dark bg-dark">
  <div class="container-fluid">
    <span class="navbar-brand fw-bold"><i class="bi bi-person-badge me-1"></i>Will Call</span>
    <div class="d-flex gap-2">
      <a href="<?= SITE_URL ?>/admin/index.php" class="btn btn-sm btn-outline-light">Dashboard</a>
      <a href="<?= SITE_URL ?>/admin/logout.php" class="btn btn-sm btn-outline-light">Logout</a>
    </div>
  </div>
</nav>

<div class="container py-4">
  <div class="row justify-content-center">
    <div class="col-lg-8">

      <h2 class="fw-bold mb-4"><i class="bi bi-person-badge me-2"></i>Will Call Lookup</h2>

      <!-- Search Form -->
      <div class="card shadow-sm mb-4">
        <div class="card-body">
          <form id="willcallSearch">
            <?= csrfField() ?>
            <div class="input-group input-group-lg">
              <input type="text" name="search" id="willcallSearchInput" class="form-control"
                     placeholder="Order ID, barcode, email, or last name..."
                     autocomplete="off" autofocus>
              <button type="submit" class="btn btn-primary"
                      hx-post="<?= SITE_URL ?>/actions/willcall-lookup.php"
                      hx-include="#willcallSearch"
                      hx-target="#willcallResult"
                      hx-swap="innerHTML">
                <i class="bi bi-search me-1"></i> Search
              </button>
            </div>
            <div class="form-text mt-1">
              <i class="bi bi-info-circle me-1"></i>
              Search by Order ID (e.g. TT-2026-XXXX), barcode token, customer email, or last name.
            </div>
          </form>
        </div>
      </div>

      <!-- Results -->
      <div id="willcallResult">
        <!-- HTMX will inject results here -->
      </div>

      <!-- Token from URL (e.g. from QR scan of order barcode) -->
      <?php if ($token = trim($_GET['token'] ?? '')): ?>
      <script>
      document.addEventListener('DOMContentLoaded', function() {
        document.getElementById('willcallSearchInput').value = '<?= e(addslashes($token)) ?>';
        htmx.ajax('POST', '<?= SITE_URL ?>/actions/willcall-lookup.php', {
          values: {
            search: '<?= e(addslashes($token)) ?>',
            csrf_token: '<?= e(csrfToken()) ?>'
          },
          target: '#willcallResult',
          swap: 'innerHTML'
        });
      });
      </script>
      <?php endif; ?>
    </div>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://unpkg.com/htmx.org@1.9.12/dist/htmx.min.js"></script>
<script src="<?= SITE_URL ?>/public/assets/js/app.js"></script>
</body>
</html>
