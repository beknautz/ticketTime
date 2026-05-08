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

$ticketUrl   = SITE_URL . '/public/ticket.php?token=' . urlencode($ticket['qr_token']);
$statusClass = 'ticket-status-' . $ticket['status'];
$statusLabel = ['valid' => 'Valid', 'used' => 'Used - Entry Recorded', 'void' => 'Voided', 'refunded' => 'Refunded'][$ticket['status']] ?? ucfirst($ticket['status']);

// QR code — fetch from API and cache locally as PNG
$qrDataUrl = null;
if ($ticket['status'] === 'valid') {
    $qrCachePath = QR_PATH . '/ticket-' . $ticket['ticket_id'] . '.png';
    // Remove stale SVG placeholders from old broken fallback
    $qrSvgPath = QR_PATH . '/ticket-' . $ticket['ticket_id'] . '.svg';
    if (file_exists($qrSvgPath)) { @unlink($qrSvgPath); }

    if (!file_exists($qrCachePath)) {
        $apiUrl = 'https://api.qrserver.com/v1/create-qr-code/?'
                . http_build_query(['size' => '300x300', 'ecc' => 'H', 'data' => $ticketUrl]);
        $ch = curl_init($apiUrl);
        curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 10, CURLOPT_FOLLOWLOCATION => true]);
        $png  = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        if ($png && $code === 200) {
            file_put_contents($qrCachePath, $png);
        }
    }

    if (file_exists($qrCachePath)) {
        $qrDataUrl = 'data:image/png;base64,' . base64_encode(file_get_contents($qrCachePath));
    }
}

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
          <?php if ($qrDataUrl): ?>
            <img src="<?= $qrDataUrl ?>" alt="QR Code"
                 style="display:block;margin:0 auto;max-width:260px;width:100%;border-radius:4px;">
          <?php else: ?>
            <img src="https://api.qrserver.com/v1/create-qr-code/?size=300x300&ecc=H&data=<?= urlencode($ticketUrl) ?>"
                 alt="QR Code"
                 style="display:block;margin:0 auto;max-width:260px;width:100%;border-radius:4px;">
          <?php endif; ?>
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

