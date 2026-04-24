<?php
declare(strict_types=1);

class Event
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    public function getActive(): array
    {
        $sql = "SELECT e.*,
                    MIN(tt.price) as min_price,
                    MAX(tt.price) as max_price,
                    SUM(tt.quantity_available - tt.quantity_sold) as tickets_remaining
                FROM events e
                LEFT JOIN ticket_types tt ON tt.event_id = e.event_id AND tt.status = 'active'
                WHERE e.status = 'active'
                  AND (e.sale_start IS NULL OR e.sale_start <= NOW())
                  AND (e.sale_end IS NULL OR e.sale_end >= NOW())
                GROUP BY e.event_id
                ORDER BY e.event_start ASC";
        $stmt = $this->db->prepare($sql);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    public function getAll(): array
    {
        $sql = "SELECT e.*,
                    COUNT(DISTINCT tt.ticket_type_id) as type_count,
                    SUM(tt.quantity_available) as total_capacity,
                    SUM(tt.quantity_sold) as total_sold
                FROM events e
                LEFT JOIN ticket_types tt ON tt.event_id = e.event_id
                GROUP BY e.event_id
                ORDER BY e.event_start DESC";
        $stmt = $this->db->prepare($sql);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    public function getBySlug(string $slug): ?array
    {
        $stmt = $this->db->prepare("SELECT * FROM events WHERE event_slug = ? LIMIT 1");
        $stmt->execute([$slug]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function getById(int $id): ?array
    {
        $stmt = $this->db->prepare("SELECT * FROM events WHERE event_id = ? LIMIT 1");
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function create(array $data): int
    {
        $slug = $this->uniqueSlug($data['event_name']);
        $stmt = $this->db->prepare("
            INSERT INTO events (event_name, event_slug, event_description, event_location,
                event_start, event_end, sale_start, sale_end, status)
            VALUES (:event_name, :event_slug, :event_description, :event_location,
                :event_start, :event_end, :sale_start, :sale_end, :status)
        ");
        $stmt->execute([
            'event_name'        => $data['event_name'],
            'event_slug'        => $data['event_slug'] ?? $slug,
            'event_description' => $data['event_description'] ?? '',
            'event_location'    => $data['event_location'] ?? '',
            'event_start'       => $data['event_start'],
            'event_end'         => $data['event_end'],
            'sale_start'        => $data['sale_start'] ?? null,
            'sale_end'          => $data['sale_end'] ?? null,
            'status'            => $data['status'] ?? 'draft',
        ]);
        return (int) $this->db->lastInsertId();
    }

    public function update(int $id, array $data): bool
    {
        $stmt = $this->db->prepare("
            UPDATE events SET
                event_name        = :event_name,
                event_slug        = :event_slug,
                event_description = :event_description,
                event_location    = :event_location,
                event_start       = :event_start,
                event_end         = :event_end,
                sale_start        = :sale_start,
                sale_end          = :sale_end,
                status            = :status
            WHERE event_id = :event_id
        ");
        return $stmt->execute([
            'event_name'        => $data['event_name'],
            'event_slug'        => $data['event_slug'],
            'event_description' => $data['event_description'] ?? '',
            'event_location'    => $data['event_location'] ?? '',
            'event_start'       => $data['event_start'],
            'event_end'         => $data['event_end'],
            'sale_start'        => $data['sale_start'] ?: null,
            'sale_end'          => $data['sale_end'] ?: null,
            'status'            => $data['status'],
            'event_id'          => $id,
        ]);
    }

    public function getTicketTypes(int $eventId, bool $activeOnly = false): array
    {
        $sql = "SELECT * FROM ticket_types WHERE event_id = ?";
        if ($activeOnly) {
            $sql .= " AND status = 'active'";
        }
        $sql .= " ORDER BY sort_order ASC, ticket_type_id ASC";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$eventId]);
        return $stmt->fetchAll();
    }

    public function getStats(int $eventId): array
    {
        $stmt = $this->db->prepare("
            SELECT
                COALESCE(SUM(tt.quantity_sold), 0) as tickets_sold,
                COALESCE(SUM(tt.quantity_available), 0) as tickets_total,
                COALESCE(SUM(ts.scan_count), 0) as tickets_scanned,
                COUNT(DISTINCT o.order_id) as order_count,
                COALESCE(SUM(o.total), 0) as revenue
            FROM events e
            LEFT JOIN ticket_types tt ON tt.event_id = e.event_id
            LEFT JOIN orders o ON o.event_id = e.event_id AND o.status = 'paid'
            LEFT JOIN (
                SELECT event_id, COUNT(*) as scan_count
                FROM tickets WHERE status = 'used' AND event_id = ?
            ) ts ON ts.event_id = e.event_id
            WHERE e.event_id = ?
        ");
        $stmt->execute([$eventId, $eventId]);
        return $stmt->fetch() ?: [];
    }

    private function uniqueSlug(string $name): string
    {
        $base = strtolower(preg_replace('/[^a-zA-Z0-9]+/', '-', $name));
        $base = trim($base, '-');
        $slug = $base;
        $i = 1;
        while ($this->slugExists($slug)) {
            $slug = $base . '-' . $i++;
        }
        return $slug;
    }

    private function slugExists(string $slug): bool
    {
        $stmt = $this->db->prepare("SELECT 1 FROM events WHERE event_slug = ? LIMIT 1");
        $stmt->execute([$slug]);
        return (bool) $stmt->fetch();
    }
}
