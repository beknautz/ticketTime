<?php
require_once dirname(__DIR__, 2) . '/config/config.php';
require_once BASE_PATH . '/includes/auth.php';

requireAdmin(['admin', 'box_office']);

$orderId    = (int)($_GET['id'] ?? 0);
$orderModel = new Order();
$order      = $orderId ? $orderModel->getById($orderId) : null;

if (!$order) {
    flashMessage('danger', 'Order not found.');
    redirect(SITE_URL . '/admin/orders.php');
}

$event   = (new Event())->getById((int)$order['event_id']);
$items   = $orderModel->getItems($orderId);
$tickets = $orderModel->getTickets($orderId);

// Handle actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $action = $_POST['action'] ?? '';

    if ($action === 'void_ticket' && canAdmin()) {
        $ticketId = (int)($_POST['ticket_id'] ?? 0);
        (new Ticket())->voidTicket($ticketId, currentAdminId());
        flashMessage('success', 'Ticket voided.');
        redirect(SITE_URL . '/admin/order-view.php?id=' . $orderId);
    }

    if ($action === 'delete_order' && canAdmin()) {
        $orderModel->delete($orderId);
        flashMessage('success', 'Order ' . e($order['public_order_id']) . ' deleted.');
        redirect(SITE_URL . '/admin/orders.php');
    }
}

$pageTitle = 'Order ' . $order['public_order_id'];
$extraHead = '<meta name="csrf-token" content="' . e(csrfToken()) . '">';
require_once __DIR__ . '/includes/admin-header.php';
?>
<div class="mb-4">
  <div class="d-flex justify-content-between align-items-start mb-2">
    <div>
      <h2 class="fw-bold mb-0">Order #<?= e($order['public_order_id']) ?></h2>
      <small class="text-muted">Created <?= formatDate($order['created_at'], 'M j, Y g:i A') ?></small>
    </div>
    <a href="<?= SITE_URL ?>/admin/orders.php" class="btn btn-outline-secondary btn-sm">
      <i class="bi bi-arrow-left me-1"></i>Back
    </a>
  </div>
  <div class="d-flex gap-2 flex-wrap">
    <?php if ($order['status'] === 'paid'): ?>
      <button class="btn btn-outline-secondary"
              hx-post="<?= SITE_URL ?>/actions/resend-tickets.php"
              hx-vals='{"public_order_id":"<?= e($order['public_order_id']) ?>"}'
              hx-target="#adminResponse"
              hx-swap="innerHTML">
        <i class="bi bi-envelope me-1"></i>Resend Tickets
      </button>
    <?php endif; ?>
    <?php if (canAdmin()): ?>
      <form method="post" onsubmit="return confirm('Permanently delete order <?= e($order['public_order_id']) ?> and all its tickets? This cannot be undone.')">
        <?= csrfField() ?>
        <input type="hidden" name="action" value="delete_order">
        <button type="submit" class="btn btn-danger">
          <i class="bi bi-trash me-1"></i>Delete Order
        </button>
      </form>
    <?php endif; ?>
  </div>
</div>

<div id="adminResponse" class="mb-3"></div>

