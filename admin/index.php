<?php
require_once dirname(__DIR__) . '/config/config.php';
require_once BASE_PATH . '/includes/auth.php';

requireAdmin(['admin', 'box_office']);

$db = Database::getInstance();

// Global stats
$stats = $db->query("
    SELECT
        COUNT(DISTINCT o.order_id) as total_orders,
        COALESCE(SUM(CASE WHEN o.status = 'paid' THEN o.total ELSE 0 END), 0) as total_revenue,
        COALESCE(SUM(CASE WHEN o.status = 'paid' THEN 1 ELSE 0 END), 0) as paid_orders,
        COALESCE((SELECT SUM(quantity_sold) FROM ticket_types), 0) as tickets_sold,
        COALESCE((SELECT COUNT(*) FROM tickets WHERE status = 'used'), 0) as tickets_scanned,
        COALESCE((SELECT COUNT(*) FROM tickets WHERE status = 'valid'), 0) as tickets_valid
    FROM orders o
")->fetch();

// Recent orders
$recentOrders = $db->query("
    SELECT o.*, e.event_name
    FROM orders o
    JOIN events e ON e.event_id = o.event_id
    ORDER BY o.created_at DESC
    LIMIT 10
")->fetchAll();

// Events summary
$events = $db->query("
    SELECT e.*,
        COALESCE(SUM(tt.quantity_sold), 0) as sold,
        COALESCE(SUM(tt.quantity_available), 0) as capacity,
        COALESCE(SUM(CASE WHEN o.status = 'paid' THEN o.total ELSE 0 END), 0) as revenue
    FROM events e
    LEFT JOIN ticket_types tt ON tt.event_id = e.event_id
    LEFT JOIN orders o ON o.event_id = e.event_id
    WHERE e.status IN ('active','closed')
    GROUP BY e.event_id
    ORDER BY e.event_start DESC
    LIMIT 5
")->fetchAll();

$pageTitle = 'Dashboard';
require_once __DIR__ . '/includes/admin-header.php';
?>
<div class="d-flex justify-content-between align-items-center mb-4">
  <h2 class="fw-bold mb-0">Dashboard</h2>
  <span class="text-muted"><?= date('l, F j, Y') ?></span>
</div>

<!-- Stat Cards -->
<div class="row g-3 mb-4">
  <div class="col-sm-6 col-xl-3">
    <div class="card stat-card shadow-sm border-0 bg-primary text-white">
      <div class="card-body">
        <div class="d-flex justify-content-between">
          <div>
            <div class="text-white-50 small">Total Revenue</div>
            <div class="stat-number"><?= formatMoney((float)$stats['total_revenue']) ?></div>
          </div>
          <i class="bi bi-currency-dollar display-4 opacity-50"></i>
        </div>
      </div>
    </div>
  </div>
  <div class="col-sm-6 col-xl-3">
    <div class="card stat-card shadow-sm border-0 bg-success text-white">
      <div class="card-body">
        <div class="d-flex justify-content-between">
          <div>
            <div class="text-white-50 small">Tickets Sold</div>
            <div class="stat-number"><?= number_format((int)$stats['tickets_sold']) ?></div>
          </div>
          <i class="bi bi-ticket-perforated display-4 opacity-50"></i>
        </div>
      </div>
    </div>
  </div>
  <div class="col-sm-6 col-xl-3">
    <div class="card stat-card shadow-sm border-0 bg-info text-white">
      <div class="card-body">
        <div class="d-flex justify-content-between">
          <div>
            <div class="text-white-50 small">Scanned / Admitted</div>
            <div class="stat-number"><?= number_format((int)$stats['tickets_scanned']) ?></div>
          </div>
          <i class="bi bi-qr-code-scan display-4 opacity-50"></i>
        </div>
      </div>
    </div>
  </div>
  <div class="col-sm-6 col-xl-3">
    <div class="card stat-card shadow-sm border-0 bg-warning text-dark">
      <div class="card-body">
        <div class="d-flex justify-content-between">
          <div>
            <div class="text-muted small">Paid Orders</div>
            <div class="stat-number"><?= number_format((int)$stats['paid_orders']) ?></div>
          </div>
          <i class="bi bi-bag-check display-4 opacity-25"></i>
        </div>
      </div>
    </div>
  </div>
</div>

<div class="row g-4">
  <!-- Recent Orders -->
  <div class="col-lg-8">
    <div class="card shadow-sm">
      <div class="card-header d-flex justify-content-between align-items-center">
        <h6 class="mb-0 fw-bold">Recent Orders</h6>
        <a href="<?= SITE_URL ?>/admin/orders.php" class="btn btn-sm btn-outline-primary">View All</a>
      </div>
      <div class="card-body p-0">
        <div class="table-responsive">
          <table class="table table-hover mb-0 align-middle small">
            <thead class="table-light">
              <tr>
                <th>Order</th>
                <th>Customer</th>
                <th>Event</th>
                <th>Total</th>
                <th>Status</th>
                <th></th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($recentOrders as $o):
                $badgeClass = match($o['status']) {
                  'paid'    => 'success',
                  'pending' => 'warning',
                  'failed'  => 'danger',
                  default   => 'secondary'
                };
              ?>
                <tr>
                  <td><span class="text-monospace small"><?= e($o['public_order_id']) ?></span></td>
                  <td><?= e($o['customer_first_name'] . ' ' . $o['customer_last_name']) ?></td>
                  <td class="text-muted"><?= e($o['event_name']) ?></td>
                  <td><?= formatMoney((float)$o['total']) ?></td>
                  <td><span class="badge bg-<?= $badgeClass ?>"><?= ucfirst($o['status']) ?></span></td>
                  <td>
                    <a href="<?= SITE_URL ?>/admin/order-view.php?id=<?= (int)$o['order_id'] ?>"
                       class="btn btn-sm btn-outline-secondary">
                      <i class="bi bi-eye"></i>
                    </a>
                  </td>
                </tr>
              <?php endforeach; ?>
              <?php if (empty($recentOrders)): ?>
                <tr><td colspan="6" class="text-center text-muted py-4">No orders yet</td></tr>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </div>

  <!-- Events Summary -->
  <div class="col-lg-4">
    <div class="card shadow-sm">
      <div class="card-header d-flex justify-content-between align-items-center">
        <h6 class="mb-0 fw-bold">Events</h6>
        <?php if (canAdmin()): ?>
          <a href="<?= SITE_URL ?>/admin/events.php" class="btn btn-sm btn-outline-primary">Manage</a>
        <?php endif; ?>
      </div>
      <div class="card-body p-0">
        <?php foreach ($events as $ev): ?>
          <div class="p-3 border-bottom">
            <div class="d-flex justify-content-between align-items-start">
              <div>
                <div class="fw-semibold small"><?= e($ev['event_name']) ?></div>
                <div class="text-muted small"><?= formatDate($ev['event_start'], 'M j, Y') ?></div>
              </div>
              <span class="badge bg-<?= $ev['status'] === 'active' ? 'success' : 'secondary' ?>">
                <?= ucfirst($ev['status']) ?>
              </span>
            </div>
            <?php if ((int)$ev['capacity'] > 0): ?>
              <div class="mt-2">
                <div class="d-flex justify-content-between small text-muted mb-1">
                  <span><?= number_format((int)$ev['sold']) ?> sold</span>
                  <span><?= number_format((int)$ev['capacity']) ?> capacity</span>
                </div>
                <div class="progress" style="height:6px">
                  <div class="progress-bar bg-success"
                       style="width:<?= min(100, round($ev['sold'] / $ev['capacity'] * 100)) ?>%"></div>
                </div>
              </div>
            <?php endif; ?>
            <div class="mt-1 text-muted small"><?= formatMoney((float)$ev['revenue']) ?> revenue</div>
          </div>
        <?php endforeach; ?>
        <?php if (empty($events)): ?>
          <div class="p-3 text-center text-muted small">No events</div>
        <?php endif; ?>
      </div>
    </div>
  </div>
</div>

<?php require_once __DIR__ . '/includes/admin-footer.php'; ?>
