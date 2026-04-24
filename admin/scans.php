<?php
require_once dirname(__DIR__) . '/config/config.php';
require_once BASE_PATH . '/includes/auth.php';

requireAdmin(['admin', 'scanner']);

$db         = Database::getInstance();
$eventModel = new Event();
$events     = $eventModel->getAll();

$eventId = (int)($_GET['event_id'] ?? 0);
$page    = max(1, (int)($_GET['page'] ?? 1));
$perPage = 50;
$offset  = ($page - 1) * $perPage;

$where  = ['1=1'];
$params = [];

if ($eventId) {
    $where[]           = 't.event_id = :event_id';
    $params['event_id'] = $eventId;
}

$totalSql = "SELECT COUNT(*) FROM ticket_scans ts LEFT JOIN tickets t ON t.ticket_id = ts.ticket_id WHERE " . implode(' AND ', $where);
$stmt     = $db->prepare($totalSql);
$stmt->execute($params);
$total = (int)$stmt->fetchColumn();

$sql  = "
    SELECT ts.*, t.ticket_code, t.ticket_name, t.event_id,
           o.customer_first_name, o.customer_last_name, o.public_order_id,
           a.name as scanner_name, e.event_name
    FROM ticket_scans ts
    LEFT JOIN tickets t ON t.ticket_id = ts.ticket_id
    LEFT JOIN orders o ON o.order_id = t.order_id
    LEFT JOIN admins a ON a.admin_id = ts.scanned_by
    LEFT JOIN events e ON e.event_id = t.event_id
    WHERE " . implode(' AND ', $where) . "
    ORDER BY ts.scanned_at DESC
    LIMIT :limit OFFSET :offset
";
$stmt = $db->prepare($sql);
foreach ($params as $k => $v) {
    $stmt->bindValue($k, $v);
}
$stmt->bindValue('limit', $perPage, PDO::PARAM_INT);
$stmt->bindValue('offset', $offset, PDO::PARAM_INT);
$stmt->execute();
$scans = $stmt->fetchAll();

// Summary stats
$summaryStmt = $db->prepare("
    SELECT
        COUNT(*) as total_scans,
        SUM(CASE WHEN ts.scan_result = 'valid' THEN 1 ELSE 0 END) as valid_scans,
        SUM(CASE WHEN ts.scan_result = 'already_used' THEN 1 ELSE 0 END) as duplicate_scans,
        SUM(CASE WHEN ts.scan_result = 'invalid' THEN 1 ELSE 0 END) as invalid_scans
    FROM ticket_scans ts
    LEFT JOIN tickets t ON t.ticket_id = ts.ticket_id
    WHERE " . implode(' AND ', $where)
);
$summaryStmt->execute($params);
$summary = $summaryStmt->fetch();

$pageTitle = 'Scan Report';
require_once __DIR__ . '/includes/admin-header.php';
?>
<div class="d-flex justify-content-between align-items-center mb-4">
  <h2 class="fw-bold mb-0">Scan Report</h2>
  <a href="<?= SITE_URL ?>/admin/reports.php?export=scans&event_id=<?= $eventId ?>"
     class="btn btn-outline-secondary">
    <i class="bi bi-download me-1"></i>Export CSV
  </a>
</div>

<!-- Filter -->
<div class="card shadow-sm mb-4">
  <div class="card-body py-2">
    <form method="get" class="d-flex gap-2 align-items-center">
      <select name="event_id" class="form-select form-select-sm w-auto" onchange="this.form.submit()">
        <option value="">All Events</option>
        <?php foreach ($events as $ev): ?>
          <option value="<?= (int)$ev['event_id'] ?>"
                  <?= $eventId === (int)$ev['event_id'] ? 'selected' : '' ?>>
            <?= e($ev['event_name']) ?>
          </option>
        <?php endforeach; ?>
      </select>
      <a href="<?= SITE_URL ?>/public/scan.php<?= $eventId ? '?event_id=' . $eventId : '' ?>"
         class="btn btn-primary btn-sm ms-auto">
        <i class="bi bi-camera me-1"></i>Open Scanner
      </a>
    </form>
  </div>