<div class="row g-4">
  <!-- Order Details -->
  <div class="col-md-8">
    <!-- Customer Info -->
    <div class="card shadow-sm mb-4">
      <div class="card-header fw-bold">Customer Information</div>
      <div class="card-body">
        <div class="row g-3">
          <div class="col-sm-6">
            <small class="text-muted">Name</small>
            <div class="fw-bold"><?= e($order['customer_first_name'] . ' ' . $order['customer_last_name']) ?></div>
          </div>
          <div class="col-sm-6">
            <small class="text-muted">Email</small>
            <div><a href="mailto:<?= e($order['customer_email']) ?>"><?= e($order['customer_email']) ?></a></div>
          </div>
          <?php if ($order['customer_phone']): ?>
          <div class="col-sm-6">
            <small class="text-muted">Phone</small>
            <div><?= e($order['customer_phone']) ?></div>
          </div>
          <?php endif; ?>
          <?php if ($event): ?>
          <div class="col-sm-6">
            <small class="text-muted">Event</small>
            <div class="fw-bold"><?= e($event['event_name']) ?></div>
            <small class="text-muted"><?= formatDate($event['event_start'], 'M j, Y g:i A') ?></small>
          </div>
          <?php endif; ?>
        </div>
      </div>
    </div>

    <!-- Items -->
    <div class="card shadow-sm mb-4">
      <div class="card-header fw-bold">Order Items</div>
      <div class="card-body p-0">
        <div class="table-responsive">
          <table class="table mb-0">
            <thead class="table-light">
              <tr><th>Ticket</th><th class="text-center">Qty</th><th class="text-end">Unit</th><th class="text-end">Total</th></tr>
            </thead>
            <tbody>
              <?php foreach ($items as $item): ?>
                <tr>
                  <td><?= e($item['ticket_name']) ?></td>
                  <td class="text-center"><?= (int)$item['quantity'] ?></td>
                  <td class="text-end"><?= formatMoney((float)$item['unit_price'] + (float)$item['unit_fee']) ?></td>
                  <td class="text-end fw-bold"><?= formatMoney((float)$item['line_total']) ?></td>
                </tr>
              <?php endforeach; ?>
            </tbody>
            <tfoot class="table-light fw-bold">
              <tr>
                <td colspan="3">Total Paid</td>
                <td class="text-end"><?= formatMoney((float)$order['total']) ?></td>
              </tr>
            </tfoot>
          </table>
        </div>
      </div>
    </div>

    <!-- Tickets -->
    <div class="card shadow-sm">
      <div class="card-header fw-bold">Individual Tickets</div>
      <div class="card-body p-0">
        <div class="table-responsive">
          <table class="table table-sm mb-0 small">
            <thead class="table-light">
              <tr>
                <th>Code</th>
                <th>Type</th>
                <th>Status</th>
                <th class="d-none d-sm-table-cell">Scanned</th>
                <th>Actions</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($tickets as $t):
                $badgeClass = ['valid' => 'success', 'used' => 'secondary', 'void' => 'dark', 'refunded' => 'warning'][$t['status']] ?? 'secondary';
              ?>
                <tr>
                  <td class="text-monospace"><?= e($t['ticket_code']) ?></td>
                  <td><?= e($t['ticket_name']) ?></td>
                  <td><span class="badge bg-<?= $badgeClass ?>"><?= ucfirst($t['status']) ?></span></td>
                  <td class="d-none d-sm-table-cell">
                    <?= $t['scanned_at'] ? formatDate($t['scanned_at'], 'M j g:i A') : '—' ?>
                    <?php if ($t['scan_location']): ?>
                      <br><small class="text-muted"><?= e($t['scan_location']) ?></small>
                    <?php endif; ?>
                  </td>
                  <td>
                    <div class="d-flex gap-1">
                      <a href="<?= SITE_URL ?>/public/ticket.php?token=<?= urlencode($t['qr_token']) ?>"
                         target="_blank" class="btn btn-xs btn-outline-secondary btn-sm py-0">
                        <i class="bi bi-eye"></i>
                      </a>
                      <?php if ($t['status'] === 'valid' && canAdmin()): ?>
                        <form method="post" class="d-inline">
                          <?= csrfField() ?>
                          <input type="hidden" name="action" value="void_ticket">
                          <input type="hidden" name="ticket_id" value="<?= (int)$t['ticket_id'] ?>">
                          <button type="submit" class="btn btn-xs btn-outline-danger btn-sm py-0"
                                  onclick="return confirm('Void this ticket?')">
                            <i class="bi bi-x-circle"></i>
                          </button>
                        </form>
                      <?php endif; ?>
                    </div>
                  </td>
                </tr>
              <?php endforeach; ?>
              <?php if (empty($tickets)): ?>
                <tr><td colspan="5" class="text-center text-muted py-3">No tickets generated yet</td></tr>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </div>

  <!-- Sidebar -->
  <div class="col-md-4">
    <!-- Status Card -->
    <div class="card shadow-sm mb-3">
      <div class="card-header fw-bold">Payment Status</div>
      <div class="card-body">
        <?php
          $badgeClass = ['paid' => 'success', 'pending' => 'warning', 'failed' => 'danger', 'refunded' => 'info'][$order['status']] ?? 'secondary';
        ?>
        <span class="badge bg-<?= $badgeClass ?> fs-6 mb-3"><?= ucfirst($order['status']) ?></span>
        <dl class="row small mb-0">
          <dt class="col-6 text-muted">Provider</dt>
          <dd class="col-6"><?= ucfirst($order['payment_provider']) ?></dd>
          <?php if ($order['payment_intent_id']): ?>
          <dt class="col-6 text-muted">Payment ID</dt>
          <dd class="col-6 text-truncate small text-monospace"><?= e(substr($order['payment_intent_id'], 0, 20)) ?>…</dd>
          <?php endif; ?>
          <?php if ($order['paid_at']): ?>
          <dt class="col-6 text-muted">Paid At</dt>
          <dd class="col-6"><?= formatDate($order['paid_at'], 'M j, Y g:i A') ?></dd>
          <?php endif; ?>
          <dt class="col-6 text-muted">Subtotal</dt>
          <dd class="col-6"><?= formatMoney((float)$order['subtotal']) ?></dd>
          <dt class="col-6 text-muted">Fees</dt>
          <dd class="col-6"><?= formatMoney((float)$order['service_fee_total']) ?></dd>
          <?php if ((float)$order['tax_total'] > 0): ?>
          <dt class="col-6 text-muted">Tax</dt>
          <dd class="col-6"><?= formatMoney((float)$order['tax_total']) ?></dd>
          <?php endif; ?>
          <dt class="col-6 fw-bold">Total</dt>
          <dd class="col-6 fw-bold"><?= formatMoney((float)$order['total']) ?></dd>
        </dl>
      </div>
    </div>

    <!-- Will Call -->
    <div class="card shadow-sm mb-3">
      <div class="card-header fw-bold">Will Call Status</div>
      <div class="card-body">
        <span class="badge bg-<?= $order['willcall_status'] === 'picked_up' ? 'success' : 'secondary' ?> mb-2">
          <?= ucfirst(str_replace('_', ' ', $order['willcall_status'])) ?>
        </span>
        <?php if ($order['picked_up_at']): ?>
          <p class="small text-muted mb-0">
            Picked up <?= formatDate($order['picked_up_at'], 'M j g:i A') ?>
            <?php if ($order['picked_up_by']): ?> by <?= e($order['picked_up_by']) ?><?php endif; ?>
          </p>
        <?php endif; ?>
        <div class="mt-2">
          <code class="small"><?= e($order['public_order_id']) ?></code>
        </div>
      </div>
    </div>
  </div>
</div>

<?php require_once __DIR__ . '/includes/admin-footer.php'; ?>
