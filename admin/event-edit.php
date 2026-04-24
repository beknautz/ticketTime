<?php
require_once dirname(__DIR__) . '/config/config.php';
require_once BASE_PATH . '/includes/auth.php';

requireAdmin(['admin']);

$eventModel = new Event();
$id         = (int)($_GET['id'] ?? 0);
$event      = $id ? $eventModel->getById($id) : null;
$isNew      = !$event;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();

    $data = [
        'event_name'        => trim($_POST['event_name'] ?? ''),
        'event_slug'        => preg_replace('/[^a-z0-9-]/', '', strtolower(trim($_POST['event_slug'] ?? ''))),
        'event_description' => trim($_POST['event_description'] ?? ''),
        'event_location'    => trim($_POST['event_location'] ?? ''),
        'event_start'       => trim($_POST['event_start'] ?? ''),
        'event_end'         => trim($_POST['event_end'] ?? ''),
        'sale_start'        => trim($_POST['sale_start'] ?? ''),
        'sale_end'          => trim($_POST['sale_end'] ?? ''),
        'status'            => $_POST['status'] ?? 'draft',
    ];

    $errors = [];
    if (!$data['event_name']) $errors[] = 'Event name is required';
    if (!$data['event_start']) $errors[] = 'Event start date is required';
    if (!$data['event_end'])   $errors[] = 'Event end date is required';

    if (!$errors) {
        if ($isNew) {
            $newId = $eventModel->create($data);
            flashMessage('success', 'Event created successfully!');
            redirect(SITE_URL . '/admin/event-edit.php?id=' . $newId);
        } else {
            $eventModel->update($id, $data);
            flashMessage('success', 'Event updated successfully!');
            redirect(SITE_URL . '/admin/event-edit.php?id=' . $id);
        }
    }
}

$pageTitle = $isNew ? 'New Event' : 'Edit Event';
require_once __DIR__ . '/includes/admin-header.php';
?>
<div class="d-flex justify-content-between align-items-center mb-4">
  <h2 class="fw-bold mb-0"><?= $isNew ? 'Create New Event' : 'Edit Event' ?></h2>
  <a href="<?= SITE_URL ?>/admin/events.php" class="btn btn-outline-secondary">
    <i class="bi bi-arrow-left me-1"></i>Back to Events
  </a>
</div>

<?php if (!empty($errors)): ?>
  <div class="alert alert-danger">
    <ul class="mb-0">
      <?php foreach ($errors as $err): ?><li><?= e($err) ?></li><?php endforeach; ?>
    </ul>
  </div>
<?php endif; ?>

<form method="post" class="row g-4">
  <?= csrfField() ?>

  <div class="col-md-8">
    <div class="card shadow-sm">
      <div class="card-header fw-bold">Event Details</div>
      <div class="card-body">
        <div class="mb-3">
          <label class="form-label fw-semibold">Event Name <span class="text-danger">*</span></label>
          <input type="text" name="event_name" class="form-control form-control-lg"
                 value="<?= e($event['event_name'] ?? '') ?>" required
                 oninput="autoSlug(this.value)">
        </div>
        <div class="mb-3">
          <label class="form-label fw-semibold">URL Slug</label>
          <div class="input-group">
            <span class="input-group-text text-muted small">/events/</span>
            <input type="text" name="event_slug" id="eventSlug" class="form-control"
                   value="<?= e($event['event_slug'] ?? '') ?>"
                   pattern="[a-z0-9-]+" required>
          </div>
        </div>
        <div class="mb-3">
          <label class="form-label fw-semibold">Description</label>
          <textarea name="event_description" class="form-control" rows="5"><?= e($event['event_description'] ?? '') ?></textarea>
        </div>
        <div class="mb-3">
          <label class="form-label fw-semibold">Location / Venue</label>
          <input type="text" name="event_location" class="form-control"
                 value="<?= e($event['event_location'] ?? '') ?>"
                 placeholder="Venue Name, Address, City">
        </div>
      </div>
    </div>
  </div>

  <div class="col-md-4">
    <div class="card shadow-sm mb-3">
      <div class="card-header fw-bold">Date &amp; Time</div>
      <div class="card-body">
        <div class="mb-3">
          <label class="form-label fw-semibold">Start <span class="text-danger">*</span></label>
          <input type="datetime-local" name="event_start" class="form-control"
                 value="<?= e($event ? date('Y-m-d\TH:i', strtotime($event['event_start'])) : '') ?>" required>
        </div>
        <div class="mb-3">
          <label class="form-label fw-semibold">End <span class="text-danger">*</span></label>
          <input type="datetime-local" name="event_end" class="form-control"
                 value="<?= e($event ? date('Y-m-d\TH:i', strtotime($event['event_end'])) : '') ?>" required>
        </div>
        <hr>
        <div class="mb-3">
          <label class="form-label fw-semibold">Sale Starts</label>
          <input type="datetime-local" name="sale_start" class="form-control"
                 value="<?= e($event && $event['sale_start'] ? date('Y-m-d\TH:i', strtotime($event['sale_start'])) : '') ?>">
        </div>
        <div class="mb-3">
          <label class="form-label fw-semibold">Sale Ends</label>
          <input type="datetime-local" name="sale_end" class="form-control"
                 value="<?= e($event && $event['sale_end'] ? date('Y-m-d\TH:i', strtotime($event['sale_end'])) : '') ?>">
        </div>
      </div>
    </div>

    <div class="card shadow-sm">
      <div class="card-header fw-bold">Status</div>
      <div class="card-body">
        <select name="status" class="form-select">
          <?php foreach (['draft', 'active', 'closed', 'archived'] as $s): ?>
            <option value="<?= $s ?>" <?= ($event['status'] ?? 'draft') === $s ? 'selected' : '' ?>>
              <?= ucfirst($s) ?>
            </option>
          <?php endforeach; ?>
        </select>
        <div class="form-text">Set to <strong>Active</strong> to make tickets purchasable.</div>
      </div>
    </div>
  </div>

  <div class="col-12">
    <button type="submit" class="btn btn-primary btn-lg">
      <i class="bi bi-save me-1"></i>Save Event
    </button>
    <?php if (!$isNew): ?>
      <a href="<?= SITE_URL ?>/admin/ticket-types.php?event_id=<?= $id ?>"
         class="btn btn-outline-secondary btn-lg ms-2">
        <i class="bi bi-tags me-1"></i>Manage Ticket Types
      </a>
    <?php endif; ?>
  </div>
</form>

<script>
function autoSlug(name) {
  const slug = document.getElementById('eventSlug');
  if (!slug.dataset.manual) {
    slug.value = name.toLowerCase().replace(/[^a-z0-9]+/g, '-').replace(/^-|-$/g, '');
  }
}
document.getElementById('eventSlug').addEventListener('input', function() {
  this.dataset.manual = 'true';
});
</script>

<?php require_once __DIR__ . '/includes/admin-footer.php'; ?>
