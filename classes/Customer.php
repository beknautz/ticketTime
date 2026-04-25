<?php
declare(strict_types=1);

class Customer
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    public function getById(int $id): ?array
    {
        $stmt = $this->db->prepare("SELECT * FROM customers WHERE customer_id = ? LIMIT 1");
        $stmt->execute([$id]);
        return $stmt->fetch() ?: null;
    }

    public function getByEmail(string $email): ?array
    {
        $stmt = $this->db->prepare("SELECT * FROM customers WHERE email = ? LIMIT 1");
        $stmt->execute([strtolower(trim($email))]);
        return $stmt->fetch() ?: null;
    }

    public function register(array $data): int
    {
        $stmt = $this->db->prepare("
            INSERT INTO customers (email, password_hash, first_name, last_name, phone, address, city, state, zip)
            VALUES (:email, :password_hash, :first_name, :last_name, :phone, :address, :city, :state, :zip)
        ");
        $stmt->execute([
            'email'         => strtolower(trim($data['email'])),
            'password_hash' => password_hash($data['password'], PASSWORD_BCRYPT, ['cost' => 12]),
            'first_name'    => trim($data['first_name']),
            'last_name'     => trim($data['last_name']),
            'phone'         => trim($data['phone'] ?? ''),
            'address'       => trim($data['address'] ?? ''),
            'city'          => trim($data['city'] ?? ''),
            'state'         => trim($data['state'] ?? ''),
            'zip'           => trim($data['zip'] ?? ''),
        ]);
        return (int) $this->db->lastInsertId();
    }

    public function verifyPassword(string $email, string $password): ?array
    {
        $customer = $this->getByEmail($email);
        if (!$customer || $customer['status'] !== 'active') return null;
        if (!password_verify($password, $customer['password_hash'])) return null;

        $this->db->prepare("UPDATE customers SET last_login = NOW() WHERE customer_id = ?")
                 ->execute([$customer['customer_id']]);

        return $customer;
    }

    public function updateProfile(int $id, array $data): bool
    {
        $stmt = $this->db->prepare("
            UPDATE customers
            SET first_name = :first_name,
                last_name  = :last_name,
                phone      = :phone,
                address    = :address,
                city       = :city,
                state      = :state,
                zip        = :zip
            WHERE customer_id = :id
        ");
        return $stmt->execute([
            'first_name' => trim($data['first_name']),
            'last_name'  => trim($data['last_name']),
            'phone'      => trim($data['phone'] ?? ''),
            'address'    => trim($data['address'] ?? ''),
            'city'       => trim($data['city'] ?? ''),
            'state'      => trim($data['state'] ?? ''),
            'zip'        => trim($data['zip'] ?? ''),
            'id'         => $id,
        ]);
    }

    public function updateEmail(int $id, string $email): bool
    {
        $stmt = $this->db->prepare("UPDATE customers SET email = ? WHERE customer_id = ?");
        return $stmt->execute([strtolower(trim($email)), $id]);
    }

    public function updatePassword(int $id, string $password): bool
    {
        $hash = password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);
        $stmt = $this->db->prepare("UPDATE customers SET password_hash = ? WHERE customer_id = ?");
        return $stmt->execute([$hash, $id]);
    }

    public function emailExists(string $email): bool
    {
        $stmt = $this->db->prepare("SELECT 1 FROM customers WHERE email = ? LIMIT 1");
        $stmt->execute([strtolower(trim($email))]);
        return (bool) $stmt->fetch();
    }

    // Get all orders for a customer (by customer_id OR email match)
    public function getOrders(int $customerId, string $email): array
    {
        $stmt = $this->db->prepare("
            SELECT o.*, e.event_name, e.event_start, e.event_location,
                   COUNT(t.ticket_id) as ticket_count,
                   SUM(CASE WHEN t.status = 'used' THEN 1 ELSE 0 END) as used_count
            FROM orders o
            JOIN events e ON e.event_id = o.event_id
            LEFT JOIN tickets t ON t.order_id = o.order_id
            WHERE (o.customer_id = :cid OR o.customer_email = :email)
              AND o.status = 'paid'
            GROUP BY o.order_id
            ORDER BY o.created_at DESC
        ");
        $stmt->execute(['cid' => $customerId, 'email' => $email]);
        return $stmt->fetchAll();
    }

    // Link existing orders by email to this customer account
    public function linkOrdersByEmail(int $customerId, string $email): void
    {
        $this->db->prepare("
            UPDATE orders SET customer_id = ?
            WHERE customer_email = ? AND customer_id IS NULL AND status = 'paid'
        ")->execute([$customerId, strtolower($email)]);
    }
}
