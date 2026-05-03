<?php
require_once dirname(__DIR__, 2) . '/config/config.php';
require_once BASE_PATH . '/includes/auth.php';

requireAdmin(['admin', 'scanner', 'box_office']);

$eventId = (int)($_GET['event_id'] ?? 0);

$db = Database::getInstance();
$stmt = $db->prepare("
    SELECT ts.*, t.ticket_code, t.ticket_name,
           o.customer_first_name, o.customer_last_name,
           a.name as scanner_name
    FROM ticket_scans ts
    LEFT JOIN tickets t ON t.ticket_id = ts.ticket_id
    LEFT JOIN orders o ON o.order_id = t.order_id
    LEFT JOIN admins a ON a.admin_id = ts.scanned_by
    WHERE (t.event_id = :eid OR ts.ticket_id IS NULL)
    ORDER BY ts.scanned_at DESC
    LIMIT 20
");
$stmt->bindValue('eid', $eventId, PDO::PARAM_INT);
$stmt->execute();
$scans = $stmt->fetchAll();

if (empty($scans)): ?>
  <div class="text-center py-3 text-muted small">No scans yet</div>
<?php else: ?>
  <table class="table table-sm table-hover mb-0 small">
    <tbody>
      <?php foreach ($scans as $s):
        $rowClass = match($s['scan_result']) {
            'valid'       => 'table-success',
            'already_used'=> 'table-warning',
            default       => 'table-danger'
        };
      ?>
        <tr class="<?= $rowClass ?>">
          <td class="ps-2">
            <?php if ($s['scan_result'] === 'valid'): ?>
              <i class="bi bi-check-circle-fill text-success"></i>
            <?php elseif ($s['scan_result'] === 'already_used'): ?>
              <i class="bi bi-exclamation-circle-fill text-warning"></i>
            <?php else: ?>
              <i class="bi bi-x-circle-fill text-danger"></i>
            <?php endif; ?>
          </td>
          <td><?= e($s['ticket_name'] ?? 'Unknown') ?></td>
          <td><?= $s['customer_first_name'] ? e($s['customer_first_name'][0] . '.' . $s['customer_last_name']) : '—' ?></td>
          <td class="text-muted"><?= date('g:i:s A', strtotime($s['scanned_at'])) ?></td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
<?php endif; ?>
