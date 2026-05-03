<?php
require_once dirname(__DIR__, 2) . '/config/config.php';
require_once BASE_PATH . '/includes/auth.php';

requireAdmin(['admin', 'box_office', 'scanner']);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo htmxAlert('danger', 'Invalid request method.');
    exit;
}

verifyCsrf();

$search = trim($_POST['search'] ?? '');

if (strlen($search) < 2) {
    echo htmxAlert('warning', 'Please enter at least 2 characters to search.');
    exit;
}

$orderModel = new Order();
$results    = $orderModel->searchWillCall($search);

if (empty($results)):
?>
  <div class="alert alert-info">
    <i class="bi bi-search me-1"></i>No paid orders found matching "<strong><?= e($search) ?></strong>".
  </div>
<?php else: ?>
  <div class="fw-semibold mb-2 text-muted"><?= count($results) ?> result(s) for "<?= e($search) ?>"</div>
  <?php foreach ($results as $order):
    $tickets  = $orderModel->getTickets((int)$order['order_id']);
    $items    = $orderModel->getItems((int)$order['order_id']);
    $isPicked = $order['willcall_status'] === 'picked_up';
  ?>
    <div class="card shadow-sm mb-3" id="wc-order-<?= (int)$order['order_id'] ?>">
      <div class="card-header d-flex justify-content-between align-items-center">
        <div>
          <span class="fw-bold"><?= e($order['public_order_id']) ?></span>
          <span class="badge bg-success ms-2">Paid</span>
          <?php if ($isPicked): ?>
            <span class="badge bg-secondary ms-1">Picked Up</span>
          <?php endif; ?>
        </div>
        <small class="text-muted"><?= formatDate($order['created_at'], 'M j, Y g:i A') ?></small>
      </div>
      <div class="card-body">
        <div class="row g-3 mb-3">
          <div class="col-sm-6">
            <strong><?= e($order['customer_first_name'] . ' ' . $order['customer_last_name']) ?></strong><br>
            <a href="mailto:<?= e($order['customer_email']) ?>" class="text-muted small">
              <?= e($order['customer_email']) ?>
            </a>
            <?php if ($order['customer_phone']): ?>
              <br><span class="text-muted small"><?= e($order['customer_phone']) ?></span>
            <?php endif; ?>
          </div>
          <div class="col-sm-6">
            <small class="text-muted d-block">Event</small>
            <strong><?= e($order['event_name']) ?></strong>
            <br><small class="text-muted"><?= formatDate($order['event_start'], 'M j, Y g:i A') ?></small>
          </div>
        </div>

        <!-- Order Items Summary -->
        <table class="table table-sm table-bordered mb-3">
          <thead class="table-light">
            <tr><th>Ticket</th><th class="text-center">Qty</th><th class="text-end">Total</th></tr>
          </thead>
          <tbody>
            <?php foreach ($items as $item): ?>
              <tr>
                <td><?= e($item['ticket_name']) ?></td>
                <td class="text-center"><?= (int)$item['quantity'] ?></td>
                <td class="text-end"><?= formatMoney((float)$item['line_total']) ?></td>
              </tr>
            <?php endforeach; ?>
            <tr class="fw-bold table-light">
              <td colspan="2">Total</td>
              <td class="text-end"><?= formatMoney((float)$order['total']) ?></td>
            </tr>
          </tbody>
        </table>

        <!-- Ticket Status -->
        <?php if (!empty($tickets)): ?>
          <div class="mb-3">
            <strong>Individual Tickets</strong>
            <div class="row g-2 mt-1">
              <?php foreach ($tickets as $t):
                $badgeClass = match($t['status']) {
                  'valid'    => 'success',
                  'used'     => 'secondary',
                  'void'     => 'dark',
                  'refunded' => 'warning',
                  default    => 'secondary'
                };
              ?>
                <div class="col-sm-6">
                  <div class="border rounded p-2 small d-flex justify-content-between">
                    <div>
                      <span class="badge bg-<?= $badgeClass ?>"><?= ucfirst($t['status']) ?></span>
                      <span class="ms-1"><?= e($t['ticket_code']) ?></span>
                    </div>
                    <a href="<?= SITE_URL ?>/public/ticket.php?token=<?= urlencode($t['qr_token']) ?>"
                       target="_blank" class="btn btn-xs btn-outline-secondary btn-sm py-0">
                      <i class="bi bi-eye"></i>
                    </a>
                  </div>
                </div>
              <?php endforeach; ?>
            </div>
          </div>
        <?php endif; ?>

        <!-- Pick-up status -->
        <?php if ($isPicked): ?>
          <div class="alert alert-secondary mb-3">
            <i class="bi bi-check2-all me-1"></i>
            <strong>Picked up</strong> on <?= formatDate($order['picked_up_at'], 'M j, Y g:i A') ?>
            <?php if ($order['picked_up_by']): ?> by <?= e($order['picked_up_by']) ?><?php endif; ?>
          </div>
        <?php endif; ?>

        <!-- Actions -->
        <div class="d-flex gap-2 flex-wrap">
          <?php if (!$isPicked): ?>
            <button class="btn btn-success"
                    hx-post="<?= SITE_URL ?>/actions/mark-willcall-picked-up.php"
                    hx-vals='{"order_id":"<?= (int)$order['order_id'] ?>"}'
                    hx-target="#wc-order-<?= (int)$order['order_id'] ?>"
                    hx-swap="outerHTML"
                    hx-confirm="Mark this order as picked up?">
              <i class="bi bi-bag-check me-1"></i>Mark Picked Up
            </button>
          <?php endif; ?>

          <button class="btn btn-outline-secondary"
                  hx-post="<?= SITE_URL ?>/actions/resend-tickets.php"
                  hx-vals='{"public_order_id":"<?= e($order['public_order_id']) ?>"}'
                  hx-target="#resend-<?= (int)$order['order_id'] ?>"
                  hx-swap="innerHTML">
            <i class="bi bi-envelope me-1"></i>Resend Tickets
          </button>

          <a href="<?= SITE_URL ?>/admin/order-view.php?id=<?= (int)$order['order_id'] ?>"
             target="_blank" class="btn btn-outline-primary">
            <i class="bi bi-eye me-1"></i>Full Order
          </a>

          <button onclick="window.open('<?= SITE_URL ?>/public/payment-success.php?order_id=<?= urlencode($order['public_order_id']) ?>', '_blank')"
                  class="btn btn-outline-secondary">
            <i class="bi bi-printer me-1"></i>Print Tickets
          </button>
        </div>
        <div id="resend-<?= (int)$order['order_id'] ?>" class="mt-2"></div>
      </div>
    </div>
  <?php endforeach; ?>
<?php endif; ?>
