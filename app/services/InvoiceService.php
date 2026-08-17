<?php
/**
 * Invoice management: CRUD with server-side calculated totals, optional
 * conversion from an accepted quotation, and payment tracking
 * (amount_paid / status) used by PaymentService webhooks.
 */
final class InvoiceService
{
    public function list(int $businessId, array $filters, int $page, int $perPage): array
    {
        $page = max(1, $page);
        $perPage = max(1, min(100, $perPage));
        $offset = ($page - 1) * $perPage;

        $where = ["i.business_id = ?", "i.deleted_at IS NULL"];
        $params = [$businessId];
        if (!empty($filters['search'])) {
            $where[] = "i.invoice_number LIKE ?";
            $params[] = '%' . $filters['search'] . '%';
        }
        if (!empty($filters['status'])) {
            $where[] = "i.status = ?";
            $params[] = $filters['status'];
        }
        $whereSql = implode(' AND ', $where);

        $total = (int) (Database::fetchOne("SELECT COUNT(*) c FROM invoices i WHERE {$whereSql}", $params)['c'] ?? 0);
        $rows = Database::fetchAll(
            "SELECT i.*, c.name AS customer_name FROM invoices i LEFT JOIN customers c ON c.id = i.customer_id
             WHERE {$whereSql} ORDER BY i.created_at DESC LIMIT {$perPage} OFFSET {$offset}",
            $params
        );

        return ['items' => $rows, 'pagination' => ['page' => $page, 'per_page' => $perPage, 'total' => $total, 'total_pages' => (int) ceil($total / $perPage)]];
    }

    public function find(int $businessId, int $id): ?array
    {
        $invoice = Database::fetchOne(
            "SELECT i.*, c.name AS customer_name, c.email AS customer_email FROM invoices i
             LEFT JOIN customers c ON c.id = i.customer_id WHERE i.id = ? AND i.business_id = ? AND i.deleted_at IS NULL",
            [$id, $businessId]
        );
        if ($invoice === null) {
            return null;
        }
        $invoice['items'] = Database::fetchAll("SELECT * FROM invoice_items WHERE invoice_id = ? ORDER BY sort_order ASC", [$id]);
        return $invoice;
    }

    public function create(int $businessId, int $userId, array $data): array
    {
        $uuid = $this->uuid4();
        $number = DocumentNumberingService::next($businessId, 'invoice');
        $items = $data['items'] ?? [];
        [$subtotal, $taxTotal, $grandTotal, $computedItems] = $this->calculateTotals($items);

        Database::query(
            "INSERT INTO invoices (uuid, business_id, customer_id, quotation_id, invoice_number, invoice_date, due_date,
                    subtotal, discount_amount, tax_amount, total, amount_paid, status, notes, created_by, created_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, 0, ?, ?, 0, 'draft', ?, ?, NOW())",
            [
                $uuid, $businessId, $data['customer_id'] ?? null, $data['quotation_id'] ?? null, $number,
                $data['invoice_date'] ?? date('Y-m-d'), $data['due_date'] ?? null,
                $subtotal, $taxTotal, $grandTotal, $data['notes'] ?? null, $userId,
            ]
        );
        $id = (int) Database::lastInsertId();
        $this->saveItems($id, $computedItems);

        AuditLogger::log($userId, $businessId, 'invoice_created', ['invoice_id' => $id]);

        return $this->find($businessId, $id);
    }

    public function createFromQuotation(int $businessId, int $userId, int $quotationId): array
    {
        $quotation = Database::fetchOne("SELECT * FROM quotations WHERE id = ? AND business_id = ?", [$quotationId, $businessId]);
        if ($quotation === null) {
            Response::notFound('Quotation not found.');
        }
        $items = Database::fetchAll("SELECT * FROM quotation_items WHERE quotation_id = ?", [$quotationId]);

        return $this->create($businessId, $userId, [
            'customer_id' => $quotation['customer_id'],
            'quotation_id' => $quotationId,
            'due_date' => date('Y-m-d', strtotime('+15 days')),
            'notes' => $quotation['notes'],
            'items' => array_map(fn ($i) => [
                'name' => $i['name'], 'description' => $i['description'],
                'quantity' => $i['quantity'], 'unit_price' => $i['unit_price'], 'tax_percent' => $i['tax_percent'],
            ], $items),
        ]);
    }

    public function recordPayment(int $businessId, int $invoiceId, float $amount): array
    {
        $invoice = $this->find($businessId, $invoiceId);
        if ($invoice === null) {
            Response::notFound('Invoice not found.');
        }

        $newPaid = (float) $invoice['amount_paid'] + $amount;
        $status = $newPaid >= (float) $invoice['total'] ? 'paid' : 'partially_paid';

        Database::query("UPDATE invoices SET amount_paid = ?, status = ? WHERE id = ?", [$newPaid, $status, $invoiceId]);

        return $this->find($businessId, $invoiceId);
    }

    public function delete(int $businessId, int $id): void
    {
        Database::query("UPDATE invoices SET deleted_at = NOW() WHERE id = ? AND business_id = ?", [$id, $businessId]);
    }

    /** @return array{0:float,1:float,2:float,3:array} */
    private function calculateTotals(array $items): array
    {
        $subtotal = 0.0;
        $taxTotal = 0.0;
        $computed = [];

        foreach ($items as $item) {
            $qty = (float) ($item['quantity'] ?? 1);
            $unitPrice = (float) ($item['unit_price'] ?? 0);
            $taxPct = (float) ($item['tax_percent'] ?? 0);

            $lineSubtotal = $qty * $unitPrice;
            $lineTax = $lineSubtotal * ($taxPct / 100);

            $subtotal += $lineSubtotal;
            $taxTotal += $lineTax;

            $computed[] = array_merge($item, ['computed_total' => round($lineSubtotal + $lineTax, 2)]);
        }

        $grandTotal = round($subtotal + $taxTotal, 2);
        return [round($subtotal, 2), round($taxTotal, 2), $grandTotal, $computed];
    }

    private function saveItems(int $invoiceId, array $items): void
    {
        foreach ($items as $i => $item) {
            Database::query(
                "INSERT INTO invoice_items (invoice_id, name, description, quantity, unit_price, tax_percent, total, sort_order)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?)",
                [
                    $invoiceId, Security::cleanString($item['name'] ?? ''), $item['description'] ?? null,
                    (float) ($item['quantity'] ?? 1), (float) ($item['unit_price'] ?? 0), (float) ($item['tax_percent'] ?? 0),
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
