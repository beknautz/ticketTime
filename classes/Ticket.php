<?php
declare(strict_types=1);

class Ticket
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    public function getById(int $id): ?array
    {
        $stmt = $this->db->prepare("
            SELECT t.*, e.event_name, e.event_start, e.event_location,
                   o.public_order_id, o.customer_first_name, o.customer_last_name, o.customer_email
            FROM tickets t
            JOIN orders o ON o.order_id = t.order_id
            JOIN events e ON e.event_id = t.event_id
            WHERE t.ticket_id = ?
            LIMIT 1
        ");
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function getByQrToken(string $token): ?array
    {
        $stmt = $this->db->prepare("
            SELECT t.*, e.event_name, e.event_start, e.event_location,
                   o.public_order_id, o.customer_first_name, o.customer_last_name, o.customer_email
            FROM tickets t
            JOIN orders o ON o.order_id = t.order_id
            JOIN events e ON e.event_id = t.event_id
            WHERE t.qr_token = ?
            LIMIT 1
        ");
        $stmt->execute([$token]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function getByTicketCode(string $code): ?array
    {
        $stmt = $this->db->prepare("SELECT * FROM tickets WHERE ticket_code = ? LIMIT 1");
        $stmt->execute([$code]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    // Generate all tickets for an order from its items
    public function generateForOrder(int $orderId): array
    {
        $order = (new Order())->getById($orderId);
        if (!$order) throw new RuntimeException("Order not found: {$orderId}");

        $items = (new Order())->getItems($orderId);
        $generated = [];

        foreach ($items as $item) {
            for ($i = 0; $i < (int)$item['quantity']; $i++) {
                $ticketCode = $this->generateTicketCode();
                $qrToken    = generateToken(48);

                $stmt = $this->db->prepare("
                    INSERT INTO tickets
                        (order_id, order_item_id, event_id, ticket_type_id, ticket_code, qr_token, ticket_name, status)
                    VALUES
                        (:order_id, :order_item_id, :event_id, :ticket_type_id, :ticket_code, :qr_token, :ticket_name, 'valid')
                ");
                $stmt->execute([
                    'order_id'       => $orderId,
                    'order_item_id'  => $item['order_item_id'],
                    'event_id'       => $order['event_id'],
                    'ticket_type_id' => $item['ticket_type_id'],
                    'ticket_code'    => $ticketCode,
                    'qr_token'       => $qrToken,
                    'ticket_name'    => $item['ticket_name'],
                ]);

                $generated[] = [
                    'ticket_id'   => (int) $this->db->lastInsertId(),
                    'ticket_code' => $ticketCode,
                    'qr_token'    => $qrToken,
                    'ticket_name' => $item['ticket_name'],
                ];
            }
        }
        return $generated;
    }

    public function voidTicket(int $ticketId, int $adminId): bool
    {
        $stmt = $this->db->prepare("
            UPDATE tickets SET status = 'void', updated_at = NOW()
            WHERE ticket_id = :id AND status NOT IN ('used')
        ");
        return $stmt->execute(['id' => $ticketId]);
    }

    public function refundTicket(int $ticketId): bool
    {
        $stmt = $this->db->prepare("UPDATE tickets SET status = 'refunded' WHERE ticket_id = ?");
        return $stmt->execute([$ticketId]);
    }

    private function generateTicketCode(): string
    {
        // Format: TK-XXXXXX (human readable, uppercase)
        do {
            $code = 'TK-' . strtoupper(substr(bin2hex(random_bytes(4)), 0, 8));
        } while ($this->codeExists($code));
        return $code;
    }

    private function codeExists(string $code): bool
    {
        $stmt = $this->db->prepare("SELECT 1 FROM tickets WHERE ticket_code = ? LIMIT 1");
        $stmt->execute([$code]);
        return (bool) $stmt->fetch();
    }
}
