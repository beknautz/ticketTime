<?php
require_once dirname(__DIR__) . '/config/config.php';
require_once BASE_PATH . '/includes/auth.php';

requireAdmin(['admin', 'scanner']);

$eventModel    = new Event();
$events        = $eventModel->getAll();
$selectedEvent = (int)($_SESSION['scan_event_id'] ?? 0);

if (isset($_GET['event_id'])) {
    $selectedEvent = (int)$_GET['event_id'];
    $_SESSION['scan_event_id'] = $selectedEvent;
}

$selectedEventName = '';
foreach ($events as $ev) {
    if ((int)$ev['event_id'] === $selectedEvent) {
        $selectedEventName = $ev['event_name'];
        break;
    }
}

$pageTitle = 'Gate Scanner';
$bodyClass = 'scan-page';
$extraHead = '<meta name="csrf-token" content="' . e(csrfToken()) . '">';
require_once BASE_PATH . '/includes/header.php';
?>

<!-- ── Scanner Nav ─────────────────────────────────────────────────────── -->
<nav class="navbar navbar-dark bg-dark">
  <div class="container-fluid px-3">
    <span class="navbar-brand fw-bold fs-6 mb-0">
      <i class="bi bi-qr-code-scan me-1"></i>Gate Scanner
    </span>
    <div class="d-flex align-items-center gap-2">
      <span class="text-light small d-none d-sm-inline"><?= e(currentAdminName()) ?></span>
      <a href="<?= SITE_URL ?>/admin/logout.php" class="btn btn-sm btn-outline-light">
        <i class="bi bi-box-arrow-right"></i>
      </a>
    </div>
  </div>
</nav>

<!-- ── Full-screen scan result overlay ────────────────────────────────── -->
<div id="scanResultOverlay" class="scan-result-overlay" style="display:none">
  <i class="scan-result-icon bi bi-question-circle-fill" id="scanResultIcon"></i>
  <div class="scan-result-text"  id="scanResultText"></div>
  <div class="scan-result-detail" id="scanResultDetail"></div>
  <button class="btn btn-outline-light mt-4 px-5" onclick="ScanUI.hide()">
    <i class="bi bi-x-lg me-1"></i>Dismiss
  </button>
</div>

<!-- ── Event picker (shown when no event selected) ────────────────────── -->
<?php if (!$selectedEvent): ?>
<div class="container py-5">
  <div class="row justify-content-center">
    <div class="col-sm-8 col-md-5">
      <div class="card shadow-sm">
        <div class="card-header fw-bold text-center">
          <i class="bi bi-calendar-event me-1"></i>Select Event to Scan
        </div>
        <div class="card-body">
          <form method="get">
            <select name="event_id" class="form-select form-select-lg mb-3"
                    onchange="this.form.submit()">
              <option value="">— Choose an event —</option>
              <?php foreach ($events as $ev): ?>
                <option value="<?= (int)$ev['event_id'] ?>">
                  <?= e($ev['event_name']) ?> &mdash; <?= formatDate($ev['event_start'], 'M j, Y') ?>
                </option>
              <?php endforeach; ?>
            </select>
            <button type="submit" class="btn btn-primary w-100 btn-lg">
              <i class="bi bi-qr-code-scan me-1"></i>Start Scanning
            </button>
          </form>
        </div>
      </div>
    </div>
  </div>
</div>

<?php else: ?>

<!-- ══════════════════════════════════════════════════════════════════════
     MOBILE LAYOUT  (< md)  — full-screen camera
     ══════════════════════════════════════════════════════════════════════ -->
<div class="d-md-none scanner-mobile">

  <!-- Camera viewport -->
  <div id="mCameraWrap" class="scanner-mobile-camera">
    <video id="mVideo" autoplay muted playsinline></video>
    <canvas id="mCanvas" style="display:none"></canvas>

    <!-- Targeting frame -->
    <div class="scanner-target-frame">
      <div class="corner tl"></div><div class="corner tr"></div>
      <div class="corner bl"></div><div class="corner br"></div>
      <div class="scanner-beam"></div>
    </div>

    <!-- Event badge -->
    <div class="scanner-event-badge">
      <i class="bi bi-calendar-event me-1"></i>
      <span><?= e($selectedEventName) ?></span>
      <a href="<?= SITE_URL ?>/public/scan.php" class="ms-2 text-white opacity-75">
        <i class="bi bi-pencil-square"></i>
      </a>
    </div>

    <!-- Status indicator -->
    <div id="mStatus" class="scanner-status">
      <span class="spinner-border spinner-border-sm me-1"></span> Starting camera…
    </div>
  </div>

  <!-- Bottom bar -->
  <div class="scanner-mobile-bar">
    <button class="btn btn-outline-light btn-sm"
            data-bs-toggle="modal" data-bs-target="#manualModal">
      <i class="bi bi-keyboard me-1"></i>Manual Entry
    </button>
    <div class="text-white text-center small" id="mScanCount">Ready</div>
    <button class="btn btn-outline-light btn-sm"
            data-bs-toggle="modal" data-bs-target="#recentModal">
      <i class="bi bi-clock-history me-1"></i>Recent
    </button>
  </div>
