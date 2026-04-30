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

    // Validate base64 image — avoid regex on multi-MB string (causes PCRE crash on IIS)
    $imageRaw   = null;
    $imageExt   = null;
    $imageB64   = trim($_POST['image_base64'] ?? '');
    if ($imageB64 !== '') {
        // Parse the data URI with string functions, not regex, to avoid PCRE on large data
        $commaPos = strpos($imageB64, ',');
        $header   = $commaPos !== false ? substr($imageB64, 0, $commaPos) : '';
        // header looks like: data:image/jpeg;base64
        $mimeMap  = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
        $detectedMime = '';
        foreach (array_keys($mimeMap) as $mime) {
            if ($header === 'data:' . $mime . ';base64') {
                $detectedMime = $mime;
                break;
            }
        }

        if (!$detectedMime || $commaPos === false) {
            $errors[] = 'Invalid image format. Please select a JPEG, PNG, or WebP file.';
        } else {
            $b64data  = substr($imageB64, $commaPos + 1);
            $imageRaw = base64_decode($b64data, true);
            if ($imageRaw === false) {
                $errors[] = 'Image data is corrupted. Please try again.';
                $imageRaw = null;
            } elseif (strlen($imageRaw) > 8 * 1024 * 1024) {
                $errors[] = 'Image must be under 8 MB.';
                $imageRaw = null;
            } else {
                // Verify magic bytes to confirm the file matches its declared type
                $magic   = substr($imageRaw, 0, 12);
                $isJpeg  = (substr($magic, 0, 2) === "\xFF\xD8");
                $isPng   = (substr($magic, 0, 8) === "\x89PNG\r\n\x1a\n");
                $isWebp  = (substr($magic, 0, 4) === 'RIFF' && substr($magic, 8, 4) === 'WEBP');
                $typeOk  = ($detectedMime === 'image/jpeg' && $isJpeg)
                        || ($detectedMime === 'image/png'  && $isPng)
                        || ($detectedMime === 'image/webp' && $isWebp);
                if (!$typeOk) {
                    $errors[] = 'Image content does not match its declared type.';
                    $imageRaw = null;
                } else {
                    $imageExt = $mimeMap[$detectedMime];
                }
            }
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

        // Write decoded image directly — no temp file involved
        if ($imageRaw !== null && empty($_POST['remove_image'])) {
            if (!empty($event['event_image'])) {
                $old = $uploadDir . basename($event['event_image']);
                if (file_exists($old)) unlink($old);
            }
            $name = 'event-' . $id . '-' . bin2hex(random_bytes(6)) . '.' . $imageExt;
            file_put_contents($uploadDir . $name, $imageRaw);
            $eventModel->updateImage($id, $name);
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

<!-- No enctype="multipart/form-data" — image is submitted as base64 POST field -->
<form method="post" class="row g-4">
  <?= csrfField() ?>
  <!-- Hidden field that holds the base64-encoded image -->
  <input type="hidden" name="image_base64" id="imageBase64">

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
            <img src="<?= SITE_URL ?>/public/assets/uploads/events/<?= e($event['event_image']) ?>"
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
        <input type="file" id="eventImageInput" class="form-control form-control-sm"
               accept="image/jpeg,image/png,image/webp">
        <div class="form-text">JPEG, PNG or WebP · max 8 MB<br>Recommended: 1600 × 600 px or wider</div>

        <div id="imagePreviewWrap" class="mt-3" style="display:none">
          <p class="small fw-semibold mb-1 text-muted">Preview:</p>
          <img id="imagePreview" src="" alt="Preview"
               class="img-fluid rounded" style="width:100%;height:130px;object-fit:cover">
          <p class="text-success small mt-1 mb-0"><i class="bi bi-check-circle me-1"></i>Ready to save</p>
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

// Encode selected image as base64 into hidden field — no temp file on server
document.getElementById('eventImageInput').addEventListener('change', function() {
  const errDiv   = document.getElementById('imageError');
  const prevWrap = document.getElementById('imagePreviewWrap');
  const preview  = document.getElementById('imagePreview');
  const hidden   = document.getElementById('imageBase64');
  const file     = this.files[0];

  errDiv.style.display   = 'none';
  prevWrap.style.display = 'none';
  hidden.value           = '';

  if (!file) return;

  const maxBytes = 8 * 1024 * 1024;
  if (file.size > maxBytes) {
    errDiv.textContent  = 'Image must be under 8 MB. Please choose a smaller file.';
    errDiv.style.display = 'block';
    this.value = '';
    return;
  }

  const reader = new FileReader();
  reader.onload = function(e) {
    hidden.value           = e.target.result; // data:image/jpeg;base64,...
    preview.src            = e.target.result;
    prevWrap.style.display = 'block';
  };
  reader.onerror = function() {
    errDiv.textContent   = 'Could not read file. Please try again.';
    errDiv.style.display = 'block';
  };
  reader.readAsDataURL(file);
});
</script>

<?php require_once __DIR__ . '/includes/admin-footer.php'; ?>
