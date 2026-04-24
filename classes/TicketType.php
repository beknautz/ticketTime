<?php
declare(strict_types=1);

class TicketType
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    public function getById(int $id): ?array
    {
        $stmt = $this->db->prepare("SELECT * FROM ticket_types WHERE ticket_type_id = ? LIMIT 1");
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function create(array $data): int
    {
        $stmt = $this->db->prepare("
            INSERT INTO ticket_types
                (event_id, ticket_name, ticket_description, price, service_fee,
                 quantity_available, max_per_order, status, sort_order)
            VALUES
                (:event_id, :ticket_name, :ticket_description, :price, :service_fee,
                 :quantity_available, :max_per_order, :status, :sort_order)
        ");
        $stmt->execute([
            'event_id'           => $data['event_id'],
            'ticket_name'        => $data['ticket_name'],
            'ticket_description' => $data['ticket_description'] ?? '',
            'price'              => $data['price'],
            'service_fee'        => $data['service_fee'] ?? 0,
            'quantity_available' => $data['quantity_available'],
            'max_per_order'      => $data['max_per_order'] ?? 10,
            'status'             => $data['status'] ?? 'active',
            'sort_order'         => $data['sort_order'] ?? 0,
        ]);
        return (int) $this->db->lastInsertId();
    }

    public function update(int $id, array $data): bool
    {
        $stmt = $this->db->prepare("
            UPDATE ticket_types SET
                ticket_name        = :ticket_name,
                ticket_description = :ticket_description,
                price              = :price,
                service_fee        = :service_fee,
                quantity_available = :quantity_available,
                max_per_order      = :max_per_order,
                status             = :status,
                sort_order         = :sort_order
            WHERE ticket_type_id = :id
        ");
        return $stmt->execute([
            'ticket_name'        => $data['ticket_name'],
            'ticket_description' => $data['ticket_description'] ?? '',
            'price'              => $data['price'],
            'service_fee'        => $data['service_fee'] ?? 0,
            'quantity_available' => $data['quantity_available'],
            'max_per_order'      => $data['max_per_order'] ?? 10,
            'status'             => $data['status'] ?? 'active',
            'sort_order'         => $data['sort_order'] ?? 0,
            'id'                 => $id,
        ]);
    }

    // Reserve quantity with pessimistic locking; returns false if oversold
    public function reserveQuantity(int $id, int $qty): bool
    {
        $db = $this->db;
        $stmt = $db->prepare("
            SELECT ticket_type_id, quantity_available, quantity_sold, status
            FROM ticket_types
            WHERE ticket_type_id = ?
            FOR UPDATE
        ");
        $stmt->execute([$id]);
        $tt = $stmt->fetch();

        if (!$tt) return false;
        if ($tt['status'] !== 'active') return false;

        $remaining = (int)$tt['quantity_available'] - (int)$tt['quantity_sold'];
        if ($remaining < $qty) return false;

        $newSold = (int)$tt['quantity_sold'] + $qty;
        $newStatus = ($newSold >= (int)$tt['quantity_available']) ? 'sold_out' : 'active';

        $upd = $db->prepare("
            UPDATE ticket_types
            SET quantity_sold = :sold, status = :status
            WHERE ticket_type_id = :id
        ");
        return $upd->execute(['sold' => $newSold, 'status' => $newStatus, 'id' => $id]);
    }

    public function releaseQuantity(int $id, int $qty): void
    {
        $this->db->prepare("
            UPDATE ticket_types
            SET quantity_sold = GREATEST(0, quantity_sold - :qty),
                status = CASE WHEN status = 'sold_out' AND quantity_sold - :qty2 < quantity_available THEN 'active' ELSE status END
            WHERE ticket_type_id = :id
        ")->execute(['qty' => $qty, 'qty2' => $qty, 'id' => $id]);
    }
}
