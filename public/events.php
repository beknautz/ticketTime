<?php
require_once dirname(__DIR__) . '/config/config.php';
require_once BASE_PATH . '/includes/auth.php';

$eventModel = new Event();
$events     = $eventModel->getActive();
$colClass   = eventColClass((int)getSiteSetting('event_columns', 3));

$pageTitle = 'Events';
require_once BASE_PATH . '/includes/header.php';
require_once BASE_PATH . '/includes/nav.php';
?>
<main class="container py-4">
  <h1 class="fw-bold mb-4"><i class="bi bi-calendar-event me-2"></i>All Events</h1>

  <?php if (empty($events)): ?>
    <div class="alert alert-info">No events currently on sale. Please check back soon.</div>
  <?php else: ?>
    <div class="row g-4">
      <?php foreach ($events as $ev): ?>
        <div class="<?= $colClass ?>">
          <div class="card event-card shadow-sm h-100">
            <div class="card-body">
              <h4 class="fw-bold"><?= e($ev['event_name']) ?></h4>
              <div class="d-flex flex-wrap gap-3 mb-3">
                <span class="text-muted"><i class="bi bi-calendar3 me-1"></i><?= formatDate($ev['event_start'], 'D, M j, Y \a\t g:i A') ?></span>
                <span class="text-muted"><i class="bi bi-geo-alt me-1"></i><?= e($ev['event_location'] ?? 'TBD') ?></span>
              </div>
              <p class="text-muted"><?= e(mb_substr($ev['event_description'] ?? '', 0, 200)) ?><?= strlen($ev['event_description'] ?? '') > 200 ? '…' : '' ?></p>
              <?php
                $remaining = (int)($ev['tickets_remaining'] ?? 0);
              ?>
              <div class="d-flex align-items-center justify-content-between mt-3">
                <div>
                  <?php if ($ev['min_price'] !== null): ?>
                    <span class="fs-5 fw-bold text-primary">From <?= formatMoney((float)$ev['min_price']) ?></span>
                  <?php endif; ?>
                  <?php if ($remaining > 0 && $remaining < 20): ?>
                    <span class="badge bg-danger ms-2">Only <?= $remaining ?> left</span>
                  <?php elseif ($remaining === 0): ?>
                    <span class="badge bg-secondary ms-2">Sold Out</span>
                  <?php endif; ?>
                </div>
                <a href="<?= SITE_URL ?>/public/event.php?slug=<?= urlencode($ev['event_slug']) ?>"
                   class="btn btn-primary">
                  Buy Tickets <i class="bi bi-arrow-right ms-1"></i>
                </a>
              </div>
            </div>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
</main>
<?php require_once BASE_PATH . '/includes/footer.php'; ?>
