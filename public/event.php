<?php
require_once dirname(__DIR__) . '/config/config.php';
require_once BASE_PATH . '/includes/auth.php';

$slug = trim($_GET['slug'] ?? '');
if (!$slug) {
    redirect(SITE_URL . '/public/events.php');
}

$eventModel = new Event();
$event      = $eventModel->getBySlug($slug);

if (!$event || $event['status'] !== 'active') {
    http_response_code(404);
    $pageTitle = 'Event Not Found';
    require_once BASE_PATH . '/includes/header.php';
    require_once BASE_PATH . '/includes/nav.php';
    echo '<div class="container py-5 text-center"><h2>Event not found</h2><a href="' . SITE_URL . '/public/events.php" class="btn btn-primary mt-3">Back to Events</a></div>';
    require_once BASE_PATH . '/includes/footer.php';
    exit;
}

$ticketTypes   = $eventModel->getTicketTypes((int)$event['event_id'], true);
$ticketColClass = eventColClass((int)getSiteSetting('event_columns', 3));
$saleActive    = true;
$saleMessage   = '';

if ($event['sale_start'] && strtotime($event['sale_start']) > time()) {
    $saleActive  = false;
    $saleMessage = 'Sales open ' . formatDate($event['sale_start']);
}
if ($event['sale_end'] && strtotime($event['sale_end']) < time()) {
    $saleActive  = false;
    $saleMessage = 'Sales have closed for this event.';
}

