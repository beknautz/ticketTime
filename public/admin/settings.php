<?php
require_once dirname(__DIR__, 2) . '/config/config.php';
require_once BASE_PATH . '/includes/auth.php';

requireAdmin(['admin']);

$errors  = [];
$imgDir  = BASE_PATH . '/public/assets/img/';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $action = $_POST['action'] ?? '';

    // ── Layout ────────────────────────────────────────────────
    if ($action === 'layout') {
        $cols = (int)($_POST['event_columns'] ?? 3);
        if (in_array($cols, [1, 2, 3, 4], true)) {
            setSiteSetting('event_columns', $cols);
            flashMessage('success', 'Event layout saved!');
        }
        redirect(SITE_URL . '/admin/settings.php');
    }

    if ($action === 'ticket_layout') {
        $cols = (int)($_POST['ticket_columns'] ?? 2);
        if (in_array($cols, [1, 2, 3, 4], true)) {
            setSiteSetting('ticket_columns', $cols);
            flashMessage('success', 'Ticket layout saved!');
        }
        redirect(SITE_URL . '/admin/settings.php');
    }

    // ── Logo ──────────────────────────────────────────────────
    if ($action === 'logo') {
        if (!empty($_POST['remove_logo']) && file_exists($imgDir . 'logo.png')) {
            unlink($imgDir . 'logo.png');
            flashMessage('success', 'Logo removed.');
            redirect(SITE_URL . '/admin/settings.php');
        }

        $file = $_FILES['logo'] ?? null;
        if ($file && $file['error'] === UPLOAD_ERR_OK) {
            if ($file['size'] > 4 * 1024 * 1024) {
                $errors[] = 'Logo must be under 4 MB.';
            } else {
                $allowed = ['image/jpeg', 'image/png', 'image/webp', 'image/svg+xml'];
                if (!in_array(mime_content_type($file['tmp_name']), $allowed, true)) {
                    $errors[] = 'Logo must be JPEG, PNG, WebP, or SVG.';
                } elseif (!move_uploaded_file($file['tmp_name'], $imgDir . 'logo.png')) {
                    $errors[] = 'Failed to save logo.';
                } else {
                    flashMessage('success', 'Logo updated!');
                    redirect(SITE_URL . '/admin/settings.php');
                }
            }
        } elseif ($file && $file['error'] !== UPLOAD_ERR_NO_FILE) {
            $errors[] = 'Upload error code ' . $file['error'];
        }
    }

    // ── Hero image ────────────────────────────────────────────
    if ($action === 'hero') {
        if (!empty($_POST['remove_hero'])) {
            foreach (glob($imgDir . 'hero.*') ?: [] as $old) { unlink($old); }
            flashMessage('success', 'Hero image removed.');
            redirect(SITE_URL . '/admin/settings.php');
        }

        $file = $_FILES['hero'] ?? null;
        if ($file && $file['error'] === UPLOAD_ERR_OK) {
            if ($file['size'] > 8 * 1024 * 1024) {
                $errors[] = 'Hero image must be under 8 MB.';
            } else {
                $mime    = mime_content_type($file['tmp_name']);
                $allowed = ['image/jpeg', 'image/png', 'image/webp'];
                if (!in_array($mime, $allowed, true)) {
                    $errors[] = 'Hero image must be JPEG, PNG, or WebP.';
                } else {
                    foreach (glob($imgDir . 'hero.*') ?: [] as $old) { unlink($old); }
                    $mimeExt = ['image/png' => 'png', 'image/webp' => 'webp'];
                    $ext = $mimeExt[$mime] ?? 'jpg';
                    if (!move_uploaded_file($file['tmp_name'], $imgDir . 'hero.' . $ext)) {
                        $errors[] = 'Failed to save hero image.';
                    } else {
                        flashMessage('success', 'Hero image updated!');
                        redirect(SITE_URL . '/admin/settings.php');
                    }
                }
            }
        } elseif ($file && $file['error'] !== UPLOAD_ERR_NO_FILE) {
            $errors[] = 'Upload error code ' . $file['error'];
        }
    }
}

$currentCols       = (int)getSiteSetting('event_columns', 3);
$currentTicketCols = (int)getSiteSetting('ticket_columns', 2);
$logoExists  = file_exists($imgDir . 'logo.png');
$heroFiles  = glob($imgDir . 'hero.*') ?: [];
$heroFile   = !empty($heroFiles) ? basename($heroFiles[0]) : null;

$pageTitle = 'Site Settings';
require_once __DIR__ . '/includes/admin-header.php';
?>

<h2 class="fw-bold mb-4">Site Settings</h2>

<?php if ($errors): ?>
  <div class="alert alert-danger"><ul class="mb-0">
    <?php foreach ($errors as $e): ?><li><?= e($e) ?></li><?php endforeach; ?>
  </ul></div>
<?php endif; ?>

