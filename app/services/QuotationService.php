<?php
/**
 * Quotation builder (spec #21): CRUD with server-side calculated totals
 * (never trust client-computed totals) + "Generate Quote" AI helper that
 * uses actual business_products data where available.
 */
final class QuotationService
{
    public function list(int $businessId, array $filters, int $page, int $perPage): array
    {
        $page = max(1, $page);
        $perPage = max(1, min(100, $perPage));
        $offset = ($page - 1) * $perPage;

        $where = ["q.business_id = ?", "q.deleted_at IS NULL"];
        $params = [$businessId];
        if (!empty($filters['search'])) {
            $where[] = "q.quote_number LIKE ?";
            $params[] = '%' . $filters['search'] . '%';
        }
        if (!empty($filters['status'])) {
            $where[] = "q.status = ?";
            $params[] = $filters['status'];
        }
        $whereSql = implode(' AND ', $where);

        $total = (int) (Database::fetchOne("SELECT COUNT(*) c FROM quotations q WHERE {$whereSql}", $params)['c'] ?? 0);
        $rows = Database::fetchAll(
            "SELECT q.*, c.name AS customer_name FROM quotations q LEFT JOIN customers c ON c.id = q.customer_id
             WHERE {$whereSql} ORDER BY q.created_at DESC LIMIT {$perPage} OFFSET {$offset}",
            $params
        );

        return ['items' => $rows, 'pagination' => ['page' => $page, 'per_page' => $perPage, 'total' => $total, 'total_pages' => (int) ceil($total / $perPage)]];
    }

    public function find(int $businessId, int $id): ?array
    {
        $quotation = Database::fetchOne(
            "SELECT q.*, c.name AS customer_name, c.email AS customer_email FROM quotations q
             LEFT JOIN customers c ON c.id = q.customer_id WHERE q.id = ? AND q.business_id = ? AND q.deleted_at IS NULL",
            [$id, $businessId]
        );
        if ($quotation === null) {
            return null;
        }
        $quotation['items'] = Database::fetchAll("SELECT * FROM quotation_items WHERE quotation_id = ? ORDER BY sort_order ASC", [$id]);
        return $quotation;
    }

    public function create(int $businessId, int $userId, array $data): array
    {
        $uuid = $this->uuid4();
        $number = DocumentNumberingService::next($businessId, 'quotation');
        $items = $data['items'] ?? [];
        [$subtotal, $discountTotal, $taxTotal, $grandTotal, $computedItems] = $this->calculateTotals($items);

        Database::query(
            "INSERT INTO quotations (uuid, business_id, customer_id, lead_id, quote_number, quote_date, expiry_date,
                    subtotal, discount_amount, tax_amount, total, notes, terms, status, generated_by_ai, created_by, created_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'draft', ?, ?, NOW())",
            [
                $uuid, $businessId, $data['customer_id'] ?? null, $data['lead_id'] ?? null, $number,
                $data['quote_date'] ?? date('Y-m-d'), $data['expiry_date'] ?? null,
                $subtotal, $discountTotal, $taxTotal, $grandTotal,
                $data['notes'] ?? null, $data['terms'] ?? null, !empty($data['generated_by_ai']) ? 1 : 0, $userId,
            ]
        );
        $id = (int) Database::lastInsertId();
        $this->saveItems($id, $computedItems);

        AuditLogger::log($userId, $businessId, 'quotation_created', ['quotation_id' => $id]);

