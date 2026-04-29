<?php
require_once dirname(__DIR__) . '/config/config.php';
require_once BASE_PATH . '/includes/auth.php';

requireAdmin(['admin']);

$eventModel = new Event();
$id         = (int)($_GET['id'] ?? 0);
$event      = $id ? $eventModel->getById($id) : null;
$isNew      = !$event;
$errors     = [];
$uploadDir  = BASE_PATH . '/public/assets/uploads/events/';

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

    // Validate image if provided
    $newImageFile = $_FILES['event_image'] ?? null;
    if ($newImageFile && $newImageFile['error'] === UPLOAD_ERR_OK) {
        $allowed = ['image/jpeg', 'image/png', 'image/webp'];
        $finfo   = finfo_open(FILEINFO_MIME_TYPE);
        $mime    = finfo_file($finfo, $newImageFile['tmp_name']);
        finfo_close($finfo);

        if (!in_array($mime, $allowed, true)) {
            $errors[] = 'Banner image must be JPEG, PNG, or WebP.';
        } elseif ($newImageFile['size'] > 10 * 1024 * 1024) {
            $errors[] = 'Banner image must be under 10 MB.';
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

        // Save new upload
        if ($newImageFile && $newImageFile['error'] === UPLOAD_ERR_OK && empty($_POST['remove_image'])) {
            // Delete old image first
            if (!empty($event['event_image'])) {
                $old = $uploadDir . basename($event['event_image']);
                if (file_exists($old)) unlink($old);
            }

            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $mime  = finfo_file($finfo, $newImageFile['tmp_name']);
            finfo_close($finfo);
            $ext  = match($mime) { 'image/png' => 'png', 'image/webp' => 'webp', default => 'jpg' };
            $name = 'event-' . $id . '-' . bin2hex(random_bytes(6)) . '.' . $ext;
            move_uploaded_file($newImageFile['tmp_name'], $uploadDir . $name);
            $eventModel->updateImage($id, $name);
            $event = $eventModel->getById($id);
        }

        flashMessage('success', $isNew ? 'Event created!' : 'Event updated!');
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

<form method="post" enctype="multipart/form-data" class="row g-4">
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
          <div class="mb-3" id="currentImageWrap">
            <img src="<?= SITE_URL ?>/public/assets/uploads/events/<?= e($event['event_image']) ?>"
                 class="img-fluid rounded mb-2" style="width:100%;height:140px;object-fit:cover"
                 alt="Current banner">
            <div class="form-check">
              <input class="form-check-input" type="checkbox" name="remove_image" id="removeImage" value="1">
              <label class="form-check-label text-danger small" for="removeImage">
                Remove current image
              </label>
            </div>
          </div>
        <?php endif; ?>

        <div>
          <label class="form-label fw-semibold small">
            <?= !empty($event['event_image']) ? 'Replace with new image' : 'Upload banner image' ?>
          </label>
          <input type="file" name="event_image" id="eventImageInput"
                 class="form-control form-control-sm"
                 accept="image/jpeg,image/png,image/webp">
          <div class="form-text">JPEG, PNG or WebP · max 10 MB<br>Recommended: 1600×600 px or wider</div>
        </div>

        <!-- Live preview of selected file -->
        <div id="imagePreviewWrap" class="mt-3" style="display:none">
          <p class="small fw-semibold mb-1 text-muted">Preview:</p>
          <img id="imagePreview" src="" alt="Preview"
               class="img-fluid rounded" style="width:100%;height:140px;object-fit:cover">
        </div>
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

// Live image preview
document.getElementById('eventImageInput').addEventListener('change', function() {
  const wrap    = document.getElementById('imagePreviewWrap');
  const preview = document.getElementById('imagePreview');
  if (this.files && this.files[0]) {
    preview.src = URL.createObjectURL(this.files[0]);
    wrap.style.display = 'block';
  } else {
    wrap.style.display = 'none';
  }
});
</script>

<?php require_once __DIR__ . '/includes/admin-footer.php'; ?>
