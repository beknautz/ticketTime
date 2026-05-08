<?php
require_once dirname(__DIR__, 2) . '/config/config.php';
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
      <div class="col-12 col-sm-auto">
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
      <div class="col-12 col-sm-auto">
        <select name="status" class="form-select form-select-sm">
          <option value="">All Statuses</option>
          <?php foreach (['pending','paid','failed','cancelled','refunded'] as $s): ?>
            <option value="<?= $s ?>" <?= ($_GET['status'] ?? '') === $s ? 'selected' : '' ?>><?= ucfirst($s) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-12 col-sm">
        <input type="text" name="search" class="form-control form-control-sm"
               placeholder="Email, name, order ID…"
               value="<?= e($_GET['search'] ?? '') ?>">
      </div>
      <div class="col-12 col-sm-auto d-flex gap-2">
        <button type="submit" class="btn btn-primary btn-sm flex-fill flex-sm-grow-0">Filter</button>
        <a href="orders.php" class="btn btn-outline-secondary btn-sm flex-fill flex-sm-grow-0">Clear</a>
      </div>
    </form>
  </div>
</div>

<!-- Orders — card list on mobile, table on desktop -->
<div class="d-md-none">
  <?php foreach ($orders as $o):
    $badgeClass = ['paid' => 'success', 'pending' => 'warning', 'failed' => 'danger', 'refunded' => 'info'][$o['status']] ?? 'secondary';
  ?>
    <div class="card shadow-sm mb-3">
      <div class="card-body">
        <div class="d-flex justify-content-between align-items-start mb-1">
          <span class="fw-bold"><?= e($o['customer_first_name'] . ' ' . $o['customer_last_name']) ?></span>
          <span class="badge bg-<?= $badgeClass ?>"><?= ucfirst($o['status']) ?></span>
        </div>
        <div class="small text-muted mb-1"><?= e($o['customer_email']) ?></div>
        <div class="small text-muted mb-2"><?= e($o['event_name']) ?> &middot; <?= formatDate($o['created_at'], 'M j, g:i A') ?></div>
        <div class="d-flex justify-content-between align-items-center">
          <span class="fw-bold fs-6"><?= formatMoney((float)$o['total']) ?></span>
          <a href="<?= SITE_URL ?>/admin/order-view.php?id=<?= (int)$o['order_id'] ?>"
             class="btn btn-primary btn-sm">
            <i class="bi bi-eye me-1"></i>View Details
          </a>
        </div>
      </div>
    </div>
  <?php endforeach; ?>
  <?php if (empty($orders)): ?>
    <div class="text-center text-muted py-4">No orders found</div>
  <?php endif; ?>
</div>

<!-- Desktop table -->
<div class="card shadow-sm d-none d-md-block">
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
            $badgeClass = ['paid' => 'success', 'pending' => 'warning', 'failed' => 'danger', 'refunded' => 'info'][$o['status']] ?? 'secondary';
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
                <div class="d-flex gap-1">
                  <a href="<?= SITE_URL ?>/admin/order-view.php?id=<?= (int)$o['order_id'] ?>"
                     class="btn btn-sm btn-primary"><i class="bi bi-eye me-1"></i>View Details</a>
                  <?php if (canAdmin()): ?>
                    <form method="post" action="<?= SITE_URL ?>/admin/order-view.php?id=<?= (int)$o['order_id'] ?>"
                          onsubmit="return confirm('Delete order <?= e($o['public_order_id']) ?>? This cannot be undone.')">
                      <?= csrfField() ?>
                      <input type="hidden" name="action" value="delete_order">
                      <button type="submit" class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button>
                    </form>
                  <?php endif; ?>
                </div>
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
