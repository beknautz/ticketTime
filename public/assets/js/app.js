/* TicketTime - Application JavaScript */

'use strict';

// ── HTMX global config ─────────────────────────────────────────────────────
document.addEventListener('DOMContentLoaded', function () {
  // Include CSRF token in all HTMX requests
  const csrfMeta = document.querySelector('meta[name="csrf-token"]');
  if (csrfMeta) {
    document.body.addEventListener('htmx:configRequest', function (evt) {
      evt.detail.headers['X-CSRF-Token'] = csrfMeta.getAttribute('content');
    });
  }

  // Scroll to response div after HTMX swap
  document.body.addEventListener('htmx:afterSwap', function (evt) {
    const target = evt.detail.target;
    if (target && target.offsetHeight > 0) {
      target.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
    }
  });
});

// ── Cart quantity controls ─────────────────────────────────────────────────
window.CartQty = {
  adjust: function (typeId, delta) {
    const input = document.getElementById('qty-' + typeId);
    if (!input) return;
    const max = parseInt(input.getAttribute('max') || '10', 10);
    let   val = parseInt(input.value, 10) + delta;
    val = Math.max(0, Math.min(max, val));
    input.value = val;
    document.getElementById('qty-display-' + typeId).textContent = val;
    CartQty.updateAddButton(typeId, val, max);
  },

  updateAddButton: function (typeId, qty, max) {
    const btn = document.getElementById('add-btn-' + typeId);
    if (btn) {
      btn.disabled = (qty === 0);
    }
  },

  set: function (typeId, val) {
    const input = document.getElementById('qty-' + typeId);
    if (input) {
      const max = parseInt(input.getAttribute('max') || '10', 10);
      input.value = Math.max(0, Math.min(max, parseInt(val, 10)));
      document.getElementById('qty-display-' + typeId).textContent = input.value;
    }
  }
};

// ── Scan result overlay ────────────────────────────────────────────────────
window.ScanUI = {
  autoResetMs: 3000,

  show: function (result, message, detail) {
    const overlay = document.getElementById('scanResultOverlay');
    const icon    = document.getElementById('scanResultIcon');
    const text    = document.getElementById('scanResultText');
    const det     = document.getElementById('scanResultDetail');

    if (!overlay) return;

    // Set classes
    overlay.className = 'scan-result-overlay ' + result;
    overlay.style.display = 'flex';

    // Set icon
    const icons = {
      valid:       'bi-check-circle-fill',
      already_used:'bi-exclamation-triangle-fill',
      invalid:     'bi-x-circle-fill',
      void:        'bi-x-circle-fill',
      refunded:    'bi-x-circle-fill',
      wrong_event: 'bi-x-circle-fill',
    };
    icon.className = 'scan-result-icon bi ' + (icons[result] || 'bi-question-circle-fill');
    text.textContent = message;
    if (det) det.textContent = detail || '';

    // Vibrate if supported
    if (navigator.vibrate) {
      navigator.vibrate(result === 'valid' ? [100] : [100, 50, 100]);
    }

    // Auto reset
    clearTimeout(ScanUI._timer);
    ScanUI._timer = setTimeout(ScanUI.hide, ScanUI.autoResetMs);
  },

  hide: function () {
    const overlay = document.getElementById('scanResultOverlay');
    if (overlay) overlay.style.display = 'none';
  }
};

// ── Payment processing UI ──────────────────────────────────────────────────
window.PaymentUI = {
  disableSubmit: function () {
    const btn = document.getElementById('payBtn');
    if (btn) {
      btn.disabled = true;
      btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Processing...';
    }
  },

  enableSubmit: function () {
    const btn = document.getElementById('payBtn');
    if (btn) {
      btn.disabled = false;
      btn.innerHTML = btn.getAttribute('data-original') || 'Pay Now';
    }
  },

  showError: function (message) {
    const el = document.getElementById('paymentError');
    if (el) {
      el.textContent = message;
      el.style.display = 'block';
    }
    PaymentUI.enableSubmit();
  }
};

// ── Flash auto-dismiss ─────────────────────────────────────────────────────
document.addEventListener('DOMContentLoaded', function () {
  const alerts = document.querySelectorAll('.alert-auto-dismiss');
  alerts.forEach(function (el) {
    setTimeout(function () {
      const bsAlert = bootstrap.Alert.getOrCreateInstance(el);
      bsAlert.close();
    }, 5000);
  });
});