        return $this->find($businessId, $id);
    }

    public function update(int $businessId, int $id, array $data): array
    {
        $existing = $this->find($businessId, $id);
        if ($existing === null) {
            Response::notFound('Quotation not found.');
        }

        $sets = [];
        $params = [];
        foreach (['customer_id', 'quote_date', 'expiry_date', 'notes', 'terms', 'status'] as $col) {
            if (array_key_exists($col, $data)) {
                $sets[] = "{$col} = ?";
                $params[] = $data[$col];
            }
        }

        if (isset($data['items']) && is_array($data['items'])) {
            [$subtotal, $discountTotal, $taxTotal, $grandTotal, $computedItems] = $this->calculateTotals($data['items']);
            $sets[] = "subtotal = ?"; $params[] = $subtotal;
            $sets[] = "discount_amount = ?"; $params[] = $discountTotal;
            $sets[] = "tax_amount = ?"; $params[] = $taxTotal;
            $sets[] = "total = ?"; $params[] = $grandTotal;
            Database::query("DELETE FROM quotation_items WHERE quotation_id = ?", [$id]);
            $this->saveItems($id, $computedItems);
        }

        if (!empty($sets)) {
            $params[] = $id;
            Database::query("UPDATE quotations SET " . implode(', ', $sets) . " WHERE id = ?", $params);
        }

        return $this->find($businessId, $id);
    }

    public function delete(int $businessId, int $id): void
    {
        Database::query("UPDATE quotations SET deleted_at = NOW() WHERE id = ? AND business_id = ?", [$id, $businessId]);
    }

    /**
     * "Generate Quote" AI helper - uses real business_products/services
     * data so the AI is grounded in actual catalog info, not fabricated
     * pricing.
     */
    public function generateWithAI(int $businessId, ?int $userId, array $input): array
    {
        $products = Database::fetchAll("SELECT name, price FROM business_products WHERE business_id = ? AND is_active = 1 LIMIT 30", [$businessId]);
        $services = Database::fetchAll("SELECT name, price FROM business_services WHERE business_id = ? AND is_active = 1 LIMIT 30", [$businessId]);
        $catalog = array_merge(
            array_map(fn ($p) => "{$p['name']}: {$p['price']}", $products),
            array_map(fn ($s) => "{$s['name']}: {$s['price']}", $services)
        );

        $prompt = "Available products/services (name: price):\n" . implode("\n", $catalog) . "\n\n" .
            "Requirement: " . ($input['requirement'] ?? '');

        $schema = ['items' => [['name' => 'string', 'quantity' => 'number', 'unit_price' => 'number']], 'notes' => 'string'];

        $ai = new AIService();
        try {
            $result = $ai->generateStructuredData($businessId, $userId, 'generate_quote', $prompt, $schema);
        } catch (\Throwable $e) {
            Response::error('AI quote generation failed: ' . $e->getMessage(), [], 502);
        }

        return $this->create($businessId, $userId, array_merge($input, [
            'items' => $result['items'] ?? [],
            'notes' => $result['notes'] ?? null,
            'generated_by_ai' => true,
        ]));
    }

    /**
     * @param array<int,array{name?:string,description?:string,quantity?:mixed,unit_price?:mixed,discount_percent?:mixed,tax_percent?:mixed,product_id?:int}> $items
     * @return array{0:float,1:float,2:float,3:float,4:array}
     */
    private function calculateTotals(array $items): array
    {
        $subtotal = 0.0;
        $discountTotal = 0.0;
        $taxTotal = 0.0;
        $computed = [];

        foreach ($items as $item) {
            $qty = (float) ($item['quantity'] ?? 1);
            $unitPrice = (float) ($item['unit_price'] ?? 0);
            $discountPct = (float) ($item['discount_percent'] ?? 0);
            $taxPct = (float) ($item['tax_percent'] ?? 0);

            $lineSubtotal = $qty * $unitPrice;
            $lineDiscount = $lineSubtotal * ($discountPct / 100);
            $afterDiscount = $lineSubtotal - $lineDiscount;
            $lineTax = $afterDiscount * ($taxPct / 100);
            $lineTotal = $afterDiscount + $lineTax;

            $subtotal += $lineSubtotal;
            $discountTotal += $lineDiscount;
            $taxTotal += $lineTax;

            $computed[] = array_merge($item, ['computed_total' => round($lineTotal, 2)]);
        }

        $grandTotal = round($subtotal - $discountTotal + $taxTotal, 2);

        return [round($subtotal, 2), round($discountTotal, 2), round($taxTotal, 2), $grandTotal, $computed];
    }

    private function saveItems(int $quotationId, array $items): void
    {
        foreach ($items as $i => $item) {
            Database::query(
                "INSERT INTO quotation_items (quotation_id, product_id, name, description, quantity, unit_price, discount_percent, tax_percent, total, sort_order)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)",
                [
                    $quotationId, $item['product_id'] ?? null, Security::cleanString($item['name'] ?? ''), $item['description'] ?? null,
                    (float) ($item['quantity'] ?? 1), (float) ($item['unit_price'] ?? 0),
                    (float) ($item['discount_percent'] ?? 0), (float) ($item['tax_percent'] ?? 0),
                    $item['computed_total'] ?? 0, $i,
                ]
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
