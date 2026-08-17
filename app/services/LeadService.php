<?php
/**
 * Lead CRM business logic: CRUD, filtering/pagination, notes, activity
 * timeline, tagging, and status transitions. All queries are scoped to
 * business_id which the caller must have already verified via
 * AuthMiddleware::requireBusinessAccess().
 */
final class LeadService
{
    public function list(int $businessId, array $filters, int $page, int $perPage): array
    {
        $page = max(1, $page);
        $perPage = max(1, min(100, $perPage));
        $offset = ($page - 1) * $perPage;

        $where = ["l.business_id = ?", "l.deleted_at IS NULL"];
        $params = [$businessId];

        if (!empty($filters['search'])) {
            $where[] = "(l.name LIKE ? OR l.email LIKE ? OR l.phone LIKE ? OR l.company LIKE ?)";
            $term = '%' . $filters['search'] . '%';
            array_push($params, $term, $term, $term, $term);
        }
        if (!empty($filters['status_id'])) {
            $where[] = "l.status_id = ?";
            $params[] = $filters['status_id'];
        }
        if (!empty($filters['source_id'])) {
            $where[] = "l.source_id = ?";
            $params[] = $filters['source_id'];
        }
        if (!empty($filters['priority'])) {
            $where[] = "l.priority = ?";
            $params[] = $filters['priority'];
        }
        if (!empty($filters['assigned_user_id'])) {
            $where[] = "l.assigned_user_id = ?";
            $params[] = $filters['assigned_user_id'];
        }
        if (!empty($filters['date_from'])) {
            $where[] = "l.created_at >= ?";
            $params[] = $filters['date_from'] . ' 00:00:00';
        }
        if (!empty($filters['date_to'])) {
            $where[] = "l.created_at <= ?";
            $params[] = $filters['date_to'] . ' 23:59:59';
        }

        $sortColumn = in_array($filters['sort'] ?? '', ['name', 'created_at', 'value', 'ai_score', 'next_followup_at'], true)
            ? $filters['sort'] : 'created_at';
        $sortDir = (($filters['dir'] ?? 'desc') === 'asc') ? 'ASC' : 'DESC';

        $whereSql = implode(' AND ', $where);

        $total = (int) (Database::fetchOne(
            "SELECT COUNT(*) c FROM leads l WHERE {$whereSql}", $params
        )['c'] ?? 0);

        $rows = Database::fetchAll(
            "SELECT l.*, s.name AS status_name, s.color AS status_color, s.is_won, s.is_lost,
                    src.name AS source_name, u.name AS assigned_user_name
             FROM leads l
             LEFT JOIN lead_statuses s ON s.id = l.status_id
             LEFT JOIN lead_sources src ON src.id = l.source_id
             LEFT JOIN users u ON u.id = l.assigned_user_id
             WHERE {$whereSql}
             ORDER BY l.{$sortColumn} {$sortDir}
             LIMIT {$perPage} OFFSET {$offset}",
            $params
        );

        return [
            'items' => $rows,
            'pagination' => [
                'page' => $page,
                'per_page' => $perPage,
                'total' => $total,
                'total_pages' => (int) ceil($total / $perPage),
            ],
        ];
    }

    public function find(int $businessId, int $leadId): ?array
    {
        $lead = Database::fetchOne(
            "SELECT l.*, s.name AS status_name, s.color AS status_color, src.name AS source_name, u.name AS assigned_user_name
             FROM leads l
             LEFT JOIN lead_statuses s ON s.id = l.status_id
             LEFT JOIN lead_sources src ON src.id = l.source_id
             LEFT JOIN users u ON u.id = l.assigned_user_id
             WHERE l.business_id = ? AND l.id = ? AND l.deleted_at IS NULL",
            [$businessId, $leadId]
        );
        if ($lead === null) {
            return null;
        }

        $lead['tags'] = Database::fetchAll(
            "SELECT t.id, t.name, t.color FROM lead_tag_relations ltr
             JOIN tags t ON t.id = ltr.tag_id WHERE ltr.lead_id = ?",
            [$leadId]
        );
        $lead['notes'] = Database::fetchAll(
            "SELECT ln.*, u.name AS user_name FROM lead_notes ln
             LEFT JOIN users u ON u.id = ln.user_id WHERE ln.lead_id = ? ORDER BY ln.created_at DESC",
            [$leadId]
        );
        $lead['activities'] = Database::fetchAll(
            "SELECT la.*, u.name AS user_name FROM lead_activities la
             LEFT JOIN users u ON u.id = la.user_id WHERE la.lead_id = ? ORDER BY la.created_at DESC LIMIT 50",
            [$leadId]
        );

        return $lead;
    }

