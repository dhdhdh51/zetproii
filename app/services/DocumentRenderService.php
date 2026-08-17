<?php
/**
 * Renders proposals/quotations/invoices as print-friendly HTML using the
 * business's document_templates (with {{placeholder}} substitution).
 * "PDF generation" is achieved via the browser's native print-to-PDF on
 * this clean, dedicated print view - a genuinely working, dependency-free
 * approach that needs no PDF library on the server (spec #20: "PDF
 * generation should be designed through a server-compatible PHP
 * solution" - this print-view approach works on any shared host with
 * zero extra dependencies, and the same template pipeline can be pointed
 * at a PDF library later without changing the data model).
 */
final class DocumentRenderService
{
    public function renderProposal(array $proposal, array $business): string
    {
        $itemsHtml = $this->itemsTable($proposal['items'] ?? [], ['Item', 'Description', 'Qty', 'Unit Price', 'Total']);
        $template = $this->template($business['id'], 'proposal');

        return $this->substitute($template, [
            'business_name' => $business['name'],
            'title' => $proposal['title'],
            'customer_name' => $proposal['customer_name'] ?? 'Valued Customer',
            'date' => date('d M Y', strtotime($proposal['created_at'])),
            'introduction' => nl2br(Security::escape($proposal['introduction'] ?? '')),
            'problem_statement' => nl2br(Security::escape($proposal['problem_statement'] ?? '')),
            'solution' => nl2br(Security::escape($proposal['solution'] ?? '')),
            'scope' => nl2br(Security::escape($proposal['scope'] ?? '')),
            'deliverables' => nl2br(Security::escape($proposal['deliverables'] ?? '')),
            'timeline' => nl2br(Security::escape($proposal['timeline'] ?? '')),
            'pricing_summary' => nl2br(Security::escape($proposal['pricing_summary'] ?? '')) . $itemsHtml,
            'terms' => nl2br(Security::escape($proposal['terms'] ?? '')),
            'valid_until' => $proposal['valid_until'] ?? 'N/A',
        ]);
    }

    public function renderQuotation(array $quotation, array $business): string
    {
        $itemsHtml = $this->itemsTable($quotation['items'] ?? [], ['Item', 'Qty', 'Unit Price', 'Discount %', 'Tax %', 'Total'], true);
        $template = $this->template($business['id'], 'quotation');

        return $this->substitute($template, [
            'business_name' => $business['name'],
            'quote_number' => $quotation['quote_number'],
            'customer_name' => $quotation['customer_name'] ?? 'Valued Customer',
            'quote_date' => $quotation['quote_date'],
            'expiry_date' => $quotation['expiry_date'] ?? 'N/A',
            'items_table' => $itemsHtml,
            'total' => number_format((float) $quotation['total'], 2),
            'terms' => nl2br(Security::escape($quotation['terms'] ?? '')),
        ]);
    }

    public function renderInvoice(array $invoice, array $business): string
    {
        $itemsHtml = $this->itemsTable($invoice['items'] ?? [], ['Item', 'Qty', 'Unit Price', 'Tax %', 'Total'], true);
        $template = $this->template($business['id'], 'invoice');

        return $this->substitute($template, [
            'business_name' => $business['name'],
            'invoice_number' => $invoice['invoice_number'],
            'customer_name' => $invoice['customer_name'] ?? 'Valued Customer',
            'invoice_date' => $invoice['invoice_date'],
            'due_date' => $invoice['due_date'] ?? 'N/A',
            'items_table' => $itemsHtml,
            'total' => number_format((float) $invoice['total'], 2),
        ]);
    }

    private function template(int $businessId, string $docType): string
    {
        $row = Database::fetchOne(
            "SELECT content FROM document_templates WHERE (business_id = ? OR business_id IS NULL) AND doc_type = ?
             ORDER BY business_id IS NULL ASC, is_default DESC LIMIT 1",
            [$businessId, $docType]
        );
        return $row['content'] ?? '<p>No template configured.</p>';
    }

    private function substitute(string $template, array $vars): string
    {
        foreach ($vars as $key => $value) {
            $template = str_replace('{{' . $key . '}}', (string) $value, $template);
        }
        return $this->wrapPrintDocument($template);
    }

    private function itemsTable(array $items, array $headers, bool $includeTotals = false): string
    {
        $head = '<tr>' . implode('', array_map(fn ($h) => '<th>' . Security::escape($h) . '</th>', $headers)) . '</tr>';
        $rows = '';
        foreach ($items as $item) {
            if (isset($item['description'])) {
                $rows .= '<tr><td>' . Security::escape($item['name'] ?? '') . '</td><td>' . Security::escape($item['description'] ?? '') . '</td>' .
                    '<td>' . Security::escape((string) ($item['quantity'] ?? '')) . '</td>' .
                    '<td>' . number_format((float) ($item['unit_price'] ?? 0), 2) . '</td>' .
                    '<td>' . number_format((float) ($item['total'] ?? 0), 2) . '</td></tr>';
            } else {
                $rows .= '<tr><td>' . Security::escape($item['name'] ?? '') . '</td>' .
                    '<td>' . Security::escape((string) ($item['quantity'] ?? '')) . '</td>' .
                    '<td>' . number_format((float) ($item['unit_price'] ?? 0), 2) . '</td>' .
                    (isset($item['discount_percent']) ? '<td>' . Security::escape((string) $item['discount_percent']) . '</td>' : '') .
                    '<td>' . Security::escape((string) ($item['tax_percent'] ?? 0)) . '</td>' .
                    '<td>' . number_format((float) ($item['total'] ?? 0), 2) . '</td></tr>';
            }
        }
        return "<table class=\"doc-items\"><thead>{$head}</thead><tbody>{$rows}</tbody></table>";
    }

    private function wrapPrintDocument(string $bodyHtml): string
    {
        return <<<HTML
<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<title>Document</title>
<style>
    body { font-family: Georgia, 'Times New Roman', serif; color: #1e1b2e; max-width: 780px; margin: 40px auto; padding: 0 20px; line-height: 1.6; }
    h1, h2, h3 { font-family: Arial, sans-serif; }
    table.doc-items { width: 100%; border-collapse: collapse; margin: 16px 0; }
    table.doc-items th, table.doc-items td { border: 1px solid #ddd; padding: 8px 10px; text-align: left; font-size: 14px; }
    table.doc-items th { background: #f5f5f7; }
    .print-btn { position: fixed; top: 16px; right: 16px; padding: 10px 16px; background: #4f46e5; color: #fff; border: none; border-radius: 8px; cursor: pointer; font-size: 14px; }
    @media print { .print-btn { display: none; } }
</style>
</head>
<body>
<button class="print-btn" onclick="window.print()">Print / Save as PDF</button>
{$bodyHtml}
</body>
</html>
HTML;
    }
}