</div>

<!-- ══════════════════════════════════════════════════════════════════════
     DESKTOP LAYOUT  (≥ md)  — two-column cards
     ══════════════════════════════════════════════════════════════════════ -->
<div class="d-none d-md-block container-fluid py-3">

  <!-- Event / change bar -->
  <div class="d-flex justify-content-between align-items-center mb-3">
    <h5 class="mb-0 fw-bold">
      <i class="bi bi-calendar-event me-1 text-primary"></i><?= e($selectedEventName) ?>
    </h5>
    <a href="<?= SITE_URL ?>/public/scan.php" class="btn btn-outline-secondary btn-sm">
      <i class="bi bi-arrow-left me-1"></i>Change Event
    </a>
  </div>

  <div class="row g-3">
    <!-- Camera column -->
    <div class="col-md-6">
      <div class="card shadow-sm">
        <div class="card-header fw-bold d-flex justify-content-between align-items-center">
          <span><i class="bi bi-camera me-1"></i>Camera Scan</span>
          <div class="form-check form-switch mb-0">
            <input class="form-check-input" type="checkbox" id="dCameraToggle">
            <label class="form-check-label small" for="dCameraToggle">Enable Camera</label>
          </div>
        </div>
        <div class="card-body text-center p-2">
          <div id="dCameraOn" style="display:none;position:relative;">
            <video id="dVideo" autoplay muted playsinline
                   style="width:100%;max-height:360px;border-radius:8px;background:#000;"></video>
            <canvas id="dCanvas" style="display:none"></canvas>
            <div id="dStatus" class="text-muted small mt-2"></div>
          </div>
          <div id="dCameraOff" class="py-5 text-muted">
            <i class="bi bi-camera-video-off display-4"></i>
            <p class="mt-2 mb-0">Toggle switch to activate camera</p>
          </div>
        </div>
      </div>
    </div>

    <!-- Manual entry + recent scans column -->
    <div class="col-md-6">
      <div class="card shadow-sm mb-3">
        <div class="card-header fw-bold"><i class="bi bi-keyboard me-1"></i>Manual Entry</div>
        <div class="card-body">
          <form id="dManualForm">
            <input type="hidden" name="event_id" value="<?= $selectedEvent ?>">
            <div class="mb-3">
              <label class="form-label fw-semibold">Ticket Code or QR Token</label>
              <input type="text" name="qr_token" id="dManualToken"
                     class="form-control form-control-lg font-monospace"
                     placeholder="Scan or type…" autocomplete="off" autofocus>
            </div>
            <div class="mb-3">
              <label class="form-label">Scan Location <span class="text-muted small">(optional)</span></label>
              <input type="text" name="scan_location" id="dScanLocation"
                     class="form-control" placeholder="e.g. Main Gate">
            </div>
            <button type="submit" class="btn btn-primary btn-lg w-100">
              <i class="bi bi-search me-1"></i>Check Ticket
            </button>
          </form>
          <div id="dScanResult" class="mt-3"></div>
        </div>
      </div>

      <div class="card shadow-sm">
        <div class="card-header fw-bold d-flex justify-content-between align-items-center">
          <span><i class="bi bi-clock-history me-1"></i>Recent Scans</span>
          <button class="btn btn-sm btn-outline-secondary"
                  hx-get="<?= SITE_URL ?>/actions/scan-log.php?event_id=<?= $selectedEvent ?>"
                  hx-target="#dRecentScans" hx-swap="innerHTML">
            <i class="bi bi-arrow-clockwise"></i>
          </button>
        </div>
        <div class="card-body p-0">
          <div id="dRecentScans" style="max-height:280px;overflow-y:auto">
            <div class="text-center py-3 text-muted small">Scan activity will appear here</div>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- ── Manual Entry Modal (mobile) ─────────────────────────────────────── -->
