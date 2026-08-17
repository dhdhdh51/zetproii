<?php
/**
 * Customer CRM business logic: CRUD, filtering/pagination, notes,
 * activity timeline, and the aggregated customer profile (leads,
 * conversations, proposals, quotations, invoices, tasks) used by the
 * customer detail page.
 */
final class CustomerService
{
    public function list(int $businessId, array $filters, int $page, int $perPage): array
    {
        $page = max(1, $page);
        $perPage = max(1, min(100, $perPage));
        $offset = ($page - 1) * $perPage;

        $where = ["c.business_id = ?", "c.deleted_at IS NULL"];
        $params = [$businessId];

        if (!empty($filters['search'])) {
            $where[] = "(c.name LIKE ? OR c.email LIKE ? OR c.phone LIKE ? OR c.company LIKE ?)";
            $term = '%' . $filters['search'] . '%';
            array_push($params, $term, $term, $term, $term);
        }
        if (!empty($filters['date_from'])) {
            $where[] = "c.created_at >= ?";
            $params[] = $filters['date_from'] . ' 00:00:00';
        }
        if (!empty($filters['date_to'])) {
            $where[] = "c.created_at <= ?";
            $params[] = $filters['date_to'] . ' 23:59:59';
        }

        $sortColumn = in_array($filters['sort'] ?? '', ['name', 'created_at', 'total_spent'], true) ? $filters['sort'] : 'created_at';
        $sortDir = (($filters['dir'] ?? 'desc') === 'asc') ? 'ASC' : 'DESC';
        $whereSql = implode(' AND ', $where);

        $total = (int) (Database::fetchOne("SELECT COUNT(*) c FROM customers c WHERE {$whereSql}", $params)['c'] ?? 0);

        $rows = Database::fetchAll(
            "SELECT c.* FROM customers c WHERE {$whereSql} ORDER BY c.{$sortColumn} {$sortDir} LIMIT {$perPage} OFFSET {$offset}",
            $params
        );

        return [
            'items' => $rows,
            'pagination' => ['page' => $page, 'per_page' => $perPage, 'total' => $total, 'total_pages' => (int) ceil($total / $perPage)],
        ];
    }

    public function find(int $businessId, int $customerId): ?array
    {
        $customer = Database::fetchOne(
            "SELECT * FROM customers WHERE business_id = ? AND id = ? AND deleted_at IS NULL",
            [$businessId, $customerId]
        );
        if ($customer === null) {
            return null;
        }

        $customer['notes'] = Database::fetchAll(
            "SELECT cn.*, u.name AS user_name FROM customer_notes cn LEFT JOIN users u ON u.id = cn.user_id
             WHERE cn.customer_id = ? ORDER BY cn.created_at DESC", [$customerId]
        );
        $customer['activities'] = Database::fetchAll(
            "SELECT * FROM customer_activities WHERE customer_id = ? ORDER BY created_at DESC LIMIT 50", [$customerId]
        );
        $customer['proposals'] = Database::fetchAll(
            "SELECT id, uuid, proposal_number, title, status, created_at FROM proposals WHERE customer_id = ? AND deleted_at IS NULL ORDER BY created_at DESC",
            [$customerId]
        );
        $customer['quotations'] = Database::fetchAll(
            "SELECT id, uuid, quote_number, total, status, created_at FROM quotations WHERE customer_id = ? AND deleted_at IS NULL ORDER BY created_at DESC",
            [$customerId]
        );
        $customer['invoices'] = Database::fetchAll(
            "SELECT id, uuid, invoice_number, total, amount_paid, status, created_at FROM invoices WHERE customer_id = ? AND deleted_at IS NULL ORDER BY created_at DESC",
            [$customerId]
        );
        $customer['tasks'] = Database::fetchAll(
            "SELECT id, title, status, due_at FROM tasks WHERE related_type = 'customer' AND related_id = ? AND deleted_at IS NULL ORDER BY created_at DESC",
            [$customerId]
        );

        return $customer;
    }

    public function create(int $businessId, array $data): array
    {
        $uuid = $this->uuid4();
        Database::query(
            "INSERT INTO customers (uuid, business_id, name, email, phone, company, address, city, state, country, created_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())",
            [
                $uuid, $businessId,
                Security::cleanString($data['name']),
                Security::cleanEmail($data['email'] ?? '') ?: null,
                Security::cleanString($data['phone'] ?? '') ?: null,
                Security::cleanString($data['company'] ?? '') ?: null,
                Security::cleanString($data['address'] ?? '') ?: null,
                Security::cleanString($data['city'] ?? '') ?: null,
                Security::cleanString($data['state'] ?? '') ?: null,
                Security::cleanString($data['country'] ?? '') ?: null,
            ]
        );
        $customerId = (int) Database::lastInsertId();
        $this->logActivity($customerId, null, 'customer_created', ['source' => 'manual']);
        AutomationService::trigger($businessId, 'customer.created', ['customer_id' => $customerId]);
        return $this->find($businessId, $customerId);
    }

    public function update(int $businessId, int $customerId, array $data): array
    {
        $existing = $this->find($businessId, $customerId);
        if ($existing === null) {
            Response::notFound('Customer not found.');
        }

        $allowed = ['name', 'email', 'phone', 'company', 'address', 'city', 'state', 'country'];
        $sets = [];
        $params = [];
        foreach ($allowed as $col) {
            if (array_key_exists($col, $data)) {
                $sets[] = "{$col} = ?";
                $value = $col === 'email' ? Security::cleanEmail((string) $data[$col]) : Security::cleanString((string) $data[$col]);
                $params[] = $value ?: null;
            }
        }
        if (!empty($sets)) {
            $params[] = $customerId;
            Database::query("UPDATE customers SET " . implode(', ', $sets) . " WHERE id = ?", $params);
        }

        return $this->find($businessId, $customerId);
    }

    public function delete(int $businessId, int $customerId, int $userId): void
    {
        $existing = $this->find($businessId, $customerId);
        if ($existing === null) {
            Response::notFound('Customer not found.');
        }
        Database::query("UPDATE customers SET deleted_at = NOW() WHERE id = ? AND business_id = ?", [$customerId, $businessId]);
        AuditLogger::log($userId, $businessId, 'customer_deleted', ['customer_id' => $customerId]);
    }

    public function addNote(int $businessId, int $customerId, int $userId, string $note): array
    {
        $customer = $this->find($businessId, $customerId);
        if ($customer === null) {
            Response::notFound('Customer not found.');
        }
        Database::query(
            "INSERT INTO customer_notes (customer_id, user_id, note, created_at) VALUES (?, ?, ?, NOW())",
            [$customerId, $userId, Security::cleanString($note)]
        );
        // Capture the id BEFORE logActivity() writes to customer_activities,
        // otherwise lastInsertId() returns the activity id and this returns null.
        $noteId = (int) Database::lastInsertId();

        $this->logActivity($customerId, $userId, 'note', ['note' => $note]);

        return Database::fetchOne("SELECT * FROM customer_notes WHERE id = ?", [$noteId]);
    }

    private function logActivity(int $customerId, ?int $userId, string $type, array $metadata = []): void
    {
        Database::query(
            "INSERT INTO customer_activities (customer_id, user_id, activity_type, metadata, created_at) VALUES (?, ?, ?, ?, NOW())",
            [$customerId, $userId, $type, json_encode($metadata)]
        );
    }

    private function uuid4(): string
    {
        $data = random_bytes(16);
        $data[6] = chr(ord($data[6]) & 0x0f | 0x40);
        $data[8] = chr(ord($data[8]) & 0x3f | 0x80);
        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
    }
}
