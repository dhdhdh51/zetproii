<?php
/**
 * Proposal builder (spec #20): CRUD + "Generate Proposal with AI" which
 * produces editable structured content using business/customer context.
 */
final class ProposalService
{
    public function list(int $businessId, array $filters, int $page, int $perPage): array
    {
        $page = max(1, $page);
        $perPage = max(1, min(100, $perPage));
        $offset = ($page - 1) * $perPage;

        $where = ["p.business_id = ?", "p.deleted_at IS NULL"];
        $params = [$businessId];
        if (!empty($filters['search'])) {
            $where[] = "(p.title LIKE ? OR p.proposal_number LIKE ?)";
            $term = '%' . $filters['search'] . '%';
            array_push($params, $term, $term);
        }
        if (!empty($filters['status'])) {
            $where[] = "p.status = ?";
            $params[] = $filters['status'];
        }
        $whereSql = implode(' AND ', $where);

        $total = (int) (Database::fetchOne("SELECT COUNT(*) c FROM proposals p WHERE {$whereSql}", $params)['c'] ?? 0);
        $rows = Database::fetchAll(
            "SELECT p.*, c.name AS customer_name FROM proposals p LEFT JOIN customers c ON c.id = p.customer_id
             WHERE {$whereSql} ORDER BY p.created_at DESC LIMIT {$perPage} OFFSET {$offset}",
            $params
        );

        return ['items' => $rows, 'pagination' => ['page' => $page, 'per_page' => $perPage, 'total' => $total, 'total_pages' => (int) ceil($total / $perPage)]];
    }

    public function find(int $businessId, int $id): ?array
    {
        $proposal = Database::fetchOne(
            "SELECT p.*, c.name AS customer_name, c.email AS customer_email FROM proposals p
             LEFT JOIN customers c ON c.id = p.customer_id WHERE p.id = ? AND p.business_id = ? AND p.deleted_at IS NULL",
            [$id, $businessId]
        );
        if ($proposal === null) {
            return null;
        }
        $proposal['items'] = Database::fetchAll("SELECT * FROM proposal_items WHERE proposal_id = ? ORDER BY sort_order ASC", [$id]);
        return $proposal;
    }

    public function create(int $businessId, int $userId, array $data): array
    {
        $uuid = $this->uuid4();
        $number = DocumentNumberingService::next($businessId, 'proposal');

        Database::query(
            "INSERT INTO proposals (uuid, business_id, customer_id, lead_id, proposal_number, title, introduction,
                    problem_statement, solution, scope, deliverables, timeline, pricing_summary, terms, valid_until,
                    notes, status, generated_by_ai, created_by, created_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'draft', ?, ?, NOW())",
            [
                $uuid, $businessId, $data['customer_id'] ?? null, $data['lead_id'] ?? null, $number,
                Security::cleanString($data['title'] ?? 'Untitled Proposal'),
                $data['introduction'] ?? null, $data['problem_statement'] ?? null, $data['solution'] ?? null,
                $data['scope'] ?? null, $data['deliverables'] ?? null, $data['timeline'] ?? null,
                $data['pricing_summary'] ?? null, $data['terms'] ?? null, $data['valid_until'] ?? null,
                $data['notes'] ?? null, !empty($data['generated_by_ai']) ? 1 : 0, $userId,
            ]
        );
        $id = (int) Database::lastInsertId();

        if (!empty($data['items']) && is_array($data['items'])) {
            $this->saveItems($id, $data['items']);
        }

        AuditLogger::log($userId, $businessId, 'proposal_created', ['proposal_id' => $id]);
        AutomationService::trigger($businessId, 'proposal.created', ['proposal_id' => $id]);

        return $this->find($businessId, $id);
    }

    public function update(int $businessId, int $id, array $data): array
    {
        $existing = $this->find($businessId, $id);
        if ($existing === null) {
            Response::notFound('Proposal not found.');
        }

        $allowed = ['customer_id', 'title', 'introduction', 'problem_statement', 'solution', 'scope',
                    'deliverables', 'timeline', 'pricing_summary', 'terms', 'valid_until', 'notes', 'status'];
        $sets = [];
        $params = [];
        foreach ($allowed as $col) {
            if (array_key_exists($col, $data)) {
                $sets[] = "{$col} = ?";
                $params[] = $data[$col];
            }
        }
        if (!empty($sets)) {
            $params[] = $id;
            Database::query("UPDATE proposals SET " . implode(', ', $sets) . " WHERE id = ?", $params);
        }

        if (isset($data['items']) && is_array($data['items'])) {
            Database::query("DELETE FROM proposal_items WHERE proposal_id = ?", [$id]);
            $this->saveItems($id, $data['items']);
        }

        return $this->find($businessId, $id);
    }

    public function delete(int $businessId, int $id): void
    {
        Database::query("UPDATE proposals SET deleted_at = NOW() WHERE id = ? AND business_id = ?", [$id, $businessId]);
    }

    /**
     * "Generate Proposal with AI" - produces full structured content
     * using business context + optional customer/lead context.
     */
    public function generateWithAI(int $businessId, ?int $userId, array $input): array
    {
        $business = Database::fetchOne("SELECT name, industry, about, unique_selling_points FROM businesses WHERE id = ?", [$businessId]);
        $customerContext = '';
        if (!empty($input['customer_id'])) {
            $customer = Database::fetchOne("SELECT name, company FROM customers WHERE id = ? AND business_id = ?", [$input['customer_id'], $businessId]);
            if ($customer !== null) {
                $customerContext = "Client: {$customer['name']}" . ($customer['company'] ? " ({$customer['company']})" : '');
            }
        }

        $prompt = "Business: {$business['name']} ({$business['industry']}). " . ($business['about'] ?? '') . "\n" .
            "USPs: " . ($business['unique_selling_points'] ?? '') . "\n{$customerContext}\n" .
            "Project/requirement: " . ($input['requirement'] ?? $input['title'] ?? '');

        $schema = [
            'title' => 'string', 'introduction' => 'string', 'problem_statement' => 'string',
            'solution' => 'string', 'scope' => 'string', 'deliverables' => 'string',
            'timeline' => 'string', 'pricing_summary' => 'string', 'terms' => 'string',
        ];

        $ai = new AIService();
        try {
            $result = $ai->generateStructuredData($businessId, $userId, 'generate_proposal', $prompt, $schema);
        } catch (\Throwable $e) {
            Response::error('AI proposal generation failed: ' . $e->getMessage(), [], 502);
        }

        return $this->create($businessId, $userId, array_merge($input, $result, ['generated_by_ai' => true]));
    }

    private function saveItems(int $proposalId, array $items): void
    {
        foreach ($items as $i => $item) {
            $qty = (float) ($item['quantity'] ?? 1);
            $price = (float) ($item['unit_price'] ?? 0);
            Database::query(
                "INSERT INTO proposal_items (proposal_id, name, description, quantity, unit_price, total, sort_order)
                 VALUES (?, ?, ?, ?, ?, ?, ?)",
                [$proposalId, Security::cleanString($item['name'] ?? ''), $item['description'] ?? null, $qty, $price, $qty * $price, $i]
            );
        }
    }

    private function uuid4(): string
    {
        $data = random_bytes(16);
        $data[6] = chr(ord($data[6]) & 0x0f | 0x40);
        $data[8] = chr(ord($data[8]) & 0x3f | 0x80);
        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
    }
}