<div class="modal fade" id="manualModal" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title fw-bold"><i class="bi bi-keyboard me-2"></i>Manual Entry</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <form id="mManualForm">
          <input type="hidden" name="event_id" value="<?= $selectedEvent ?>">
          <div class="mb-3">
            <label class="form-label fw-semibold">Ticket Code or QR Token</label>
            <input type="text" name="qr_token" id="mManualToken"
                   class="form-control form-control-lg font-monospace"
                   placeholder="Type or paste code…" autocomplete="off">
          </div>
          <div class="mb-3">
            <label class="form-label">Scan Location <span class="text-muted small">(optional)</span></label>
            <input type="text" name="scan_location" id="mScanLocation"
                   class="form-control" placeholder="e.g. Main Gate">
          </div>
          <button type="submit" class="btn btn-primary btn-lg w-100">
            <i class="bi bi-search me-1"></i>Check Ticket
          </button>
        </form>
        <div id="mManualResult" class="mt-3"></div>
      </div>
    </div>
  </div>
</div>

<!-- ── Recent Scans Modal (mobile) ─────────────────────────────────────── -->
<div class="modal fade" id="recentModal" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered modal-fullscreen-sm-down">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title fw-bold"><i class="bi bi-clock-history me-2"></i>Recent Scans</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body p-0" style="overflow-y:auto;max-height:70vh">
        <div id="mRecentScans"
             hx-get="<?= SITE_URL ?>/actions/scan-log.php?event_id=<?= $selectedEvent ?>"
             hx-trigger="revealed"
             hx-swap="innerHTML">
          <div class="text-center py-4 text-muted small">Loading…</div>
        </div>
      </div>
    </div>
  </div>
</div>

<?php endif; ?>

<script>
const SCAN_EVENT_ID  = <?= $selectedEvent ?: 'null' ?>;
const SCAN_URL       = '<?= SITE_URL ?>/actions/scan-ticket.php';
const CSRF_TOKEN     = document.querySelector('meta[name="csrf-token"]')?.content || '';
const IS_MOBILE      = window.innerWidth < 768;

let cameraStream  = null;
let scanCooldown  = false;
let lastToken     = null;
let scanCount     = 0;
let barcodeDetector = null;
let scanLoopActive  = false;

// ── Init BarcodeDetector (native API, no library needed on modern iOS/Android)
if ('BarcodeDetector' in window) {
  BarcodeDetector.getSupportedFormats().then(formats => {
    if (formats.includes('qr_code')) {
      barcodeDetector = new BarcodeDetector({ formats: ['qr_code'] });
    }
  }).catch(() => {});
}

// ── Submit helpers ────────────────────────────────────────────────────────
async function processScan(token, location) {
  if (!SCAN_EVENT_ID || !token) return;
  token = decodeQRUrl(token);
  if (!token || token === lastToken) return;

  try {
    const fd = new FormData();
    fd.append('qr_token',      token);
    fd.append('event_id',      SCAN_EVENT_ID);
    fd.append('scan_location', location || '');
    fd.append('csrf_token',    CSRF_TOKEN);

    const resp = await fetch(SCAN_URL, { method: 'POST', body: fd });
    const data = await resp.json();

    const detail = data.ticket
      ? (data.ticket.ticket_name + ' — ' + (data.ticket.customer_first_name || '') + ' ' + (data.ticket.customer_last_name || '')).trim()
      : '';

    ScanUI.show(data.result, data.message, detail);

    // Update inline result areas (desktop)
    const el = document.getElementById('dScanResult');
    if (el) {
      const color = { valid:'success', already_used:'warning', invalid:'danger', void:'danger', refunded:'danger', wrong_event:'danger' }[data.result] || 'secondary';
      el.innerHTML = `<div class="alert alert-${color} mb-0">${data.message}</div>`;
    }
    const mel = document.getElementById('mManualResult');
    if (mel) {
      const color = { valid:'success', already_used:'warning', invalid:'danger' }[data.result] || 'danger';
      mel.innerHTML = `<div class="alert alert-${color} mb-0">${data.message}</div>`;
    }

    // Refresh recent scans (desktop)
    if (typeof htmx !== 'undefined') {
      htmx.ajax('GET', '<?= SITE_URL ?>/actions/scan-log.php?event_id=' + SCAN_EVENT_ID, { target: '#dRecentScans', swap: 'innerHTML' });
    }

    // Mobile scan counter
    if (data.result === 'valid') {
      scanCount++;
      const c = document.getElementById('mScanCount');
      if (c) c.textContent = scanCount + ' scanned today';
    }

    // Cooldown
    lastToken    = token;
    scanCooldown = true;
    setTimeout(() => { scanCooldown = false; lastToken = null; }, 3000);

  } catch (err) {
    ScanUI.show('invalid', 'Network Error', 'Check connection and retry');
  }
}

