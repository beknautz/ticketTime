<?php
require_once dirname(__DIR__) . '/config/config.php';
require_once BASE_PATH . '/includes/auth.php';

requireAdmin(['admin', 'scanner']);

$eventModel = new Event();
$events     = $eventModel->getAll();

$selectedEvent = (int)($_SESSION['scan_event_id'] ?? 0);
if (isset($_GET['event_id'])) {
    $selectedEvent = (int)$_GET['event_id'];
    $_SESSION['scan_event_id'] = $selectedEvent;
}

$pageTitle = 'Gate Scanner';
$bodyClass = 'scan-page';
$extraHead = '<meta name="csrf-token" content="' . e(csrfToken()) . '">';
require_once BASE_PATH . '/includes/header.php';
?>
<!-- Minimal nav for scan page -->
<nav class="navbar navbar-dark bg-dark">
  <div class="container-fluid">
    <span class="navbar-brand fw-bold"><i class="bi bi-qr-code-scan me-1"></i>Gate Scanner</span>
    <div class="d-flex gap-2 align-items-center">
      <span class="text-light small"><?= e(currentAdminName()) ?></span>
      <a href="<?= SITE_URL ?>/admin/logout.php" class="btn btn-sm btn-outline-light">Logout</a>
    </div>
  </div>
</nav>

<div class="container-fluid py-3">

  <!-- Scan Result Overlay -->
  <div id="scanResultOverlay" class="scan-result-overlay" style="display:none">
    <i class="scan-result-icon bi bi-question-circle-fill" id="scanResultIcon"></i>
    <div class="scan-result-text" id="scanResultText"></div>
    <div class="scan-result-detail" id="scanResultDetail"></div>
    <button class="btn btn-outline-light mt-4" onclick="ScanUI.hide()">
      <i class="bi bi-x-lg me-1"></i>Dismiss
    </button>
  </div>

  <!-- Event Selector -->
  <div class="row justify-content-center mb-3">
    <div class="col-md-6">
      <form method="get" class="d-flex gap-2">
        <select name="event_id" class="form-select" onchange="this.form.submit()">
          <option value="">— Select Event —</option>
          <?php foreach ($events as $ev): ?>
            <option value="<?= (int)$ev['event_id'] ?>"
                    <?= $selectedEvent === (int)$ev['event_id'] ? 'selected' : '' ?>>
              <?= e($ev['event_name']) ?> (<?= formatDate($ev['event_start'], 'M j, Y') ?>)
            </option>
          <?php endforeach; ?>
        </select>
      </form>
    </div>
  </div>

  <?php if (!$selectedEvent): ?>
    <div class="alert alert-info text-center">
      <i class="bi bi-info-circle me-1"></i>Please select an event above to begin scanning.
    </div>
  <?php else: ?>
    <div class="row g-3">
      <!-- Camera Scanner -->
      <div class="col-md-6">
        <div class="card shadow-sm">
          <div class="card-header fw-bold d-flex justify-content-between align-items-center">
            <span><i class="bi bi-camera me-1"></i>Camera Scan</span>
            <div class="form-check form-switch mb-0">
              <input class="form-check-input" type="checkbox" id="cameraToggle">
              <label class="form-check-label small" for="cameraToggle">Enable Camera</label>
            </div>
          </div>
          <div class="card-body text-center">
            <div id="cameraContainer" style="display:none">
              <div class="scanner-frame d-inline-block mb-3">
                <video id="scannerVideo" autoplay muted playsinline></video>
              </div>
              <canvas id="scannerCanvas" style="display:none"></canvas>
              <p class="text-muted small">Hold ticket QR code in front of camera</p>
            </div>
            <div id="cameraOff" class="py-4 text-muted">
              <i class="bi bi-camera-video-off display-4"></i>
              <p class="mt-2">Toggle switch above to activate camera</p>
            </div>
          </div>
        </div>
      </div>

      <!-- Manual Scan / Type -->
      <div class="col-md-6">
        <div class="card shadow-sm">
          <div class="card-header fw-bold"><i class="bi bi-keyboard me-1"></i>Manual Entry</div>
          <div class="card-body">
            <form id="manualScanForm">
              <?= csrfField() ?>
              <input type="hidden" name="event_id" value="<?= $selectedEvent ?>">
              <div class="mb-3">
                <label class="form-label fw-semibold">Ticket Code / QR Token</label>
                <input type="text" name="qr_token" id="manualToken" class="form-control form-control-lg"
                       placeholder="Scan or type ticket code..." autocomplete="off"
                       autofocus>
              </div>
              <div class="mb-3">
                <label class="form-label">Scan Location (optional)</label>
                <input type="text" name="scan_location" class="form-control" placeholder="e.g. Main Gate">
              </div>
              <button type="submit" class="btn btn-primary btn-lg w-100">
                <i class="bi bi-search me-1"></i>Check Ticket
              </button>
            </form>

            <div id="scanResult" class="mt-3"></div>
          </div>
        </div>

        <!-- Recent scans -->
        <div class="card shadow-sm mt-3">
          <div class="card-header fw-bold d-flex justify-content-between">
            <span><i class="bi bi-clock-history me-1"></i>Recent Scans</span>
            <button class="btn btn-sm btn-outline-secondary"
                    hx-get="<?= SITE_URL ?>/actions/scan-log.php?event_id=<?= $selectedEvent ?>"
                    hx-target="#recentScans"
                    hx-swap="innerHTML">
              <i class="bi bi-arrow-clockwise"></i>
            </button>
          </div>
          <div class="card-body p-0">
            <div id="recentScans" style="max-height:300px;overflow-y:auto">
              <div class="text-center py-3 text-muted small">Scanning activity will appear here</div>
            </div>
          </div>
        </div>
      </div>
    </div>
  <?php endif; ?>
