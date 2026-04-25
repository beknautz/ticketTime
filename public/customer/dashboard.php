<?php
require_once dirname(dirname(__DIR__)) . '/config/config.php';
requireCustomerLogin();

$customer = currentCustomer();
if (!$customer) {
    customerLogout();
    redirect(SITE_URL . '/public/customer/login.php');
}
$customerModel = new Customer();
$orders        = $customerModel->getOrders($customer['customer_id'], $customer['email']);

$pageTitle = 'My Tickets';
require_once BASE_PATH . '/includes/header.php';
require_once BASE_PATH . '/includes/nav.php';
?>
<main class="container py-4">
  <?= renderFlash() ?>

  <div class="d-flex justify-content-between align-items-center mb-4">
    <div>
      <h2 class="fw-bold mb-0">My Tickets</h2>
      <p class="text-muted mb-0">Welcome back, <?= e($customer['first_name']) ?>!</p>
    </div>
    <a href="<?= SITE_URL ?>/public/events.php" class="btn btn-primary">
      <i class="bi bi-ticket-perforated me-1"></i>Buy More Tickets
    </a>
  </div>

  <?php if (empty($orders)): ?>
    <div class="text-center py-5">
      <i class="bi bi-ticket-perforated display-1 text-muted opacity-25"></i>
      <h4 class="mt-3 text-muted">No tickets yet</h4>
      <p class="text-muted">Your purchased tickets will appear here.</p>
      <a href="<?= SITE_URL ?>/public/events.php" class="btn btn-primary mt-2">Browse Events</a>
    </div>
  <?php else: ?>
    <div class="row g-4">
      <?php foreach ($orders as $order):
        $orderModel = new Order();
        $tickets    = $orderModel->getTickets((int)$order['order_id']);
        $items      = $orderModel->getItems((int)$order['order_id']);
        $validCount = count(array_filter($tickets, fn($t) => $t['status'] === 'valid'));
        $usedCount  = count(array_filter($tickets, fn($t) => $t['status'] === 'used'));
      ?>
        <div class="col-12">
          <div class="card shadow-sm">
            <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
              <div>
                <span class="fw-bold"><?= e($order['event_name']) ?></span>
                <span class="text-muted ms-2 small">
                  <i class="bi bi-calendar3 me-1"></i><?= formatDate($order['event_start'], 'M j, Y') ?>
                </span>
              </div>
              <div class="d-flex align-items-center gap-2">
                <span class="badge bg-success"><?= $validCount ?> valid</span>
                <?php if ($usedCount > 0): ?>
                  <span class="badge bg-secondary"><?= $usedCount ?> used</span>
                <?php endif; ?>
                <span class="text-muted small">Order #<?= e($order['public_order_id']) ?></span>
                <span class="fw-bold"><?= formatMoney((float)$order['total']) ?></span>
              </div>
            </div>

            <div class="card-body">
              <!-- Items summary -->
              <div class="mb-3">
                <?php foreach ($items as $item): ?>
                  <span class="badge bg-light text-dark border me-1 mb-1 p-2">
                    <?= (int)$item['quantity'] ?>× <?= e($item['ticket_name']) ?>
                  </span>
                <?php endforeach; ?>
              </div>

              <!-- Individual tickets -->
              <div class="row g-2">
                <?php foreach ($tickets as $t):
                  $statusClass = match($t['status']) {
                    'valid'    => 'success',
                    'used'     => 'secondary',
                    'void'     => 'dark',
                    'refunded' => 'warning',
                    default    => 'secondary'
                  };
                ?>
                  <div class="col-sm-6 col-md-4 col-lg-3">
                    <div class="border rounded p-3 text-center h-100 d-flex flex-column justify-content-between">
                      <div>
                        <span class="badge bg-<?= $statusClass ?> mb-2"><?= ucfirst($t['status']) ?></span>
                        <div class="small fw-semibold"><?= e($t['ticket_name']) ?></div>
                        <div class="text-monospace text-muted" style="font-size:.75rem"><?= e($t['ticket_code']) ?></div>
                      </div>
                      <?php if ($t['status'] === 'valid'): ?>
                        <a href="<?= SITE_URL ?>/public/ticket.php?token=<?= urlencode($t['qr_token']) ?>"
                           class="btn btn-sm btn-outline-primary mt-2" target="_blank">
                          <i class="bi bi-qr-code me-1"></i>View
                        </a>
                      <?php elseif ($t['status'] === 'used'): ?>
                        <div class="text-muted small mt-2">
                          Used <?= $t['scanned_at'] ? formatDate($t['scanned_at'], 'M j g:i A') : '' ?>
                        </div>
                      <?php endif; ?>
                    </div>
                  </div>
                <?php endforeach; ?>
              </div>

              <!-- Actions -->
              <div class="mt-3 d-flex gap-2 flex-wrap">
                <a href="<?= SITE_URL ?>/public/payment-success.php?order_id=<?= urlencode($order['public_order_id']) ?>"
                   class="btn btn-sm btn-outline-secondary">
                  <i class="bi bi-receipt me-1"></i>View Order
                </a>
                <button class="btn btn-sm btn-outline-secondary"
                        hx-post="<?= SITE_URL ?>/actions/resend-tickets.php"
                        hx-vals='{"public_order_id":"<?= e($order['public_order_id']) ?>"}'
                        hx-target="#resend-<?= (int)$order['order_id'] ?>"
                        hx-swap="innerHTML">
                  <i class="bi bi-envelope me-1"></i>Resend to Email
                </button>
                <button onclick="window.print()" class="btn btn-sm btn-outline-secondary">
                  <i class="bi bi-printer me-1"></i>Print
                </button>
              </div>
              <div id="resend-<?= (int)$order['order_id'] ?>" class="mt-2"></div>
            </div>

            <div class="card-footer text-muted small d-flex justify-content-between">
              <span>Purchased <?= formatDate($order['created_at'], 'M j, Y g:i A') ?></span>
              <span><?= e($order['event_location'] ?? '') ?></span>
            </div>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
</main>
<?php require_once BASE_PATH . '/includes/footer.php'; ?>
