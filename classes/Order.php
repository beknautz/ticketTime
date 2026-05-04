<?php
declare(strict_types=1);

class Order
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    public function getById(int $id): ?array
    {
        $stmt = $this->db->prepare("SELECT * FROM orders WHERE order_id = ? LIMIT 1");
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function getByPublicId(string $publicId): ?array
    {
        $stmt = $this->db->prepare("SELECT * FROM orders WHERE public_order_id = ? LIMIT 1");
        $stmt->execute([$publicId]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function getByPaymentIntent(string $piId): ?array
    {
        $stmt = $this->db->prepare("SELECT * FROM orders WHERE payment_intent_id = ? LIMIT 1");
        $stmt->execute([$piId]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function getByBarcodeToken(string $token): ?array
    {
        $stmt = $this->db->prepare("SELECT * FROM orders WHERE order_barcode_token = ? LIMIT 1");
        $stmt->execute([$token]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function getItems(int $orderId): array
    {
        $stmt = $this->db->prepare("SELECT * FROM order_items WHERE order_id = ? ORDER BY order_item_id ASC");
        $stmt->execute([$orderId]);
        return $stmt->fetchAll();
    }

    public function getTickets(int $orderId): array
    {
        $stmt = $this->db->prepare("SELECT * FROM tickets WHERE order_id = ? ORDER BY ticket_id ASC");
        $stmt->execute([$orderId]);
        return $stmt->fetchAll();
    }

    public function getAll(array $filters = [], int $limit = 50, int $offset = 0): array
    {
        $where = ['1=1'];
        $params = [];

        if (!empty($filters['event_id'])) {
            $where[] = 'o.event_id = :event_id';
            $params['event_id'] = $filters['event_id'];
        }
        if (!empty($filters['status'])) {
            $where[] = 'o.status = :status';
            $params['status'] = $filters['status'];
        }
        if (!empty($filters['search'])) {
            $where[] = "(o.customer_email LIKE :search OR o.customer_last_name LIKE :search OR o.public_order_id LIKE :search)";
            $params['search'] = '%' . $filters['search'] . '%';
        }

        $sql = "SELECT o.*, e.event_name, e.event_start
                FROM orders o
                JOIN events e ON e.event_id = o.event_id
                WHERE " . implode(' AND ', $where) . "
                ORDER BY o.created_at DESC
                LIMIT :limit OFFSET :offset";

        $stmt = $this->db->prepare($sql);
        foreach ($params as $k => $v) {
            $stmt->bindValue($k, $v);
        }
        $stmt->bindValue('limit', $limit, PDO::PARAM_INT);
        $stmt->bindValue('offset', $offset, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    public function countAll(array $filters = []): int
    {
        $where = ['1=1'];
        $params = [];

        if (!empty($filters['event_id'])) {
            $where[] = 'o.event_id = :event_id';
            $params['event_id'] = $filters['event_id'];
        }
        if (!empty($filters['status'])) {
            $where[] = 'o.status = :status';
            $params['status'] = $filters['status'];
        }
        if (!empty($filters['search'])) {
            $where[] = "(o.customer_email LIKE :search OR o.customer_last_name LIKE :search OR o.public_order_id LIKE :search)";
            $params['search'] = '%' . $filters['search'] . '%';
        }

        $sql = "SELECT COUNT(*) FROM orders o WHERE " . implode(' AND ', $where);
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return (int) $stmt->fetchColumn();
    }

    public function create(array $data): int
    {
        $publicOrderId    = generatePublicOrderId();
        $barcodeToken     = generateToken(48);

        $stmt = $this->db->prepare("
            INSERT INTO orders
                (public_order_id, event_id, customer_id, customer_first_name, customer_last_name,
                 customer_email, customer_phone, subtotal, service_fee_total, tax_total, total,
                 status, payment_provider, order_barcode_token, willcall_status, ip_address, user_agent)
            VALUES
                (:public_order_id, :event_id, :customer_id, :first_name, :last_name,
                 :email, :phone, :subtotal, :fee_total, :tax_total, :total,
                 'pending', 'stripe', :barcode_token, 'not_needed', :ip, :ua)
        ");
        $stmt->execute([
            'public_order_id' => $publicOrderId,
            'event_id'        => $data['event_id'],
            'customer_id'     => $data['customer_id'] ?? null,
            'first_name'      => $data['first_name'],
            'last_name'       => $data['last_name'],
            'email'           => $data['email'],
            'phone'           => $data['phone'] ?? '',
            'subtotal'        => $data['subtotal'],
            'fee_total'       => $data['fee_total'],
            'tax_total'       => $data['tax_total'],
            'total'           => $data['total'],
            'barcode_token'   => $barcodeToken,
            'ip'              => $data['ip'] ?? ($_SERVER['REMOTE_ADDR'] ?? ''),
            'ua'              => $data['ua'] ?? ($_SERVER['HTTP_USER_AGENT'] ?? ''),
        ]);
        return (int) $this->db->lastInsertId();
    }

    public function setPaymentIntent(int $orderId, string $piId): void
    {
        $this->db->prepare("UPDATE orders SET payment_intent_id = ? WHERE order_id = ?")
                 ->execute([$piId, $orderId]);
    }

    public function markPaid(int $orderId, string $paymentStatus = 'succeeded'): void
    {
        $this->db->prepare("
            UPDATE orders
            SET status = 'paid', payment_status = :ps, paid_at = NOW()
            WHERE order_id = :id AND status = 'pending'
        ")->execute(['ps' => $paymentStatus, 'id' => $orderId]);
    }

    public function markFailed(int $orderId): void
    {
        $this->db->prepare("UPDATE orders SET status = 'failed' WHERE order_id = ? AND status = 'pending'")
                 ->execute([$orderId]);
    }

    public function addItem(int $orderId, array $item): int
    {
        $stmt = $this->db->prepare("
            INSERT INTO order_items (order_id, ticket_type_id, ticket_name, quantity, unit_price, unit_fee, line_total)
            VALUES (:order_id, :ticket_type_id, :ticket_name, :qty, :unit_price, :unit_fee, :line_total)
        ");
        $stmt->execute([
            'order_id'       => $orderId,
            'ticket_type_id' => $item['ticket_type_id'],
            'ticket_name'    => $item['ticket_name'],
            'qty'            => $item['quantity'],
            'unit_price'     => $item['unit_price'],
            'unit_fee'       => $item['unit_fee'],
            'line_total'     => $item['line_total'],
        ]);
        return (int) $this->db->lastInsertId();
    }

    public function searchWillCall(string $term): array
    {
        $stmt = $this->db->prepare("
            SELECT o.*, e.event_name, e.event_start
            FROM orders o
            JOIN events e ON e.event_id = o.event_id
            WHERE o.status = 'paid'
              AND (o.order_barcode_token = :token
                   OR o.public_order_id = :pubid
                   OR o.customer_email = :email
                   OR o.customer_last_name LIKE :name)
            ORDER BY o.created_at DESC
            LIMIT 20
        ");
        $stmt->execute([
            'token'  => $term,
            'pubid'  => $term,
            'email'  => $term,
            'name'   => '%' . $term . '%',
        ]);
        return $stmt->fetchAll();
    }

    public function markWillCallPickedUp(int $orderId, string $pickedUpBy): bool
    {
        $stmt = $this->db->prepare("
            UPDATE orders
            SET willcall_status = 'picked_up', picked_up_at = NOW(), picked_up_by = :by
            WHERE order_id = :id AND status = 'paid'
        ");
        return $stmt->execute(['by' => $pickedUpBy, 'id' => $orderId]);
    }

    public function delete(int $orderId): void
    {
        // Release inventory for each ticket type in this order
        $items = $this->getItems($orderId);
        $ttModel = new TicketType();
        foreach ($items as $item) {
            $ttModel->releaseQuantity((int)$item['ticket_type_id'], (int)$item['quantity']);
        }

        $this->db->prepare("DELETE FROM tickets    WHERE order_id = ?")->execute([$orderId]);
        $this->db->prepare("DELETE FROM order_items WHERE order_id = ?")->execute([$orderId]);
        $this->db->prepare("DELETE FROM orders     WHERE order_id = ?")->execute([$orderId]);
    }
}
