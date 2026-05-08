<?php
require_once dirname(__DIR__, 2) . '/config/config.php';
require_once BASE_PATH . '/includes/auth.php';

requireAdmin(['admin']);

$errors  = [];
$imgDir  = BASE_PATH . '/public/assets/img/';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $action = $_POST['action'] ?? '';

    // ── Mail / SendGrid ───────────────────────────────────────────────────
    if ($action === 'mail') {
        $key = trim($_POST['sendgrid_api_key'] ?? '');
        if ($key !== '' && strncmp($key, 'SG.', 3) !== 0) {
            $errors[] = 'SendGrid API key must start with SG.';
        } else {
            setSiteSetting('sendgrid_api_key', $key);
            setSiteSetting('mail_from_address', trim($_POST['mail_from_address'] ?? ''));
            setSiteSetting('mail_from_name',    trim($_POST['mail_from_name']    ?? ''));
            flashMessage('success', 'Mail settings saved!');
            redirect(SITE_URL . '/admin/settings.php');
        }
    }

    // ── Ticket pickup message ─────────────────────────────────────────────
    if ($action === 'ticket_message') {
        setSiteSetting('ticket_pickup_message', trim($_POST['ticket_pickup_message'] ?? ''));
        flashMessage('success', 'Ticket message saved!');
        redirect(SITE_URL . '/admin/settings.php');
    }

    // ── Contact Info ─────────────────────────────────────────
    if ($action === 'contact') {
        $email = trim($_POST['support_email'] ?? '');
        $phone = trim($_POST['support_phone'] ?? '');
        if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Please enter a valid email address.';
        } else {
            setSiteSetting('support_email', $email);
            setSiteSetting('support_phone', $phone);
            flashMessage('success', 'Contact info saved!');
            redirect(SITE_URL . '/admin/settings.php');
        }
    }

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

$currentCols          = (int)getSiteSetting('event_columns', 3);
$currentTicketCols    = (int)getSiteSetting('ticket_columns', 2);
$currentEmail         = getSiteSetting('support_email', SUPPORT_EMAIL);
$currentPhone         = getSiteSetting('support_phone', SUPPORT_PHONE);
$currentTicketMessage = getSiteSetting('ticket_pickup_message', '');
$currentSgKey         = getSiteSetting('sendgrid_api_key', '');
$currentMailFrom      = getSiteSetting('mail_from_address', MAIL_FROM_ADDRESS);
$currentMailName      = getSiteSetting('mail_from_name',    MAIL_FROM_NAME);
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

  <!-- Ticket Pickup Message -->
  <div class="col-12">
    <div class="card shadow-sm">
      <div class="card-header fw-bold"><i class="bi bi-info-circle me-1"></i>Ticket Pickup / Confirmation Message</div>
      <div class="card-body">
        <form method="post">
          <?= csrfField() ?>
          <input type="hidden" name="action" value="ticket_message">
          <div class="mb-3">
            <label class="form-label fw-semibold small" for="ticket_pickup_message">Message</label>
            <textarea name="ticket_pickup_message" id="ticket_pickup_message"
                      class="form-control" rows="3"
                      placeholder="e.g. Your tickets will be available at the far left window…"><?= e($currentTicketMessage) ?></textarea>
            <div class="form-text">Shown on the order confirmation page and included in the ticket email. Leave blank to hide.</div>
          </div>
          <button type="submit" class="btn btn-primary btn-sm">
            <i class="bi bi-save me-1"></i>Save
          </button>
        </form>
      </div>
    </div>
  </div>

  <!-- Contact Info -->
  <div class="col-md-6">
    <div class="card shadow-sm">
      <div class="card-header fw-bold"><i class="bi bi-telephone me-1"></i>Footer Contact Info</div>
      <div class="card-body">
        <form method="post">
          <?= csrfField() ?>
          <input type="hidden" name="action" value="contact">
          <div class="mb-3">
            <label class="form-label fw-semibold small" for="support_email">Support Email</label>
            <input type="email" name="support_email" id="support_email"
                   class="form-control form-control-sm"
                   value="<?= e($currentEmail) ?>"
                   placeholder="support@example.com">
          </div>
          <div class="mb-3">
            <label class="form-label fw-semibold small" for="support_phone">Support Phone</label>
            <input type="text" name="support_phone" id="support_phone"
                   class="form-control form-control-sm"
                   value="<?= e($currentPhone) ?>"
                   placeholder="(555) 000-0000">
            <div class="form-text">Leave phone blank to hide it from the footer.</div>
          </div>
          <button type="submit" class="btn btn-primary btn-sm">
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

  <!-- Mail / SendGrid -->
  <div class="col-12">
    <div class="card shadow-sm">
      <div class="card-header fw-bold"><i class="bi bi-envelope-at me-1"></i>Mail / SendGrid Settings</div>
      <div class="card-body">
        <p class="small text-muted mb-3">
          These settings are stored in <code>storage/settings.json</code> and survive git pulls.
          Leave the API key blank to fall back to the value in <code>config/mail.php</code>.
        </p>
        <form method="post">
          <?= csrfField() ?>
          <input type="hidden" name="action" value="mail">
          <div class="row g-3">
            <div class="col-md-6">
              <label class="form-label fw-semibold small" for="sendgrid_api_key">SendGrid API Key</label>
              <input type="text" name="sendgrid_api_key" id="sendgrid_api_key"
                     class="form-control form-control-sm font-monospace"
                     value="<?= e($currentSgKey) ?>"
                     placeholder="SG.xxxxxxxxxxxxxxxxxxxxxx">
              <div class="form-text">
                <?php if ($currentSgKey && strncmp($currentSgKey, 'SG.', 3) === 0): ?>
                  <span class="text-success"><i class="bi bi-check-circle me-1"></i>Key saved — starts with <?= e(substr($currentSgKey, 0, 10)) ?>…</span>
                <?php elseif (defined('SENDGRID_API_KEY') && SENDGRID_API_KEY !== 'SG.REPLACE_ME'): ?>
                  <span class="text-warning"><i class="bi bi-exclamation-circle me-1"></i>Using key from config/mail.php (<?= e(substr(SENDGRID_API_KEY, 0, 10)) ?>…)</span>
                <?php else: ?>
                  <span class="text-danger"><i class="bi bi-x-circle me-1"></i>No key configured — email will fail</span>
                <?php endif; ?>
              </div>
            </div>
            <div class="col-md-3">
              <label class="form-label fw-semibold small" for="mail_from_address">From Address</label>
              <input type="email" name="mail_from_address" id="mail_from_address"
                     class="form-control form-control-sm"
                     value="<?= e($currentMailFrom) ?>"
                     placeholder="tickets@yourdomain.com">
            </div>
            <div class="col-md-3">
              <label class="form-label fw-semibold small" for="mail_from_name">From Name</label>
              <input type="text" name="mail_from_name" id="mail_from_name"
                     class="form-control form-control-sm"
                     value="<?= e($currentMailName) ?>"
                     placeholder="<?= e(SITE_NAME) ?>">
            </div>
          </div>
          <button type="submit" class="btn btn-primary btn-sm mt-3">
            <i class="bi bi-save me-1"></i>Save Mail Settings
          </button>
        </form>
      </div>
    </div>
  </div>

</div>

<?php require_once __DIR__ . '/includes/admin-footer.php'; ?>