</div>

<script>
const SCAN_EVENT_ID = <?= $selectedEvent ?: 'null' ?>;
const SCAN_URL = '<?= SITE_URL ?>/actions/scan-ticket.php';
const CSRF_TOKEN = document.querySelector('meta[name="csrf-token"]').content;

// ── Manual form submission ──────────────────────────────────────────────────
document.getElementById('manualScanForm')?.addEventListener('submit', async function(e) {
  e.preventDefault();
  const token = document.getElementById('manualToken').value.trim();
  if (!token) return;
  await processScan(token, document.querySelector('[name="scan_location"]').value);
  document.getElementById('manualToken').value = '';
  document.getElementById('manualToken').focus();
});

// ── Process scan ───────────────────────────────────────────────────────────
async function processScan(qrToken, location) {
  if (!SCAN_EVENT_ID) return;

  try {
    const fd = new FormData();
    fd.append('qr_token', qrToken);
    fd.append('event_id', SCAN_EVENT_ID);
    fd.append('scan_location', location || '');
    fd.append('csrf_token', CSRF_TOKEN);

    const resp = await fetch(SCAN_URL, { method: 'POST', body: fd });
    const data = await resp.json();

    // Big overlay
    const detail = data.ticket
      ? (data.ticket.ticket_name + ' | ' + (data.ticket.customer_first_name || '') + ' ' + (data.ticket.customer_last_name || ''))
      : '';
    ScanUI.show(data.result, data.message, detail);

    // Update inline result
    const resultEl = document.getElementById('scanResult');
    if (resultEl) {
      const colors = { valid:'success', already_used:'warning', invalid:'danger', void:'danger', refunded:'danger', wrong_event:'danger' };
      resultEl.innerHTML = `<div class="alert alert-${colors[data.result] || 'secondary'}">${data.message}</div>`;
    }

    // Refresh recent scans
    htmx.ajax('GET', '<?= SITE_URL ?>/actions/scan-log.php?event_id=' + SCAN_EVENT_ID, {
      target: '#recentScans', swap: 'innerHTML'
    });

  } catch(err) {
    ScanUI.show('invalid', 'Scan Error', 'Network error - please retry');
  }
}

// ── Camera scanner ─────────────────────────────────────────────────────────
let cameraStream = null;
let scanInterval = null;
let lastScannedToken = null;
let scanCooldown = false;

document.getElementById('cameraToggle')?.addEventListener('change', async function() {
  const container = document.getElementById('cameraContainer');
  const off       = document.getElementById('cameraOff');

  if (this.checked) {
    container.style.display = 'block';
    off.style.display = 'none';
    await startCamera();
  } else {
    container.style.display = 'none';
    off.style.display = 'block';
    stopCamera();
  }
});

async function startCamera() {
  const video = document.getElementById('scannerVideo');
  try {
    cameraStream = await navigator.mediaDevices.getUserMedia({
      video: { facingMode: 'environment', width: { ideal: 640 }, height: { ideal: 480 } }
    });
    video.srcObject = cameraStream;
    video.play();

    // Start frame scanning (requires jsQR or similar)
    if (typeof jsQR !== 'undefined') {
      const canvas = document.getElementById('scannerCanvas');
      const ctx    = canvas.getContext('2d');
      scanInterval = setInterval(() => {
        if (video.readyState !== video.HAVE_ENOUGH_DATA) return;
        canvas.width  = video.videoWidth;
        canvas.height = video.videoHeight;
        ctx.drawImage(video, 0, 0, canvas.width, canvas.height);
        const imageData = ctx.getImageData(0, 0, canvas.width, canvas.height);
        const code = jsQR(imageData.data, imageData.width, imageData.height);
        if (code && code.data && !scanCooldown) {
          const token = code.data.split('token=')[1] || code.data;
          if (token && token !== lastScannedToken) {
            lastScannedToken = token;
            scanCooldown = true;
            processScan(token, '').finally(() => {
              setTimeout(() => {
                scanCooldown = false;
                lastScannedToken = null;
              }, 3000);
            });
          }
        }
      }, 250);
    }
  } catch (err) {
    alert('Camera access denied: ' + err.message);
    document.getElementById('cameraToggle').checked = false;
    document.getElementById('cameraContainer').style.display = 'none';
    document.getElementById('cameraOff').style.display = 'block';
  }
}

function stopCamera() {
  clearInterval(scanInterval);
  if (cameraStream) {
    cameraStream.getTracks().forEach(t => t.stop());
    cameraStream = null;
  }
}
</script>

<!-- Optional: jsQR for camera decoding -->
<script src="https://cdn.jsdelivr.net/npm/jsqr@1.4.0/dist/jsQR.min.js"></script>

<?php require_once BASE_PATH . '/includes/footer.php'; ?>
