<?php
declare(strict_types=1);

class Scanner
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    /**
     * Process a scan. Returns result array with keys:
     *   result: valid|already_used|invalid|void|refunded|wrong_event
     *   message: human-readable message
     *   ticket: ticket row (if found)
     *   color:  green|red|orange
     */
    public function scan(string $qrToken, int $eventId, int $scannedBy, string $location = ''): array
    {
        $ip = $_SERVER['REMOTE_ADDR'] ?? '';
        $ua = $_SERVER['HTTP_USER_AGENT'] ?? '';

        try {
            $this->db->beginTransaction();

            // Lock the row for update to prevent race conditions
            $stmt = $this->db->prepare("
                SELECT t.*, e.event_name, e.event_start,
                       o.public_order_id, o.customer_first_name, o.customer_last_name
                FROM tickets t
                JOIN events e ON e.event_id = t.event_id
                JOIN orders o ON o.order_id = t.order_id
                WHERE t.qr_token = ?
                FOR UPDATE
            ");
            $stmt->execute([$qrToken]);
            $ticket = $stmt->fetch();

            if (!$ticket) {
                $this->db->rollBack();
                $this->logScan(null, $qrToken, 'invalid', 'Ticket not found', $scannedBy, $location, $ip, $ua);
                return $this->result('invalid', 'Invalid ticket - not found', null, 'red');
            }

            if ((int)$ticket['event_id'] !== $eventId) {
                $this->db->rollBack();
                $this->logScan($ticket['ticket_id'], $qrToken, 'wrong_event',
                    'Wrong event', $scannedBy, $location, $ip, $ua);
                return $this->result('wrong_event',
                    'Wrong event! This ticket is for: ' . $ticket['event_name'],
                    $ticket, 'red');
            }

            switch ($ticket['status']) {
                case 'used':
                    $this->db->rollBack();
                    $scannedTime = $ticket['scanned_at']
                        ? date('g:i A', strtotime($ticket['scanned_at']))
                        : 'unknown time';
                    $this->logScan($ticket['ticket_id'], $qrToken, 'already_used',
                        'Already scanned at ' . $scannedTime, $scannedBy, $location, $ip, $ua);
                    return $this->result('already_used',
                        "Already scanned at {$scannedTime}",
                        $ticket, 'orange');

                case 'void':
                    $this->db->rollBack();
                    $this->logScan($ticket['ticket_id'], $qrToken, 'void',
                        'Ticket voided', $scannedBy, $location, $ip, $ua);
                    return $this->result('void', 'Ticket has been voided', $ticket, 'red');

                case 'refunded':
                    $this->db->rollBack();
                    $this->logScan($ticket['ticket_id'], $qrToken, 'refunded',
                        'Ticket refunded', $scannedBy, $location, $ip, $ua);
                    return $this->result('refunded', 'Ticket has been refunded', $ticket, 'red');

                case 'valid':
                    // Mark used
                    $upd = $this->db->prepare("
                        UPDATE tickets
                        SET status = 'used', scanned_at = NOW(), scanned_by = :by, scan_location = :loc
                        WHERE ticket_id = :id AND status = 'valid'
                    ");
                    $upd->execute(['by' => $scannedBy, 'loc' => $location, 'id' => $ticket['ticket_id']]);

                    if ($upd->rowCount() === 0) {
                        // Race condition: another process marked it used between our SELECT and UPDATE
                        $this->db->rollBack();
                        $this->logScan($ticket['ticket_id'], $qrToken, 'already_used',
                            'Race condition - already used', $scannedBy, $location, $ip, $ua);
                        return $this->result('already_used', 'Already scanned (race condition)', $ticket, 'orange');
                    }

                    $this->logScan($ticket['ticket_id'], $qrToken, 'valid',
                        'Valid scan - admitted', $scannedBy, $location, $ip, $ua);

                    $this->db->commit();

                    $ticket['scanned_at'] = date('Y-m-d H:i:s');
                    return $this->result('valid',
                        'Valid! Welcome, ' . $ticket['customer_first_name'],
                        $ticket, 'green');
            }

            $this->db->rollBack();
            return $this->result('invalid', 'Unknown ticket status', $ticket, 'red');

        } catch (\Throwable $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            Logger::error('Scan failed', ['error' => $e->getMessage(), 'token' => $qrToken]);
            return $this->result('invalid', 'Scan processing error - please retry', null, 'red');
        }
    }

    private function logScan(
        ?int $ticketId, string $qrToken, string $result, string $message,
        int $scannedBy, string $location, string $ip, string $ua
    ): void {
        try {
            $stmt = $this->db->prepare("
                INSERT INTO ticket_scans
                    (ticket_id, qr_token, scan_result, scan_message, scanned_by, scan_location, ip_address, user_agent)
                VALUES
                    (:ticket_id, :token, :result, :message, :by, :loc, :ip, :ua)
            ");
            $stmt->execute([
                'ticket_id' => $ticketId,
                'token'     => $qrToken,
                'result'    => $result,
                'message'   => $message,
                'by'        => $scannedBy,
                'loc'       => $location,
                'ip'        => $ip,
                'ua'        => $ua,
            ]);
        } catch (\Throwable $e) {
            Logger::error('Failed to log scan', ['error' => $e->getMessage()]);
        }
    }

    private function result(string $result, string $message, ?array $ticket, string $color): array
    {
        return [
            'result'  => $result,
            'message' => $message,
            'ticket'  => $ticket,
            'color'   => $color,
        ];
    }

    public function getRecentScans(int $eventId, int $limit = 50): array
    {
        $stmt = $this->db->prepare("
            SELECT ts.*, t.ticket_code, t.ticket_name,
                   o.customer_first_name, o.customer_last_name, o.public_order_id,
                   a.name as scanner_name
            FROM ticket_scans ts
            LEFT JOIN tickets t ON t.ticket_id = ts.ticket_id
            LEFT JOIN orders o ON o.order_id = t.order_id
            LEFT JOIN admins a ON a.admin_id = ts.scanned_by
            WHERE t.event_id = :eid OR (t.ticket_id IS NULL)
            ORDER BY ts.scanned_at DESC
            LIMIT :lim
        ");
        $stmt->bindValue('eid', $eventId, PDO::PARAM_INT);
        $stmt->bindValue('lim', $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }
}
