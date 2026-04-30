<?php
require_once dirname(__DIR__) . '/config/config.php';
require_once BASE_PATH . '/includes/auth.php';

requireAdmin(['admin']);

$eventModel = new Event();
$id         = (int)($_GET['id'] ?? 0);
$event      = $id ? $eventModel->getById($id) : null;
$isNew      = !$event;
$errors     = [];
$uploadDir  = BASE_PATH . '/public/assets/img/events/';

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

    if (!$data['event_name'])  $errors[] = 'Event name is required';
    if (!$data['event_start']) $errors[] = 'Event start date is required';
    if (!$data['event_end'])   $errors[] = 'Event end date is required';

    // Image already uploaded via AJAX to upload-image.php; pending_image holds the filename
    $pendingImage = '';
    if (!empty($_POST['pending_image'])) {
        $candidate = basename($_POST['pending_image']);
        if (preg_match('/^event-[0-9a-f]{16}\.(jpg|png|webp)$/', $candidate)
            && file_exists($uploadDir . $candidate)) {
            $pendingImage = $candidate;
        } else {
            $errors[] = 'Uploaded image not found. Please try uploading again.';
        }
    }

    if (!$errors) {
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }

        if ($isNew) {
            $id    = $eventModel->create($data);
            $event = $eventModel->getById($id);
            $isNew = false;
        } else {
            $eventModel->update($id, $data);
            $event = $eventModel->getById($id);
        }

        // Remove image if requested
        if (!empty($_POST['remove_image']) && !empty($event['event_image'])) {
            $old = $uploadDir . basename($event['event_image']);
            if (file_exists($old)) unlink($old);
            $eventModel->updateImage($id, null);
            $event = $eventModel->getById($id);
        }

        // Link the already-uploaded image to this event
        if ($pendingImage && empty($_POST['remove_image'])) {
            if (!empty($event['event_image'])) {
                $old = $uploadDir . basename($event['event_image']);
                if (file_exists($old)) unlink($old);
            }
            $eventModel->updateImage($id, $pendingImage);
            $event = $eventModel->getById($id);
        }

        flashMessage('success', 'Event saved!');
        redirect(SITE_URL . '/admin/event-edit.php?id=' . $id);
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

<?php if ($errors): ?>
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

    <!-- Banner Image -->
    <div class="card shadow-sm mb-3">
      <div class="card-header fw-bold"><i class="bi bi-image me-1"></i>Banner Image</div>
      <div class="card-body">

        <?php if (!empty($event['event_image'])): ?>
          <div class="mb-3">
            <img src="<?= SITE_URL ?>/public/assets/img/events/<?= e($event['event_image']) ?>"
                 class="img-fluid rounded mb-2"
                 style="width:100%;height:130px;object-fit:cover" alt="Current banner">
            <div class="form-check">
              <input class="form-check-input" type="checkbox" name="remove_image" id="removeImage" value="1">
              <label class="form-check-label text-danger small" for="removeImage">Remove current image</label>
            </div>
          </div>
        <?php endif; ?>

        <label class="form-label fw-semibold small">
          <?= !empty($event['event_image']) ? 'Replace with new image' : 'Upload banner image' ?>
        </label>
        <input type="hidden" name="pending_image" id="pendingImage" value="">
        <input type="file" id="eventImageInput" class="form-control form-control-sm"
               accept="image/jpeg,image/png,image/webp">
        <div class="form-text">JPEG, PNG or WebP · max 8 MB<br>Recommended: 1600 × 600 px or wider</div>

        <div id="uploadStatus" class="mt-2" style="display:none"></div>

        <div id="imagePreviewWrap" class="mt-3" style="display:none">
          <p class="small fw-semibold mb-1 text-muted">Preview:</p>
          <img id="imagePreview" src="" alt="Preview"
               class="img-fluid rounded" style="width:100%;height:130px;object-fit:cover">
        </div>
        <div id="imageError" class="text-danger small mt-2" style="display:none"></div>
      </div>
    </div>

    <!-- Date & Time -->
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

    <!-- Status -->
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

document.getElementById('eventImageInput').addEventListener('change', function() {
  const errDiv       = document.getElementById('imageError');
  const prevWrap     = document.getElementById('imagePreviewWrap');
  const preview      = document.getElementById('imagePreview');
  const statusDiv    = document.getElementById('uploadStatus');
  const pendingInput = document.getElementById('pendingImage');
  const file         = this.files[0];

  errDiv.style.display    = 'none';
  statusDiv.style.display = 'none';
  prevWrap.style.display  = 'none';
  pendingInput.value      = '';

  if (!file) return;

  if (file.size > 8 * 1024 * 1024) {
    errDiv.textContent   = 'Image must be under 8 MB.';
    errDiv.style.display = 'block';
    this.value = '';
    return;
  }

  statusDiv.innerHTML     = '<span class="spinner-border spinner-border-sm me-1"></span> Uploading…';
  statusDiv.className     = 'mt-2 text-muted small';
  statusDiv.style.display = 'block';

  const csrfToken = document.querySelector('meta[name="csrf-token"]').content;

  fetch('<?= SITE_URL ?>/admin/upload-image.php', {
    method: 'POST',
    headers: { 'Content-Type': file.type, 'X-CSRF-TOKEN': csrfToken },
    body: file,
  })
  .then(function(res) { return res.json(); })
  .then(function(data) {
    if (data.filename) {
      pendingInput.value      = data.filename;
      statusDiv.innerHTML     = '<i class="bi bi-check-circle-fill text-success me-1"></i> Uploaded';
      statusDiv.className     = 'mt-2 small text-success';
      const reader = new FileReader();
      reader.onload = function(e) {
        preview.src            = e.target.result;
        prevWrap.style.display = 'block';
      };
      reader.readAsDataURL(file);
    } else {
      errDiv.textContent      = data.error || 'Upload failed.';
      errDiv.style.display    = 'block';
      statusDiv.style.display = 'none';
    }
  })
  .catch(function() {
    errDiv.textContent      = 'Upload failed. Please try again.';
    errDiv.style.display    = 'block';
    statusDiv.style.display = 'none';
  });
});
</script>

<?php require_once __DIR__ . '/includes/admin-footer.php'; ?>
