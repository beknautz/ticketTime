<?php
require_once dirname(__DIR__) . '/config/config.php';
require_once BASE_PATH . '/includes/auth.php';

$publicOrderId = trim($_GET['order_id'] ?? '');

if (!$publicOrderId) {
    redirect(SITE_URL . '/public/events.php');
}

$orderModel = new Order();
$order      = $orderModel->getByPublicId($publicOrderId);

if (!$order) {
    flashMessage('danger', 'Order not found.');
    redirect(SITE_URL . '/public/events.php');
}

$event   = (new Event())->getById((int)$order['event_id']);
$tickets = $orderModel->getTickets((int)$order['order_id']);
$items   = $orderModel->getItems((int)$order['order_id']);

$pageTitle  = 'Order Confirmed';
// HTMX polling interval (ms) - polls until order status = paid
$pollNeeded = $order['status'] === 'pending';
$extraHead  = '<meta name="csrf-token" content="' . e(csrfToken()) . '">';
require_once BASE_PATH . '/includes/header.php';
require_once BASE_PATH . '/includes/nav.php';
?>
<main class="container py-4">
  <div class="row justify-content-center">
    <div class="col-lg-8">

      <!-- Status header -->
      <?php if ($order['status'] === 'paid'): ?>
        <div class="text-center mb-4">
          <div class="display-1 text-success"><i class="bi bi-check-circle-fill"></i></div>
          <h1 class="fw-bold mt-2">Payment Confirmed!</h1>
          <p class="lead text-muted">Your tickets are on their way to <?= e($order['customer_email']) ?></p>
        </div>
      <?php elseif ($order['status'] === 'pending'): ?>
        <div class="text-center mb-4" id="paymentStatus"
             hx-get="<?= SITE_URL ?>/actions/order-status.php?order_id=<?= urlencode($publicOrderId) ?>"
             hx-trigger="every 3s"
             hx-swap="outerHTML"
             hx-target="#paymentStatus">
          <div class="spinner-border text-primary mb-3" style="width:3rem;height:3rem"></div>
          <h2 class="fw-bold">Processing Payment...</h2>
          <p class="text-muted">Please wait while we confirm your payment. Do not refresh or close this page.</p>
        </div>
      <?php else: ?>
        <div class="alert alert-danger text-center">
          <h4>Payment Issue</h4>
          <p>There was a problem with your payment. Order status: <strong><?= e($order['status']) ?></strong></p>
          <a href="<?= SITE_URL ?>/public/checkout.php" class="btn btn-primary">Try Again</a>
        </div>
      <?php endif; ?>

      <?php $ticketMsg = getSiteSetting('ticket_pickup_message', ''); ?>
      <?php if ($ticketMsg && $order['status'] === 'paid'): ?>
        <div class="alert alert-info d-flex gap-2 mb-4">
          <i class="bi bi-info-circle-fill fs-5 flex-shrink-0 mt-1"></i>
          <span><?= e($ticketMsg) ?></span>
        </div>
      <?php endif; ?>

      <!-- Order Details -->
      <div class="card shadow-sm mb-4">
        <div class="card-header d-flex justify-content-between align-items-center">
          <h5 class="mb-0 fw-bold">Order #<?= e($order['public_order_id']) ?></h5>
          <?php
            $badgeClass = ['paid' => 'success', 'pending' => 'warning', 'failed' => 'danger'][$order['status']] ?? 'secondary';
          ?>
          <span class="badge bg-<?= $badgeClass ?> fs-6"><?= ucfirst($order['status']) ?></span>
        </div>
        <div class="card-body">
          <div class="row g-3 mb-3">
            <div class="col-sm-6">
              <small class="text-muted d-block">Customer</small>
              <strong><?= e($order['customer_first_name'] . ' ' . $order['customer_last_name']) ?></strong>
            </div>
            <div class="col-sm-6">
              <small class="text-muted d-block">Email</small>
              <strong><?= e($order['customer_email']) ?></strong>
            </div>
            <?php if ($event): ?>
            <div class="col-sm-6">
              <small class="text-muted d-block">Event</small>
              <strong><?= e($event['event_name']) ?></strong>
            </div>
            <div class="col-sm-6">
              <small class="text-muted d-block">Date</small>
              <strong><?= formatDate($event['event_start'], 'M j, Y g:i A') ?></strong>
            </div>
            <?php endif; ?>
          </div>

          <table class="table table-sm">
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
              <tr class="fw-bold">
                <td colspan="2">Total Paid</td>
                <td class="text-end text-success"><?= formatMoney((float)$order['total']) ?></td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>

      <!-- Tickets -->
      <?php if (!empty($tickets) && $order['status'] === 'paid'): ?>
        <div class="card shadow-sm mb-4">
          <div class="card-header fw-bold">
            <i class="bi bi-ticket-perforated me-2"></i>Your Tickets
          </div>
          <div class="card-body">
            <div class="row g-3">
              <?php foreach ($tickets as $t): ?>
                <div class="col-sm-6">
                  <div class="border rounded p-3 text-center">
                    <div class="badge bg-<?= $t['status'] === 'valid' ? 'success' : 'secondary' ?> mb-2">
                      <?= ucfirst($t['status']) ?>
                    </div>
                    <div class="fw-bold mb-1"><?= e($t['ticket_name']) ?></div>
                    <div class="text-monospace text-muted small mb-2"><?= e($t['ticket_code']) ?></div>
                    <a href="<?= SITE_URL ?>/public/ticket.php?token=<?= urlencode($t['qr_token']) ?>"
                       class="btn btn-sm btn-outline-primary" target="_blank">
                      <i class="bi bi-qr-code me-1"></i> View Ticket
                    </a>
                  </div>
                </div>
              <?php endforeach; ?>
            </div>
          </div>
        </div>

        <!-- Will-Call backup -->
        <div class="card shadow-sm mb-4 border-info">
          <div class="card-body">
            <h6 class="fw-bold"><i class="bi bi-person-badge me-2"></i>Will-Call Backup</h6>
            <p class="small text-muted mb-2">
              Can't access your email at the gate? Use this Order ID at the will-call window:
            </p>
            <div class="d-flex align-items-center gap-2">
              <code class="fs-5 fw-bold"><?= e($order['public_order_id']) ?></code>
              <button class="btn btn-sm btn-outline-secondary"
                      onclick="navigator.clipboard.writeText('<?= e($order['public_order_id']) ?>')">
                <i class="bi bi-clipboard"></i>
              </button>
            </div>
          </div>
        </div>

        <div class="d-flex gap-2 flex-wrap">
          <button class="btn btn-outline-secondary"
                  hx-post="<?= SITE_URL ?>/actions/resend-tickets.php"
                  hx-vals='{"public_order_id":"<?= e($order['public_order_id']) ?>"}'
                  hx-target="#resendResult"
                  hx-swap="innerHTML">
            <i class="bi bi-envelope me-1"></i> Resend Tickets
          </button>
          <a href="<?= SITE_URL ?>/public/events.php" class="btn btn-primary">
            <i class="bi bi-calendar2-event me-1"></i> Browse More Events
          </a>
        </div>
        <div id="resendResult" class="mt-2"></div>
      <?php endif; ?>

    </div>
  </div>
</main>
<?php require_once BASE_PATH . '/includes/footer.php'; ?>