</div>

<!-- Stats -->
<div class="row g-3 mb-4">
  <div class="col-sm-3">
    <div class="card text-center shadow-sm border-0 bg-light">
      <div class="card-body py-2">
        <div class="fs-4 fw-bold"><?= number_format((int)$summary['total_scans']) ?></div>
        <div class="text-muted small">Total Scans</div>
      </div>
    </div>
  </div>
  <div class="col-sm-3">
    <div class="card text-center shadow-sm border-0 bg-success bg-opacity-10">
      <div class="card-body py-2">
        <div class="fs-4 fw-bold text-success"><?= number_format((int)$summary['valid_scans']) ?></div>
        <div class="text-muted small">Valid Entries</div>
      </div>
    </div>
  </div>
  <div class="col-sm-3">
    <div class="card text-center shadow-sm border-0 bg-warning bg-opacity-10">
      <div class="card-body py-2">
        <div class="fs-4 fw-bold text-warning"><?= number_format((int)$summary['duplicate_scans']) ?></div>
        <div class="text-muted small">Duplicates</div>
      </div>
    </div>
  </div>
  <div class="col-sm-3">
    <div class="card text-center shadow-sm border-0 bg-danger bg-opacity-10">
      <div class="card-body py-2">
        <div class="fs-4 fw-bold text-danger"><?= number_format((int)$summary['invalid_scans']) ?></div>
        <div class="text-muted small">Invalid</div>
      </div>
    </div>
  </div>
</div>

<!-- Scan Log Table -->
<div class="card shadow-sm">
  <div class="card-body p-0">
    <div class="table-responsive">
      <table class="table table-sm table-hover mb-0 small align-middle">
        <thead class="table-light">
          <tr>
            <th>Time</th>
            <th>Result</th>
            <th>Ticket</th>
            <th>Customer</th>
            <th>Event</th>
            <th>Scanner</th>
            <th>Location</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($scans as $s):
            $rowClass = match($s['scan_result']) {
              'valid'        => 'table-success',
              'already_used' => 'table-warning',
              default        => 'table-danger'
            };
          ?>
            <tr class="<?= $rowClass ?>">
              <td class="text-nowrap"><?= formatDate($s['scanned_at'], 'M j g:i:s A') ?></td>
              <td>
                <?php if ($s['scan_result'] === 'valid'): ?>
                  <i class="bi bi-check-circle-fill text-success"></i>
                <?php elseif ($s['scan_result'] === 'already_used'): ?>
                  <i class="bi bi-exclamation-triangle-fill text-warning"></i>
                <?php else: ?>
                  <i class="bi bi-x-circle-fill text-danger"></i>
                <?php endif; ?>
                <?= ucfirst(str_replace('_', ' ', $s['scan_result'])) ?>
              </td>
              <td class="text-monospace"><?= e($s['ticket_code'] ?? substr($s['qr_token'], 0, 12) . '…') ?></td>
              <td><?= $s['customer_first_name'] ? e($s['customer_first_name'] . ' ' . $s['customer_last_name']) : '—' ?></td>
              <td><?= e($s['event_name'] ?? '—') ?></td>
              <td><?= e($s['scanner_name'] ?? 'System') ?></td>
              <td><?= e($s['scan_location'] ?? '') ?></td>
            </tr>
          <?php endforeach; ?>
          <?php if (empty($scans)): ?>
            <tr><td colspan="7" class="text-center text-muted py-4">No scans recorded yet</td></tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<div class="mt-3">
  <?= paginationLinks($total, $perPage, $page, 'scans.php?event_id=' . $eventId) ?>
</div>

<?php require_once __DIR__ . '/includes/admin-footer.php'; ?>