    public function create(int $businessId, ?int $createdByUserId, array $data): array
    {
        $uuid = $this->uuid4();

        Database::query(
            "INSERT INTO leads (uuid, business_id, name, email, phone, company, source_id, status_id, priority,
                                 value, assigned_user_id, location, requirement, budget, next_followup_at, created_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())",
            [
                $uuid, $businessId,
                Security::cleanString($data['name']),
                Security::cleanEmail($data['email'] ?? '') ?: null,
                Security::cleanString($data['phone'] ?? '') ?: null,
                Security::cleanString($data['company'] ?? '') ?: null,
                $data['source_id'] ?? null,
                $data['status_id'] ?? $this->defaultStatusId($businessId),
                $data['priority'] ?? 'medium',
                $data['value'] ?? null,
                $data['assigned_user_id'] ?? null,
                Security::cleanString($data['location'] ?? '') ?: null,
                Security::cleanString($data['requirement'] ?? '') ?: null,
                Security::cleanString($data['budget'] ?? '') ?: null,
                $data['next_followup_at'] ?? null,
            ]
        );
        $leadId = (int) Database::lastInsertId();

        $this->logActivity($leadId, $createdByUserId, 'lead_created', 'Lead created');

        if (!empty($data['tag_ids']) && is_array($data['tag_ids'])) {
            $this->syncTags($businessId, $leadId, $data['tag_ids']);
        }

        AutomationService::trigger($businessId, 'lead.created', ['lead_id' => $leadId]);

