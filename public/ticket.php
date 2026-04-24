<?php
require_once dirname(__DIR__) . '/config/config.php';
require_once BASE_PATH . '/includes/auth.php';

$token = trim($_GET['token'] ?? '');

if (!$token) {
    http_response_code(404);
    die('Ticket not found.');
}

$ticketModel = new Ticket();
$ticket      = $ticketModel->getByQrToken($token);

if (!$ticket) {
    http_response_code(404);
    $pageTitle = 'Ticket Not Found';
    require_once BASE_PATH . '/includes/header.php';
    require_once BASE_PATH . '/includes/nav.php';
    echo '<div class="container py-5 text-center"><h2>Ticket not found</h2><p class="text-muted">This ticket link is invalid.</p></div>';
    require_once BASE_PATH . '/includes/footer.php';
    exit;
}

// Generate QR code
$qr       = new QRCode();
$filename = 'ticket-' . $ticket['ticket_id'];
$qrPath   = $qr->generate(SITE_URL . '/public/ticket.php?token=' . urlencode($ticket['qr_token']), $filename);
$qrBase64 = $qr->generateBase64(SITE_URL . '/public/ticket.php?token=' . urlencode($ticket['qr_token']), $filename);

$statusClass = 'ticket-status-' . $ticket['status'];
$statusLabel = match($ticket['status']) {
    'valid'    => 'Valid',
    'used'     => 'Used - Entry Recorded',
    'void'     => 'Voided',
    'refunded' => 'Refunded',
    default    => ucfirst($ticket['status']),
};

$pageTitle = 'Ticket: ' . $ticket['ticket_code'];
require_once BASE_PATH . '/includes/header.php';
require_once BASE_PATH . '/includes/nav.php';
?>
<main class="container py-4">
  <div class="ticket-view shadow-lg mx-auto">
    <!-- Ticket Header -->
    <div class="ticket-header">
      <h4 class="fw-bold mb-1"><?= e($ticket['event_name']) ?></h4>
      <p class="mb-1 opacity-90">
        <i class="bi bi-calendar3 me-1"></i><?= formatDate($ticket['event_start'], 'D, F j, Y \a\t g:i A') ?>
      </p>
      <p class="mb-0 opacity-75 small">
        <i class="bi bi-geo-alt me-1"></i><?= e($ticket['event_location'] ?? '') ?>
      </p>
    </div>

    <!-- Status Banner -->
    <div class="text-center py-2 fw-bold <?= $statusClass ?>">
      <?php if ($ticket['status'] === 'valid'): ?>
        <i class="bi bi-check-circle-fill me-1"></i>
      <?php elseif ($ticket['status'] === 'used'): ?>
        <i class="bi bi-check2-all me-1"></i>
      <?php else: ?>
        <i class="bi bi-x-circle-fill me-1"></i>
      <?php endif; ?>
      <?= $statusLabel ?>
    </div>

    <!-- Ticket Body -->
    <div class="ticket-body">
      <div class="row g-3 mb-3">
        <div class="col-6">
          <small class="text-muted d-block">Ticket Type</small>
          <strong><?= e($ticket['ticket_name']) ?></strong>
        </div>
        <div class="col-6">
          <small class="text-muted d-block">Order</small>
          <strong><?= e($ticket['public_order_id']) ?></strong>
        </div>
        <div class="col-12">
          <small class="text-muted d-block">Name</small>
          <strong><?= e($ticket['customer_first_name'] . ' ' . $ticket['customer_last_name']) ?></strong>
        </div>
        <?php if ($ticket['status'] === 'used' && $ticket['scanned_at']): ?>
        <div class="col-12">
          <small class="text-muted d-block">Scanned At</small>
          <strong><?= formatDate($ticket['scanned_at'], 'M j, Y g:i:s A') ?></strong>
          <?php if ($ticket['scan_location']): ?>
            <span class="text-muted small"> at <?= e($ticket['scan_location']) ?></span>
          <?php endif; ?>
        </div>
        <?php endif; ?>
      </div>

      <!-- QR Code -->
      <?php if ($ticket['status'] === 'valid'): ?>
        <div class="ticket-qr">
          <img src="<?= $qrBase64 ?>" alt="QR Code" class="img-fluid">
          <div class="ticket-code mt-2"><?= e($ticket['ticket_code']) ?></div>
          <small class="text-muted">Show this QR code at the gate</small>
        </div>
      <?php else: ?>
        <div class="ticket-qr text-center text-muted">
          <i class="bi bi-qr-code-scan display-4 opacity-25"></i>
          <p class="small mt-2">QR code unavailable for this ticket status</p>
        </div>
      <?php endif; ?>
    </div>
  </div>

  <div class="text-center mt-4 no-print">
    <button onclick="window.print()" class="btn btn-outline-secondary me-2">
      <i class="bi bi-printer me-1"></i> Print Ticket
    </button>
    <a href="<?= SITE_URL ?>/public/payment-success.php?order_id=<?= urlencode($ticket['public_order_id']) ?>"
       class="btn btn-outline-primary">
      <i class="bi bi-receipt me-1"></i> View Order
    </a>
  </div>
</main>
<?php require_once BASE_PATH . '/includes/footer.php'; ?>
