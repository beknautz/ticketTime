<?php
require_once dirname(__DIR__) . '/config/config.php';
require_once BASE_PATH . '/includes/auth.php';

requireAdmin(['admin']);

$eventModel = new Event();
$events     = $eventModel->getAll();

$pageTitle = 'Manage Events';
require_once __DIR__ . '/includes/admin-header.php';
?>
<div class="d-flex justify-content-between align-items-center mb-4">
  <h2 class="fw-bold mb-0">Events</h2>
  <a href="<?= SITE_URL ?>/admin/event-edit.php" class="btn btn-primary">
    <i class="bi bi-plus-lg me-1"></i>New Event
  </a>
</div>

<div class="card shadow-sm">
  <div class="card-body p-0">
    <div class="table-responsive">
      <table class="table table-hover mb-0 align-middle">
        <thead class="table-light">
          <tr>
            <th>Event</th>
            <th>Date</th>
            <th>Location</th>
            <th class="text-center">Sold / Cap</th>
            <th>Status</th>
            <th>Actions</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($events as $ev):
            $sold = (int)$ev['total_sold'];
            $cap  = (int)$ev['total_capacity'];
            $pct  = $cap > 0 ? round($sold / $cap * 100) : 0;
            $badgeClass = match($ev['status']) {
              'active'   => 'success',
              'draft'    => 'warning',
              'closed'   => 'secondary',
              'archived' => 'dark',
              default    => 'secondary'
            };
          ?>
            <tr>
              <td>
                <div class="fw-semibold"><?= e($ev['event_name']) ?></div>
                <small class="text-muted"><?= e($ev['event_slug']) ?></small>
              </td>
              <td class="text-nowrap"><?= formatDate($ev['event_start'], 'M j, Y') ?></td>
              <td class="text-muted small"><?= e($ev['event_location'] ?? '') ?></td>
              <td class="text-center">
                <div class="small fw-semibold"><?= $sold ?> / <?= $cap ?></div>
                <?php if ($cap > 0): ?>
                  <div class="progress" style="height:4px;width:80px;margin:auto">
                    <div class="progress-bar <?= $pct >= 90 ? 'bg-danger' : 'bg-success' ?>"
                         style="width:<?= $pct ?>%"></div>
                  </div>
                <?php endif; ?>
              </td>
              <td><span class="badge bg-<?= $badgeClass ?>"><?= ucfirst($ev['status']) ?></span></td>
              <td>
                <div class="btn-group btn-group-sm">
                  <a href="<?= SITE_URL ?>/admin/event-edit.php?id=<?= (int)$ev['event_id'] ?>"
                     class="btn btn-outline-secondary"><i class="bi bi-pencil"></i></a>
                  <a href="<?= SITE_URL ?>/admin/ticket-types.php?event_id=<?= (int)$ev['event_id'] ?>"
                     class="btn btn-outline-secondary"><i class="bi bi-tags"></i></a>
                  <a href="<?= SITE_URL ?>/admin/orders.php?event_id=<?= (int)$ev['event_id'] ?>"
                     class="btn btn-outline-secondary"><i class="bi bi-receipt"></i></a>
                  <a href="<?= SITE_URL ?>/public/event.php?slug=<?= urlencode($ev['event_slug']) ?>"
                     target="_blank" class="btn btn-outline-secondary"><i class="bi bi-eye"></i></a>
                </div>
              </td>
            </tr>
          <?php endforeach; ?>
          <?php if (empty($events)): ?>
            <tr><td colspan="6" class="text-center text-muted py-4">No events yet. <a href="event-edit.php">Create one</a></td></tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>
<?php require_once __DIR__ . '/includes/admin-footer.php'; ?>