        return $this->find($businessId, $leadId);
    }

    public function update(int $businessId, int $leadId, int $userId, array $data): array
    {
        $existing = $this->find($businessId, $leadId);
        if ($existing === null) {
            Response::notFound('Lead not found.');
        }

        $allowed = ['name', 'email', 'phone', 'company', 'source_id', 'status_id', 'priority',
                    'value', 'assigned_user_id', 'location', 'requirement', 'budget', 'next_followup_at'];
        $sets = [];
        $params = [];
        foreach ($allowed as $col) {
            if (array_key_exists($col, $data)) {
                $sets[] = "{$col} = ?";
                $value = $data[$col];
                if (in_array($col, ['name', 'company', 'location', 'requirement', 'budget'], true)) {
                    $value = Security::cleanString((string) $value) ?: null;
                } elseif ($col === 'email') {
                    $value = Security::cleanEmail((string) $value) ?: null;
                }
                $params[] = $value;
            }
        }

        if (!empty($sets)) {
            $params[] = $leadId;
            Database::query("UPDATE leads SET " . implode(', ', $sets) . " WHERE id = ?", $params);
        }

        if (isset($data['status_id']) && (int) $data['status_id'] !== (int) $existing['status_id']) {
            $newStatus = Database::fetchOne("SELECT name, is_won, is_lost FROM lead_statuses WHERE id = ?", [$data['status_id']]);
            $this->logActivity($leadId, $userId, 'status_change', 'Status changed to ' . ($newStatus['name'] ?? ''));
            if ($newStatus !== null) {
                if (!empty($newStatus['is_won'])) {
                    AutomationService::trigger($businessId, 'lead.won', ['lead_id' => $leadId]);
                }
                if ($newStatus['name'] === 'Qualified' || strtolower((string) $newStatus['name']) === 'qualified') {
                    AutomationService::trigger($businessId, 'lead.qualified', ['lead_id' => $leadId]);
                }
            }
        }

        if (array_key_exists('tag_ids', $data) && is_array($data['tag_ids'])) {
            $this->syncTags($businessId, $leadId, $data['tag_ids']);
        }

        AutomationService::trigger($businessId, 'lead.updated', ['lead_id' => $leadId]);

        return $this->find($businessId, $leadId);
    }

    public function delete(int $businessId, int $leadId, int $userId): void
    {
        $existing = $this->find($businessId, $leadId);
        if ($existing === null) {
            Response::notFound('Lead not found.');
        }
        Database::query("UPDATE leads SET deleted_at = NOW() WHERE id = ? AND business_id = ?", [$leadId, $businessId]);
        AuditLogger::log($userId, $businessId, 'lead_deleted', ['lead_id' => $leadId]);
    }

    public function addNote(int $businessId, int $leadId, int $userId, string $note): array
    {
        $lead = $this->find($businessId, $leadId);
        if ($lead === null) {
            Response::notFound('Lead not found.');
        }
        Database::query(
            "INSERT INTO lead_notes (lead_id, user_id, note, created_at) VALUES (?, ?, ?, NOW())",
            [$leadId, $userId, Security::cleanString($note)]
        );
        $this->logActivity($leadId, $userId, 'note', 'Note added');
        return Database::fetchOne("SELECT * FROM lead_notes WHERE id = ?", [(int) Database::lastInsertId()]);
    }

    public function convertToCustomer(int $businessId, int $leadId, int $userId): array
    {
        $lead = $this->find($businessId, $leadId);
        if ($lead === null) {
            Response::notFound('Lead not found.');
        }
        if (!empty($lead['converted_customer_id'])) {
            Response::error('This lead has already been converted to a customer.', [], 409);
        }

        $uuid = $this->uuid4();
        Database::query(
            "INSERT INTO customers (uuid, business_id, lead_id, name, email, phone, company, created_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, NOW())",
            [$uuid, $businessId, $leadId, $lead['name'], $lead['email'], $lead['phone'], $lead['company']]
        );
        $customerId = (int) Database::lastInsertId();

        Database::query("UPDATE leads SET converted_customer_id = ? WHERE id = ?", [$customerId, $leadId]);
        $this->logActivity($leadId, $userId, 'converted_to_customer', 'Converted to customer');

        AutomationService::trigger($businessId, 'customer.created', ['customer_id' => $customerId, 'lead_id' => $leadId]);

        return Database::fetchOne("SELECT * FROM customers WHERE id = ?", [$customerId]);
    }

    private function syncTags(int $businessId, int $leadId, array $tagIds): void
    {
        Database::query("DELETE FROM lead_tag_relations WHERE lead_id = ?", [$leadId]);
        foreach ($tagIds as $tagId) {
            // Verify the tag actually belongs to this business before linking.
            $tag = Database::fetchOne("SELECT id FROM tags WHERE id = ? AND business_id = ?", [$tagId, $businessId]);
            if ($tag !== null) {
                Database::query("INSERT IGNORE INTO lead_tag_relations (lead_id, tag_id) VALUES (?, ?)", [$leadId, $tagId]);
            }
        }
    }

    private function defaultStatusId(int $businessId): ?int
    {
        $row = Database::fetchOne(
            "SELECT id FROM lead_statuses WHERE (business_id = ? OR business_id IS NULL) AND slug = 'new'
             ORDER BY business_id IS NULL ASC LIMIT 1",
            [$businessId]
        );
        return $row['id'] ?? null;
    }

    private function logActivity(int $leadId, ?int $userId, string $type, string $description): void
    {
        Database::query(
            "INSERT INTO lead_activities (lead_id, user_id, activity_type, description, created_at) VALUES (?, ?, ?, ?, NOW())",
            [$leadId, $userId, $type, $description]
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
