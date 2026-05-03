<?php
require_once dirname(__DIR__, 2) . '/config/config.php';
require_once BASE_PATH . '/includes/auth.php';

requireAdmin(['admin']);

$eventModel = new Event();
$ttModel    = new TicketType();
$eventId    = (int)($_GET['event_id'] ?? 0);

if (!$eventId) {
    redirect(SITE_URL . '/admin/events.php');
}

$event = $eventModel->getById($eventId);
if (!$event) {
    flashMessage('danger', 'Event not found.');
    redirect(SITE_URL . '/admin/events.php');
}

$error  = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $action = $_POST['action'] ?? '';

    if ($action === 'delete') {
        $err = $ttModel->delete((int)($_POST['ticket_type_id'] ?? 0));
        if ($err) {
            $error = $err;
        } else {
            $success = 'Ticket type deleted.';
        }
    } elseif ($action === 'create' || $action === 'update') {
        $data = [
            'event_id'           => $eventId,
            'ticket_name'        => trim($_POST['ticket_name'] ?? ''),
            'ticket_description' => trim($_POST['ticket_description'] ?? ''),
            'price'              => (float)($_POST['price'] ?? 0),
            'service_fee'        => (float)($_POST['service_fee'] ?? 0),
            'quantity_available' => (int)($_POST['quantity_available'] ?? 0),
            'max_per_order'      => (int)($_POST['max_per_order'] ?? 10),
            'status'             => $_POST['status'] ?? 'active',
            'sort_order'         => (int)($_POST['sort_order'] ?? 0),
        ];

        if (!$data['ticket_name'] || $data['quantity_available'] <= 0) {
            $error = 'Name and quantity are required.';
        } else {
            if ($action === 'update' && isset($_POST['ticket_type_id'])) {
                $ttModel->update((int)$_POST['ticket_type_id'], $data);
                $success = 'Ticket type updated.';
            } else {
                $ttModel->create($data);
                $success = 'Ticket type created.';
            }
        }
    }
}


$ticketTypes = $eventModel->getTicketTypes($eventId);

$pageTitle = 'Ticket Types — ' . $event['event_name'];
require_once __DIR__ . '/includes/admin-header.php';
?>
<div class="d-flex justify-content-between align-items-center mb-4">
  <div>
    <h2 class="fw-bold mb-0">Ticket Types</h2>
    <small class="text-muted"><?= e($event['event_name']) ?></small>
  </div>
  <a href="<?= SITE_URL ?>/admin/events.php" class="btn btn-outline-secondary">
    <i class="bi bi-arrow-left me-1"></i>Back
  </a>
</div>

<?php if ($error): ?><div class="alert alert-danger"><?= e($error) ?></div><?php endif; ?>
<?php if ($success): ?><div class="alert alert-success"><?= e($success) ?></div><?php endif; ?>

