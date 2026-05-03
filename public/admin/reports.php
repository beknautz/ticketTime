<?php
require_once dirname(__DIR__, 2) . '/config/config.php';
require_once BASE_PATH . '/includes/auth.php';

requireAdmin(['admin']);

$db         = Database::getInstance();
$eventModel = new Event();
$eventId    = (int)($_GET['event_id'] ?? 0);
$export     = $_GET['export'] ?? '';

// ── CSV EXPORT ──────────────────────────────────────────────────────────────
if (in_array($export, ['orders', 'tickets', 'scans'])) {

    $filename = $export . '-' . date('Y-m-d') . '.csv';
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="' . $filename . '"');

    $out = fopen('php://output', 'w');

    if ($export === 'orders') {
        fputcsv($out, ['Order ID','First','Last','Email','Phone','Event','Status','Total','Paid At','Created At']);
        $stmt = $db->prepare("
            SELECT o.public_order_id, o.customer_first_name, o.customer_last_name, o.customer_email,
                   o.customer_phone, e.event_name, o.status, o.total, o.paid_at, o.created_at
            FROM orders o JOIN events e ON e.event_id = o.event_id
            " . ($eventId ? "WHERE o.event_id = :eid" : "") . "
            ORDER BY o.created_at DESC
        ");
        if ($eventId) $stmt->bindValue('eid', $eventId, PDO::PARAM_INT);
        $stmt->execute();
        while ($row = $stmt->fetch()) fputcsv($out, $row);

    } elseif ($export === 'tickets') {
        fputcsv($out, ['Ticket Code','QR Token','Order ID','Ticket Type','Customer','Email','Event','Status','Scanned At','Scan Location']);
        $stmt = $db->prepare("
            SELECT t.ticket_code, t.qr_token, o.public_order_id, t.ticket_name,
                   CONCAT(o.customer_first_name, ' ', o.customer_last_name) as customer_name,
                   o.customer_email, e.event_name, t.status, t.scanned_at, t.scan_location
            FROM tickets t
            JOIN orders o ON o.order_id = t.order_id
            JOIN events e ON e.event_id = t.event_id
            " . ($eventId ? "WHERE t.event_id = :eid" : "") . "
            ORDER BY t.ticket_id ASC
        ");
        if ($eventId) $stmt->bindValue('eid', $eventId, PDO::PARAM_INT);
        $stmt->execute();
        while ($row = $stmt->fetch()) fputcsv($out, $row);

    } elseif ($export === 'scans') {
        fputcsv($out, ['Scanned At','Ticket Code','Result','Message','Customer','Event','Scanner','Location','IP']);
        $stmt = $db->prepare("
            SELECT ts.scanned_at, t.ticket_code, ts.scan_result, ts.scan_message,
                   CONCAT(o.customer_first_name, ' ', o.customer_last_name) as customer,
                   e.event_name, a.name as scanner_name, ts.scan_location, ts.ip_address
            FROM ticket_scans ts
            LEFT JOIN tickets t ON t.ticket_id = ts.ticket_id
            LEFT JOIN orders o ON o.order_id = t.order_id
            LEFT JOIN events e ON e.event_id = t.event_id
            LEFT JOIN admins a ON a.admin_id = ts.scanned_by
            " . ($eventId ? "WHERE t.event_id = :eid" : "") . "
            ORDER BY ts.scanned_at DESC
        ");
        if ($eventId) $stmt->bindValue('eid', $eventId, PDO::PARAM_INT);
        $stmt->execute();
        while ($row = $stmt->fetch()) fputcsv($out, $row);
    }

    fclose($out);
    exit;
}

// ── REPORTS PAGE ────────────────────────────────────────────────────────────
$events = $eventModel->getAll();

// Revenue by event
$revenueByEvent = $db->query("
    SELECT e.event_name, e.event_start,
           COUNT(DISTINCT o.order_id) as orders,
           COALESCE(SUM(CASE WHEN o.status='paid' THEN 1 ELSE 0 END),0) as paid_orders,
           COALESCE(SUM(tt.quantity_sold),0) as tickets_sold,
           COALESCE(SUM(tt.quantity_available),0) as capacity,
           COALESCE(SUM(CASE WHEN o.status='paid' THEN o.total ELSE 0 END),0) as revenue
    FROM events e
    LEFT JOIN ticket_types tt ON tt.event_id = e.event_id
    LEFT JOIN orders o ON o.event_id = e.event_id
    GROUP BY e.event_id
    ORDER BY e.event_start DESC
")->fetchAll();

$pageTitle = 'Reports';
require_once __DIR__ . '/includes/admin-header.php';
?>
<div class="d-flex justify-content-between align-items-center mb-4">
  <h2 class="fw-bold mb-0">Reports &amp; Export</h2>
</div>

<!-- Revenue by Event -->
<div class="card shadow-sm mb-4">
  <div class="card-header fw-bold">Revenue by Event</div>
  <div class="card-body p-0">
    <div class="table-responsive">
      <table class="table table-hover mb-0 align-middle">
        <thead class="table-light">
          <tr>
            <th>Event</th>
            <th>Date</th>
            <th class="text-center">Sold / Cap</th>
            <th class="text-center">Paid Orders</th>
            <th class="text-end">Revenue</th>
            <th>Export</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($revenueByEvent as $ev):
            $cap  = (int)$ev['capacity'];
            $sold = (int)$ev['tickets_sold'];
            $pct  = $cap > 0 ? round($sold / $cap * 100) : 0;
          ?>
            <tr>
              <td class="fw-semibold"><?= e($ev['event_name']) ?></td>
              <td class="text-muted"><?= formatDate($ev['event_start'], 'M j, Y') ?></td>
              <td class="text-center">
                <?= $sold ?> / <?= $cap ?>
                <?php if ($cap > 0): ?>
                  <div class="progress mt-1" style="height:4px">
                    <div class="progress-bar" style="width:<?= $pct ?>%"></div>
                  </div>
                <?php endif; ?>
              </td>
              <td class="text-center"><?= number_format((int)$ev['paid_orders']) ?></td>
              <td class="text-end fw-bold"><?= formatMoney((float)$ev['revenue']) ?></td>
              <td>
                <div class="btn-group btn-group-sm">
                  <a href="?export=orders&event_id=0" class="btn btn-outline-secondary" title="Orders CSV">
                    <i class="bi bi-file-earmark-spreadsheet"></i> Orders
                  </a>
                  <a href="?export=tickets&event_id=0" class="btn btn-outline-secondary" title="Tickets CSV">
                    <i class="bi bi-ticket-perforated"></i> Tickets
                  </a>
                  <a href="?export=scans&event_id=0" class="btn btn-outline-secondary" title="Scans CSV">
                    <i class="bi bi-qr-code-scan"></i> Scans
                  </a>
                </div>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<!-- All Events Export -->
<div class="card shadow-sm">
  <div class="card-header fw-bold">Global Export</div>
  <div class="card-body">
    <div class="d-flex gap-3 flex-wrap">
      <a href="?export=orders" class="btn btn-outline-primary">
        <i class="bi bi-file-earmark-spreadsheet me-1"></i>All Orders CSV
      </a>
      <a href="?export=tickets" class="btn btn-outline-primary">
        <i class="bi bi-ticket-perforated me-1"></i>All Tickets CSV
      </a>
      <a href="?export=scans" class="btn btn-outline-primary">
        <i class="bi bi-qr-code-scan me-1"></i>All Scans CSV
      </a>
    </div>
  </div>
</div>

<?php require_once __DIR__ . '/includes/admin-footer.php'; ?>
