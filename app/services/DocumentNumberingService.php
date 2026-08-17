<?php
/**
 * Generates sequential, business-scoped document numbers (e.g.
 * PROP-0001, QUO-0001, INV-0001), using the invoice/quote prefix
 * configured in business_settings if present, falling back to sane
 * defaults. Numbers are unique per business (enforced by DB unique key).
 */
final class DocumentNumberingService
{
    public static function next(int $businessId, string $type): string
    {
        $prefixKey = match ($type) {
            'proposal' => 'proposal_prefix',
            'quotation' => 'quote_prefix',
            'invoice' => 'invoice_prefix',
            default => 'doc_prefix',
        };
        $defaultPrefix = match ($type) {
            'proposal' => 'PROP',
            'quotation' => 'QUO',
            'invoice' => 'INV',
            default => 'DOC',
        };

        $row = Database::fetchOne(
            "SELECT setting_value FROM business_settings WHERE business_id = ? AND setting_key = ?",
            [$businessId, $prefixKey]
        );
        $prefix = $row['setting_value'] ?? $defaultPrefix;

        $table = match ($type) {
            'proposal' => 'proposals',
            'quotation' => 'quotations',
            'invoice' => 'invoices',
            default => null,
        };
        $numberColumn = match ($type) {
            'proposal' => 'proposal_number',
            'quotation' => 'quote_number',
            'invoice' => 'invoice_number',
            default => null,
        };

        $next = 1;
        if ($table !== null) {
            // Extract the numeric suffix from the highest existing number for
            // this business+prefix, rather than a plain COUNT(*), so gaps left
            // by deleted records don't cause number collisions.
            $row = Database::fetchOne(
                "SELECT {$numberColumn} AS num FROM {$table}
                 WHERE business_id = ? AND {$numberColumn} LIKE ?
                 ORDER BY id DESC LIMIT 1",
                [$businessId, $prefix . '-%']
            );
            if ($row !== null && preg_match('/-(\d+)$/', (string) $row['num'], $m)) {
                $next = ((int) $m[1]) + 1;
            }
        }

        return sprintf('%s-%04d', $prefix, $next);
    }
}