<div class="row g-4">
  <!-- Existing types -->
  <div class="col-md-8">
    <?php if (empty($ticketTypes)): ?>
      <div class="alert alert-info">No ticket types yet. Create one to the right.</div>
    <?php else: ?>
      <?php foreach ($ticketTypes as $tt):
        $remaining = (int)$tt['quantity_available'] - (int)$tt['quantity_sold'];
      ?>
        <div class="card shadow-sm mb-3">
          <div class="card-header d-flex justify-content-between align-items-center">
            <span class="fw-bold"><?= e($tt['ticket_name']) ?></span>
            <span class="badge bg-<?= $tt['status'] === 'active' ? 'success' : ($tt['status'] === 'sold_out' ? 'danger' : 'secondary') ?>">
              <?= ucfirst(str_replace('_', ' ', $tt['status'])) ?>
            </span>
          </div>
          <div class="card-body">
            <div class="row g-2">
              <div class="col-sm-4">
                <small class="text-muted">Price</small>
                <div class="fw-bold"><?= formatMoney((float)$tt['price']) ?></div>
              </div>
              <div class="col-sm-4">
                <small class="text-muted">Fee</small>
                <div class="fw-bold"><?= formatMoney((float)$tt['service_fee']) ?></div>
              </div>
              <div class="col-sm-4">
                <small class="text-muted">Sold / Available</small>
                <div class="fw-bold"><?= (int)$tt['quantity_sold'] ?> / <?= (int)$tt['quantity_available'] ?></div>
              </div>
            </div>
            <div class="mt-3 d-flex align-items-center gap-2 flex-wrap">
              <form method="post" class="d-inline">
                <?= csrfField() ?>
                <input type="hidden" name="action" value="update">
                <input type="hidden" name="ticket_type_id" value="<?= (int)$tt['ticket_type_id'] ?>">
                <input type="hidden" name="ticket_name" value="<?= e($tt['ticket_name']) ?>">
                <input type="hidden" name="ticket_description" value="<?= e($tt['ticket_description']) ?>">
                <input type="hidden" name="price" value="<?= (float)$tt['price'] ?>">
                <input type="hidden" name="service_fee" value="<?= (float)$tt['service_fee'] ?>">
                <input type="hidden" name="quantity_available" value="<?= (int)$tt['quantity_available'] ?>">
                <input type="hidden" name="max_per_order" value="<?= (int)$tt['max_per_order'] ?>">
                <input type="hidden" name="sort_order" value="<?= (int)$tt['sort_order'] ?>">
                <select name="status" class="form-select form-select-sm d-inline-block w-auto" onchange="this.form.submit()">
                  <?php foreach (['active', 'inactive', 'sold_out'] as $s): ?>
                    <option value="<?= $s ?>" <?= $tt['status'] === $s ? 'selected' : '' ?>><?= ucfirst(str_replace('_', ' ', $s)) ?></option>
                  <?php endforeach; ?>
                </select>
              </form>
              <button type="button"
                      class="btn btn-outline-danger btn-sm"
                      onclick="confirmDeleteTT(<?= (int)$tt['ticket_type_id'] ?>, <?= htmlspecialchars(json_encode($tt['ticket_name']), ENT_QUOTES) ?>, <?= (int)$tt['quantity_sold'] ?>)">
                <i class="bi bi-trash me-1"></i>Delete
              </button>
            </div>
          </div>
        </div>
      <?php endforeach; ?>
    <?php endif; ?>
  </div>

  <!-- Create new type -->
  <div class="col-md-4">
    <div class="card shadow-sm">
      <div class="card-header fw-bold"><i class="bi bi-plus-circle me-1"></i>Add Ticket Type</div>
      <div class="card-body">
        <form method="post">
          <?= csrfField() ?>
          <input type="hidden" name="action" value="create">
          <input type="hidden" name="event_id" value="<?= $eventId ?>">
          <div class="mb-3">
            <label class="form-label fw-semibold">Name <span class="text-danger">*</span></label>
            <input type="text" name="ticket_name" class="form-control" required
                   placeholder="e.g. General Admission">
          </div>
          <div class="mb-3">
            <label class="form-label fw-semibold">Description</label>
            <textarea name="ticket_description" class="form-control" rows="2" placeholder="Optional"></textarea>
          </div>
          <div class="row g-2 mb-3">
            <div class="col-6">
              <label class="form-label fw-semibold">Price</label>
              <div class="input-group">
                <span class="input-group-text"><?= CURRENCY_SYMBOL ?></span>
                <input type="number" name="price" class="form-control" min="0" step="0.01" value="0.00" required>
              </div>
            </div>
            <div class="col-6">
              <label class="form-label fw-semibold">Fee</label>
              <div class="input-group">
                <span class="input-group-text"><?= CURRENCY_SYMBOL ?></span>
                <input type="number" name="service_fee" class="form-control" min="0" step="0.01" value="0.00">
              </div>
            </div>
          </div>
          <div class="row g-2 mb-3">
            <div class="col-6">
              <label class="form-label fw-semibold">Qty Available</label>
              <input type="number" name="quantity_available" class="form-control" min="1" required>
            </div>
            <div class="col-6">
              <label class="form-label fw-semibold">Max / Order</label>
              <input type="number" name="max_per_order" class="form-control" min="1" value="10">
            </div>
          </div>
          <div class="mb-3">
            <label class="form-label fw-semibold">Sort Order</label>
            <input type="number" name="sort_order" class="form-control" value="0">
          </div>
          <button type="submit" class="btn btn-primary w-100">
            <i class="bi bi-plus-lg me-1"></i>Create Ticket Type
          </button>
        </form>
      </div>
    </div>
  </div>
</div>
<!-- Hidden delete form -->
<form id="deleteTTForm" method="post" style="display:none">
  <?= csrfField() ?>
  <input type="hidden" name="action" value="delete">
  <input type="hidden" name="ticket_type_id" id="deleteTTId">
</form>

<!-- Confirmation modal -->
<div class="modal fade" id="deleteTTModal" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header border-0 pb-0">
        <h5 class="modal-title text-danger"><i class="bi bi-exclamation-triangle me-2"></i>Delete Ticket Type</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <p>Delete <strong id="deleteTTName"></strong>?</p>
        <p class="text-muted small mb-0" id="deleteTTWarning"></p>
      </div>
      <div class="modal-footer border-0 pt-0">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
        <button type="button" class="btn btn-danger" id="confirmDeleteTTBtn">
          <i class="bi bi-trash me-1"></i>Delete
        </button>
      </div>
    </div>
  </div>
</div>

<script>
function confirmDeleteTT(id, name, sold) {
  if (sold > 0) {
    alert('Cannot delete "' + name + '" — ' + sold + ' ticket(s) have already been sold. Set it to Inactive instead.');
    return;
  }
  document.getElementById('deleteTTId').value  = id;
  document.getElementById('deleteTTName').textContent = name;
  document.getElementById('deleteTTWarning').textContent =
    'This permanently removes the ticket type. This cannot be undone.';
  document.getElementById('confirmDeleteTTBtn').onclick = function() {
    document.getElementById('deleteTTForm').submit();
  };
  new bootstrap.Modal(document.getElementById('deleteTTModal')).show();
}
</script>

<?php require_once __DIR__ . '/includes/admin-footer.php'; ?>
