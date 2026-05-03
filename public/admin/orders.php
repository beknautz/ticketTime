<?php
require_once dirname(__DIR__) . '/config/config.php';
require_once BASE_PATH . '/includes/auth.php';

requireAdmin(['admin', 'box_office']);

$orderModel = new Order();
$eventModel = new Event();
$events     = $eventModel->getAll();

$page    = max(1, (int)($_GET['page'] ?? 1));
$perPage = 25;
$offset  = ($page - 1) * $perPage;

$filters = [
    'event_id' => (int)($_GET['event_id'] ?? 0) ?: null,
    'status'   => $_GET['status'] ?? '',
    'search'   => trim($_GET['search'] ?? ''),
];

$orders = $orderModel->getAll(array_filter($filters), $perPage, $offset);
$total  = $orderModel->countAll(array_filter($filters));

$pageTitle  = 'Orders';
$exportUrl  = SITE_URL . '/admin/reports.php?export=orders&' . http_build_query(array_filter($filters));
require_once __DIR__ . '/includes/admin-header.php';
?>
<div class="d-flex justify-content-between align-items-center mb-4">
  <h2 class="fw-bold mb-0">Orders <span class="badge bg-secondary"><?= number_format($total) ?></span></h2>
  <a href="<?= $exportUrl ?>" class="btn btn-outline-secondary">
    <i class="bi bi-download me-1"></i>Export CSV
  </a>
</div>

<!-- Filters -->
<div class="card shadow-sm mb-4">
  <div class="card-body py-2">
    <form method="get" class="row g-2 align-items-end">
      <div class="col-auto">
        <select name="event_id" class="form-select form-select-sm">
          <option value="">All Events</option>
          <?php foreach ($events as $ev): ?>
            <option value="<?= (int)$ev['event_id'] ?>"
                    <?= ((int)($_GET['event_id'] ?? 0)) === (int)$ev['event_id'] ? 'selected' : '' ?>>
              <?= e($ev['event_name']) ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-auto">
        <select name="status" class="form-select form-select-sm">
          <option value="">All Statuses</option>
          <?php foreach (['pending','paid','failed','cancelled','refunded'] as $s): ?>
            <option value="<?= $s ?>" <?= ($_GET['status'] ?? '') === $s ? 'selected' : '' ?>><?= ucfirst($s) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-auto">
        <input type="text" name="search" class="form-control form-control-sm"
               placeholder="Email, name, order ID…"
               value="<?= e($_GET['search'] ?? '') ?>">
      </div>
      <div class="col-auto">
        <button type="submit" class="btn btn-primary btn-sm">Filter</button>
        <a href="orders.php" class="btn btn-outline-secondary btn-sm">Clear</a>
      </div>
    </form>
  </div>
</div>

<!-- Orders Table -->
<div class="card shadow-sm">
  <div class="card-body p-0">
    <div class="table-responsive">
      <table class="table table-hover mb-0 align-middle small">
        <thead class="table-light">
          <tr>
            <th>Order ID</th>
            <th>Customer</th>
            <th>Event</th>
            <th class="text-end">Total</th>
            <th>Status</th>
            <th>Date</th>
            <th></th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($orders as $o):
            $badgeClass = match($o['status']) {
              'paid'    => 'success',
              'pending' => 'warning',
              'failed'  => 'danger',
              'refunded'=> 'info',
              default   => 'secondary'
            };
          ?>
            <tr>
              <td><span class="text-monospace"><?= e($o['public_order_id']) ?></span></td>
              <td>
                <?= e($o['customer_first_name'] . ' ' . $o['customer_last_name']) ?>
                <br><small class="text-muted"><?= e($o['customer_email']) ?></small>
              </td>
              <td class="text-muted"><?= e($o['event_name']) ?></td>
              <td class="text-end fw-bold"><?= formatMoney((float)$o['total']) ?></td>
              <td><span class="badge bg-<?= $badgeClass ?>"><?= ucfirst($o['status']) ?></span></td>
              <td class="text-muted"><?= formatDate($o['created_at'], 'M j g:i A') ?></td>
              <td>
                <a href="<?= SITE_URL ?>/admin/order-view.php?id=<?= (int)$o['order_id'] ?>"
                   class="btn btn-sm btn-outline-secondary"><i class="bi bi-eye"></i></a>
              </td>
            </tr>
          <?php endforeach; ?>
          <?php if (empty($orders)): ?>
            <tr><td colspan="7" class="text-center text-muted py-4">No orders found</td></tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<div class="mt-3">
  <?= paginationLinks($total, $perPage, $page, 'orders.php?' . http_build_query(array_filter($filters))) ?>
</div>

<?php require_once __DIR__ . '/includes/admin-footer.php'; ?>