$pageTitle = $event['event_name'];
$extraHead = '<meta name="csrf-token" content="' . e(csrfToken()) . '">';
require_once BASE_PATH . '/includes/header.php';
require_once BASE_PATH . '/includes/nav.php';
?>
<main class="container py-4">
  <?= renderFlash() ?>

  <!-- Event Header -->
  <div class="row g-4 mb-4">
    <div class="col-md-8">
      <?php if (!empty($event['event_image'])): ?>
        <div class="event-banner-img mb-4">
          <img src="<?= SITE_URL ?>/public/assets/uploads/events/<?= e($event['event_image']) ?>"
               alt="<?= e($event['event_name']) ?>">
        </div>
      <?php else: ?>
        <div class="event-banner mb-4">
          <i class="bi bi-music-note-beamed"></i>
        </div>
      <?php endif; ?>
      <h1 class="fw-bold"><?= e($event['event_name']) ?></h1>
      <div class="d-flex flex-wrap gap-3 my-3">
        <span class="text-muted fs-5"><i class="bi bi-calendar3 me-1"></i><?= formatDate($event['event_start'], 'D, F j, Y') ?></span>
        <span class="text-muted fs-5"><i class="bi bi-clock me-1"></i><?= formatDate($event['event_start'], 'g:i A') ?> &ndash; <?= formatDate($event['event_end'], 'g:i A') ?></span>
      </div>
      <?php if ($event['event_location']): ?>
        <p class="text-muted"><i class="bi bi-geo-alt-fill me-1"></i><?= e($event['event_location']) ?></p>
      <?php endif; ?>
      <p class="mt-3"><?= nl2br(e($event['event_description'] ?? '')) ?></p>
    </div>

    <!-- Cart Summary -->
    <div class="col-md-4">
      <div class="card shadow-sm cart-summary">
        <div class="card-header bg-primary text-white fw-bold">
          <i class="bi bi-cart3 me-1"></i> Your Cart
        </div>
        <div class="card-body" id="cartSummary">
          <?php include __DIR__ . '/../includes/partials/cart-summary.php'; ?>
        </div>
      </div>
    </div>
  </div>

  <!-- Ticket Types -->
  <h3 class="fw-bold mb-3">Select Tickets</h3>
  <div id="cartResponse" class="mb-3"></div>

  <?php if (!$saleActive): ?>
    <div class="alert alert-warning"><i class="bi bi-clock me-1"></i><?= e($saleMessage) ?></div>
  <?php elseif (empty($ticketTypes)): ?>
    <div class="alert alert-info">No tickets available for this event yet.</div>
  <?php else: ?>
    <div class="row g-3">
      <?php foreach ($ticketTypes as $tt): ?>
        <?php
          $remaining = (int)$tt['quantity_available'] - (int)$tt['quantity_sold'];
          $isSoldOut = $tt['status'] === 'sold_out' || $remaining <= 0;
          $maxQty    = min((int)$tt['max_per_order'], $remaining);
        ?>
        <div class="<?= $ticketColClass ?>">
          <div class="ticket-type-card p-4 <?= $isSoldOut ? 'opacity-50' : '' ?>">
            <div class="d-flex justify-content-between align-items-start">
              <div>
                <h5 class="fw-bold mb-1"><?= e($tt['ticket_name']) ?></h5>
                <?php if ($tt['ticket_description']): ?>
                  <p class="text-muted small mb-2"><?= e($tt['ticket_description']) ?></p>
                <?php endif; ?>
                <div class="fw-bold fs-5 text-primary"><?= formatMoney((float)$tt['price']) ?></div>
                <?php if ((float)$tt['service_fee'] > 0): ?>
                  <small class="text-muted">+ <?= formatMoney((float)$tt['service_fee']) ?> service fee</small>
                <?php endif; ?>
              </div>
              <div class="text-end">
                <?php if ($isSoldOut): ?>
                  <span class="badge bg-danger fs-6">Sold Out</span>
                <?php elseif ($remaining <= 10): ?>
                  <span class="badge bg-warning text-dark"><?= $remaining ?> left</span>
                <?php endif; ?>
              </div>
            </div>

            <?php if (!$isSoldOut): ?>
              <div class="mt-3 d-flex align-items-center gap-3">
                <div class="ticket-qty-control">
                  <button type="button" class="qty-btn" onclick="CartQty.adjust(<?= (int)$tt['ticket_type_id'] ?>, -1)">−</button>
                  <span class="qty-display" id="qty-display-<?= (int)$tt['ticket_type_id'] ?>">0</span>
                  <button type="button" class="qty-btn" onclick="CartQty.adjust(<?= (int)$tt['ticket_type_id'] ?>, 1)">+</button>
                </div>
                <input type="hidden" id="qty-<?= (int)$tt['ticket_type_id'] ?>"
                       value="0" min="0" max="<?= $maxQty ?>">

                <button type="button"
                        id="add-btn-<?= (int)$tt['ticket_type_id'] ?>"
                        class="btn btn-primary"
                        disabled
                        hx-post="<?= SITE_URL ?>/actions/cart-add.php"
                        hx-include="#qty-<?= (int)$tt['ticket_type_id'] ?>"
                        hx-vals='{"ticket_type_id": "<?= (int)$tt['ticket_type_id'] ?>", "event_id": "<?= (int)$event['event_id'] ?>"}'
                        hx-target="#cartResponse"
                        hx-swap="innerHTML"
                        hx-on:htmx:after-request="document.getElementById('cartSummary').dispatchEvent(new Event('refresh')); CartQty.set(<?= (int)$tt['ticket_type_id'] ?>, 0)"
                        hx-indicator="#cartSpinner"
                        onclick="document.getElementById('qty-<?= (int)$tt['ticket_type_id'] ?>').value = document.getElementById('qty-display-<?= (int)$tt['ticket_type_id'] ?>').textContent">
                  <i class="bi bi-cart-plus me-1"></i> Add to Cart
                </button>
              </div>
            <?php endif; ?>
          </div>
        </div>
      <?php endforeach; ?>
    </div>

    <div id="cartSpinner" class="htmx-indicator text-center py-3" style="display:none">
      <div class="spinner-border text-primary"></div>
    </div>
  <?php endif; ?>
</main>

<script>
// Refresh cart summary on cart changes
document.getElementById('cartSummary')?.addEventListener('refresh', function () {
  htmx.ajax('GET', '<?= SITE_URL ?>/actions/cart-summary.php', { target: '#cartSummary', swap: 'innerHTML' });
});

// Auto-refresh cart on page load
htmx.ajax('GET', '<?= SITE_URL ?>/actions/cart-summary.php', { target: '#cartSummary', swap: 'innerHTML' });
</script>

<?php require_once BASE_PATH . '/includes/footer.php'; ?>