function decodeQRUrl(raw) {
  if (raw.includes('token=')) {
    try {
      const qs = new URL(raw).searchParams;
      return qs.get('token') || raw;
    } catch (_) {
      const m = raw.match(/[?&]token=([^&]+)/);
      return m ? decodeURIComponent(m[1]) : raw;
    }
  }
  return raw;
}

// ── Camera ────────────────────────────────────────────────────────────────
async function startCamera(videoEl, statusEl) {
  try {
    cameraStream = await navigator.mediaDevices.getUserMedia({
      video: { facingMode: 'environment', width: { ideal: 1280 }, height: { ideal: 720 } }
    });
    videoEl.srcObject = cameraStream;
    await videoEl.play();
    if (statusEl) statusEl.textContent = 'Point camera at QR code';
    startScanLoop(videoEl);
  } catch (err) {
    if (statusEl) statusEl.textContent = 'Camera unavailable — use Manual Entry';
    console.warn('Camera error:', err.message);
  }
}

function stopCamera() {
  scanLoopActive = false;
  if (cameraStream) {
    cameraStream.getTracks().forEach(t => t.stop());
    cameraStream = null;
  }
}

function startScanLoop(videoEl) {
  scanLoopActive = true;
  const canvas   = document.getElementById('mCanvas') || document.getElementById('dCanvas');
  const ctx      = canvas ? canvas.getContext('2d') : null;

  async function tick() {
    if (!scanLoopActive || !cameraStream) return;

    if (!scanCooldown && videoEl.readyState >= videoEl.HAVE_ENOUGH_DATA) {
      let decoded = null;

      if (barcodeDetector) {
        try {
          const codes = await barcodeDetector.detect(videoEl);
          decoded = codes.length ? codes[0].rawValue : null;
        } catch (_) {}
      } else if (typeof jsQR !== 'undefined' && ctx) {
        canvas.width  = videoEl.videoWidth;
        canvas.height = videoEl.videoHeight;
        ctx.drawImage(videoEl, 0, 0, canvas.width, canvas.height);
        const img  = ctx.getImageData(0, 0, canvas.width, canvas.height);
        const code = jsQR(img.data, img.width, img.height);
        decoded = code ? code.data : null;
      }

      if (decoded) {
        const loc = document.getElementById('mScanLocation')?.value
                 || document.getElementById('dScanLocation')?.value || '';
        processScan(decoded, loc);
      }
    }

    setTimeout(tick, 250);
  }
  tick();
}

// ── Mobile: auto-start camera on load ────────────────────────────────────
if (IS_MOBILE && SCAN_EVENT_ID) {
  const video     = document.getElementById('mVideo');
  const statusEl  = document.getElementById('mStatus');
  if (video) startCamera(video, statusEl);
}

// ── Desktop: camera toggle ────────────────────────────────────────────────
document.getElementById('dCameraToggle')?.addEventListener('change', function () {
  const video = document.getElementById('dVideo');
  const on    = document.getElementById('dCameraOn');
  const off   = document.getElementById('dCameraOff');
  const status = document.getElementById('dStatus');
  if (this.checked) {
    on.style.display  = 'block';
    off.style.display = 'none';
    startCamera(video, status);
  } else {
    on.style.display  = 'none';
    off.style.display = 'block';
    stopCamera();
  }
});

// ── Manual forms ──────────────────────────────────────────────────────────
['dManualForm', 'mManualForm'].forEach(id => {
  document.getElementById(id)?.addEventListener('submit', async function (e) {
    e.preventDefault();
    const tokenInput = this.querySelector('[name="qr_token"]');
    const locInput   = this.querySelector('[name="scan_location"]');
    const token = tokenInput?.value.trim();
    if (!token) return;
    await processScan(token, locInput?.value || '');
    tokenInput.value = '';
    tokenInput.focus();
  });
});
</script>

<!-- jsQR fallback for older devices without BarcodeDetector -->
<script src="https://cdn.jsdelivr.net/npm/jsqr@1.4.0/dist/jsQR.min.js" async></script>

<?php require_once BASE_PATH . '/includes/footer.php'; ?>