<div class="row g-4">

  <!-- Logo -->
  <div class="col-md-6">
    <div class="card shadow-sm">
      <div class="card-header fw-bold"><i class="bi bi-image me-1"></i>Site Logo</div>
      <div class="card-body">
        <?php if ($logoExists): ?>
          <div class="mb-3 p-3 bg-dark rounded text-center">
            <img src="<?= SITE_URL ?>/assets/img/logo.png?<?= filemtime($imgDir . 'logo.png') ?>"
                 style="max-height:80px;max-width:100%" alt="Current logo">
          </div>
        <?php endif; ?>

        <form method="post" enctype="multipart/form-data">
          <?= csrfField() ?>
          <input type="hidden" name="action" value="logo">
          <div class="mb-3">
            <label class="form-label fw-semibold small">
              <?= $logoExists ? 'Replace logo' : 'Upload logo' ?>
            </label>
            <input type="file" name="logo" class="form-control form-control-sm"
                   accept="image/jpeg,image/png,image/webp,image/svg+xml">
            <div class="form-text">PNG, JPEG, WebP or SVG · max 4 MB<br>Recommended height: 80–120 px</div>
          </div>
          <?php if ($logoExists): ?>
            <div class="form-check mb-3">
              <input class="form-check-input" type="checkbox" name="remove_logo" value="1" id="removeLogo">
              <label class="form-check-label text-danger small" for="removeLogo">Remove current logo</label>
            </div>
          <?php endif; ?>
          <button type="submit" class="btn btn-primary btn-sm">
            <i class="bi bi-save me-1"></i>Save Logo
          </button>
        </form>
      </div>
    </div>
  </div>

  <!-- Hero image -->
  <div class="col-md-6">
    <div class="card shadow-sm">
      <div class="card-header fw-bold"><i class="bi bi-panorama me-1"></i>Homepage Hero Image</div>
      <div class="card-body">
        <?php if ($heroFile): ?>
          <div class="mb-3">
            <img src="<?= SITE_URL ?>/assets/img/<?= e($heroFile) ?>?<?= filemtime($imgDir . $heroFile) ?>"
                 class="img-fluid rounded" style="width:100%;height:130px;object-fit:cover" alt="Current hero">
          </div>
        <?php endif; ?>

        <form method="post" enctype="multipart/form-data">
          <?= csrfField() ?>
          <input type="hidden" name="action" value="hero">
          <div class="mb-3">
            <label class="form-label fw-semibold small">
              <?= $heroFile ? 'Replace hero image' : 'Upload hero image' ?>
            </label>
            <input type="file" name="hero" class="form-control form-control-sm"
                   accept="image/jpeg,image/png,image/webp">
            <div class="form-text">JPEG, PNG or WebP · max 8 MB<br>Recommended: 1920 × 600 px or wider</div>
          </div>
          <?php if ($heroFile): ?>
            <div class="form-check mb-3">
              <input class="form-check-input" type="checkbox" name="remove_hero" value="1" id="removeHero">
              <label class="form-check-label text-danger small" for="removeHero">Remove hero image</label>
            </div>
          <?php endif; ?>
          <button type="submit" class="btn btn-primary btn-sm">
            <i class="bi bi-save me-1"></i>Save Hero Image
          </button>
        </form>
      </div>
    </div>
  </div>

  <!-- Event Listing Layout -->
  <div class="col-md-6">
    <div class="card shadow-sm">
      <div class="card-header fw-bold"><i class="bi bi-grid me-1"></i>Event Listing Columns</div>
      <div class="card-body">
        <form method="post">
          <?= csrfField() ?>
          <input type="hidden" name="action" value="layout">
          <p class="small text-muted mb-2">Homepage &amp; Events page cards</p>
          <div class="d-flex gap-3 flex-wrap">
            <?php foreach ([1 => '1 Column', 2 => '2 Columns', 3 => '3 Columns', 4 => '4 Columns'] as $n => $label): ?>
              <div class="form-check">
                <input class="form-check-input" type="radio" name="event_columns"
                       id="ecols<?= $n ?>" value="<?= $n ?>"
                       <?= $currentCols === $n ? 'checked' : '' ?>>
                <label class="form-check-label" for="ecols<?= $n ?>">
                  <div class="d-flex gap-1 mb-1">
                    <?php for ($i = 0; $i < $n; $i++): ?>
                      <div style="height:28px;background:#D3AF37;border-radius:3px;flex:1;min-width:12px"></div>
                    <?php endfor; ?>
                  </div>
                  <small class="text-muted"><?= $label ?></small>
                </label>
              </div>
            <?php endforeach; ?>
          </div>
          <button type="submit" class="btn btn-primary btn-sm mt-3">
            <i class="bi bi-save me-1"></i>Save
          </button>
        </form>
      </div>
    </div>
  </div>

  <!-- Ticket Type Layout -->
  <div class="col-md-6">
    <div class="card shadow-sm">
      <div class="card-header fw-bold"><i class="bi bi-ticket-perforated me-1"></i>Ticket Type Columns</div>
      <div class="card-body">
        <form method="post">
          <?= csrfField() ?>
          <input type="hidden" name="action" value="ticket_layout">
          <p class="small text-muted mb-2">Ticket selection grid on the event page</p>
          <div class="d-flex gap-3 flex-wrap">
            <?php foreach ([1 => '1 Column', 2 => '2 Columns', 3 => '3 Columns', 4 => '4 Columns'] as $n => $label): ?>
              <div class="form-check">
                <input class="form-check-input" type="radio" name="ticket_columns"
                       id="tcols<?= $n ?>" value="<?= $n ?>"
                       <?= $currentTicketCols === $n ? 'checked' : '' ?>>
                <label class="form-check-label" for="tcols<?= $n ?>">
                  <div class="d-flex gap-1 mb-1">
                    <?php for ($i = 0; $i < $n; $i++): ?>
                      <div style="height:28px;background:#D3AF37;border-radius:3px;flex:1;min-width:12px"></div>
                    <?php endfor; ?>
                  </div>
                  <small class="text-muted"><?= $label ?></small>
                </label>
              </div>
            <?php endforeach; ?>
          </div>
          <button type="submit" class="btn btn-primary btn-sm mt-3">
            <i class="bi bi-save me-1"></i>Save
          </button>
        </form>
      </div>
    </div>
  </div>

</div>

<?php require_once __DIR__ . '/includes/admin-footer.php'; ?>
