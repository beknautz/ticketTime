<?php
require_once dirname(__DIR__) . '/config/config.php';
require_once BASE_PATH . '/includes/auth.php';

$eventModel = new Event();
$events     = $eventModel->getActive();

$pageTitle = 'Home';

$heroFiles = glob(BASE_PATH . '/public/assets/img/hero.*') ?: [];
$heroFile  = !empty($heroFiles) ? basename($heroFiles[0]) : null;

require_once BASE_PATH . '/includes/header.php';
require_once BASE_PATH . '/includes/nav.php';
?>
<main>
  <!-- Hero -->
  <?php if ($heroFile): ?>
  <div class="hero-section" style="background-image:url('<?= SITE_URL ?>/assets/img/<?= e($heroFile) ?>')">
    <div class="hero-overlay">
      <div class="container text-center py-5">
        <h1 class="display-4 fw-bold text-white"><i class="bi bi-ticket-perforated-fill me-2"></i><?= SITE_NAME ?></h1>
        <p class="lead mb-4 text-white">Secure online event ticketing. Buy tickets, receive them instantly by email, scan at the gate.</p>
        <a href="<?= SITE_URL ?>/public/events.php" class="btn btn-light btn-lg fw-bold px-5">
          <i class="bi bi-search me-1"></i> Browse Events
        </a>
      </div>
    </div>
  </div>
  <?php else: ?>
  <div class="bg-primary text-white py-5">
    <div class="container text-center">
      <h1 class="display-4 fw-bold"><i class="bi bi-ticket-perforated-fill me-2"></i><?= SITE_NAME ?></h1>
      <p class="lead mb-4">Secure online event ticketing. Buy tickets, receive them instantly by email, scan at the gate.</p>
      <a href="<?= SITE_URL ?>/public/events.php" class="btn btn-light btn-lg fw-bold px-5">
        <i class="bi bi-search me-1"></i> Browse Events
      </a>
    </div>
  </div>
  <?php endif; ?>

  <div class="container py-5">
    <?= renderFlash() ?>

    <?php if (empty($events)): ?>
      <div class="text-center py-5">
        <i class="bi bi-calendar-x display-1 text-muted"></i>
        <h3 class="mt-3 text-muted">No events on sale right now</h3>
        <p class="text-muted">Check back soon for upcoming events.</p>
      </div>
    <?php else: ?>
      <h2 class="fw-bold mb-4">Upcoming Events</h2>
      <div class="row g-4">
        <?php foreach ($events as $ev): ?>
          <div class="col-sm-6 col-lg-4">
            <div class="card event-card h-100 shadow-sm">
              <?php if (!empty($ev['event_image'])): ?>
                <img src="<?= SITE_URL ?>/public/assets/uploads/events/<?= e($ev['event_image']) ?>"
                     class="card-img-top" alt="<?= e($ev['event_name']) ?>">
              <?php else: ?>
                <div class="event-banner">
                  <i class="bi bi-music-note-beamed"></i>
                </div>
              <?php endif; ?>
              <div class="card-body">
                <h5 class="card-title fw-bold"><?= e($ev['event_name']) ?></h5>
                <p class="text-muted small mb-2">
                  <i class="bi bi-calendar3 me-1"></i><?= formatDate($ev['event_start'], 'D, M j, Y') ?>
                </p>
                <p class="text-muted small mb-2">
                  <i class="bi bi-geo-alt me-1"></i><?= e($ev['event_location'] ?? 'TBD') ?>
                </p>
                <p class="card-text small text-muted">
                  <?= e(mb_substr($ev['event_description'] ?? '', 0, 100)) ?>…
                </p>
                <?php if ($ev['min_price'] !== null): ?>
                  <p class="mb-0 fw-bold text-primary">
                    From <?= formatMoney((float)$ev['min_price']) ?>
                    <?php if ((int)($ev['tickets_remaining'] ?? 0) < 20 && (int)($ev['tickets_remaining'] ?? 0) > 0): ?>
                      <span class="badge bg-danger ms-1">Only <?= (int)$ev['tickets_remaining'] ?> left!</span>
                    <?php endif; ?>
                  </p>
                <?php endif; ?>
              </div>
              <div class="card-footer border-0 bg-transparent">
                <a href="<?= SITE_URL ?>/public/event.php?slug=<?= urlencode($ev['event_slug']) ?>"
                   class="btn btn-primary w-100">
                  Get Tickets <i class="bi bi-arrow-right ms-1"></i>
                </a>
              </div>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </div>

  <!-- Features strip -->
  <div class="bg-light py-5">
    <div class="container">
      <div class="row g-4 text-center">
        <div class="col-md-4">
          <i class="bi bi-shield-lock-fill display-4 text-primary"></i>
          <h5 class="mt-3 fw-bold">Secure Payments</h5>
          <p class="text-muted">Powered by Stripe. We never store your card details.</p>
        </div>
        <div class="col-md-4">
          <i class="bi bi-envelope-fill display-4 text-success"></i>
          <h5 class="mt-3 fw-bold">Instant Delivery</h5>
          <p class="text-muted">Tickets emailed to you immediately after purchase.</p>
        </div>
        <div class="col-md-4">
          <i class="bi bi-qr-code display-4 text-info"></i>
          <h5 class="mt-3 fw-bold">Easy Entry</h5>
          <p class="text-muted">QR code scan at the gate. No printing required.</p>
        </div>
      </div>
    </div>
  </div>
</main>
<?php require_once BASE_PATH . '/includes/footer.php'; ?>
