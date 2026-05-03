<?php
require_once dirname(__DIR__, 2) . '/config/config.php';
require_once BASE_PATH . '/includes/auth.php';

requireAdmin(['admin']);

$eventModel = new Event();

// ── Delete handler ────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'delete') {
    verifyCsrf();
    $delId  = (int)($_POST['event_id'] ?? 0);
    $event  = $delId ? $eventModel->getById($delId) : null;
    if ($event) {
        $err = $eventModel->delete($delId);
        if ($err) {
            flashMessage('danger', $err);
        } else {
            // Remove banner image file if present
            if (!empty($event['event_image'])) {
                $img = BASE_PATH . '/public/assets/uploads/events/' . basename($event['event_image']);
                if (file_exists($img)) unlink($img);
            }
            flashMessage('success', '"' . $event['event_name'] . '" deleted.');
        }
    }
    redirect(SITE_URL . '/admin/events.php');
}

$events    = $eventModel->getAll();
$pageTitle = 'Manage Events';
require_once __DIR__ . '/includes/admin-header.php';
?>
<div class="d-flex justify-content-between align-items-center mb-4">
  <h2 class="fw-bold mb-0">Events</h2>
  <a href="<?= SITE_URL ?>/admin/event-edit.php" class="btn btn-primary">
    <i class="bi bi-plus-lg me-1"></i>New Event
  </a>
</div>

<?= renderFlash() ?>

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
            $badgeClass = ['active' => 'success', 'draft' => 'warning', 'closed' => 'secondary', 'archived' => 'dark'][$ev['status']] ?? 'secondary';
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
                     class="btn btn-outline-secondary" title="Edit"><i class="bi bi-pencil"></i></a>
                  <a href="<?= SITE_URL ?>/admin/ticket-types.php?event_id=<?= (int)$ev['event_id'] ?>"
                     class="btn btn-outline-secondary" title="Ticket Types"><i class="bi bi-tags"></i></a>
                  <a href="<?= SITE_URL ?>/admin/orders.php?event_id=<?= (int)$ev['event_id'] ?>"
                     class="btn btn-outline-secondary" title="Orders"><i class="bi bi-receipt"></i></a>
                  <a href="<?= SITE_URL ?>/public/event.php?slug=<?= urlencode($ev['event_slug']) ?>"
                     target="_blank" class="btn btn-outline-secondary" title="View"><i class="bi bi-eye"></i></a>
                  <button type="button"
                          class="btn btn-outline-danger"
                          title="Delete event"
                          onclick="confirmDeleteEvent(<?= (int)$ev['event_id'] ?>, <?= htmlspecialchars(json_encode($ev['event_name']), ENT_QUOTES) ?>)">
                    <i class="bi bi-trash"></i>
                  </button>
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

<!-- Hidden delete form — submitted by JS after confirmation -->
<form id="deleteEventForm" method="post" style="display:none">
  <?= csrfField() ?>
  <input type="hidden" name="action" value="delete">
  <input type="hidden" name="event_id" id="deleteEventId">
</form>

<!-- Confirmation modal -->
<div class="modal fade" id="deleteModal" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header border-0 pb-0">
        <h5 class="modal-title text-danger"><i class="bi bi-exclamation-triangle me-2"></i>Delete Event</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <p>Are you sure you want to delete <strong id="deleteEventName"></strong>?</p>
        <p class="text-muted small mb-0">This will permanently remove the event, all ticket types, and any
        pending/failed orders. <strong class="text-danger">Paid orders will block the delete.</strong></p>
      </div>
      <div class="modal-footer border-0 pt-0">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
        <button type="button" class="btn btn-danger" id="confirmDeleteBtn">
          <i class="bi bi-trash me-1"></i>Delete Permanently
        </button>
      </div>
    </div>
  </div>
</div>

<script>
function confirmDeleteEvent(id, name) {
  document.getElementById('deleteEventId').value = id;
  document.getElementById('deleteEventName').textContent = name;
  document.getElementById('confirmDeleteBtn').onclick = function() {
    document.getElementById('deleteEventForm').submit();
  };
  new bootstrap.Modal(document.getElementById('deleteModal')).show();
}
</script>

<?php require_once __DIR__ . '/includes/admin-footer.php'; ?>
