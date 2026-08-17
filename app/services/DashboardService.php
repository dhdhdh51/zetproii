<?php
/**
 * Aggregates real database metrics for the main dashboard widgets and
 * charts. Every number here is a live query scoped to business_id -
 * nothing is mocked or hardcoded.
 */
final class DashboardService
{
    public function widgets(int $businessId): array
    {
        $totalLeads = (int) (Database::fetchOne(
            "SELECT COUNT(*) c FROM leads WHERE business_id = ? AND deleted_at IS NULL", [$businessId]
        )['c'] ?? 0);

        $newLeads = (int) (Database::fetchOne(
            "SELECT COUNT(*) c FROM leads WHERE business_id = ? AND deleted_at IS NULL AND created_at >= (NOW() - INTERVAL 7 DAY)",
            [$businessId]
        )['c'] ?? 0);

        $qualifiedLeads = (int) (Database::fetchOne(
            "SELECT COUNT(*) c FROM leads l JOIN lead_statuses s ON s.id = l.status_id
             WHERE l.business_id = ? AND l.deleted_at IS NULL AND s.slug = 'qualified'",
            [$businessId]
        )['c'] ?? 0);

        $totalCustomers = (int) (Database::fetchOne(
            "SELECT COUNT(*) c FROM customers WHERE business_id = ? AND deleted_at IS NULL", [$businessId]
        )['c'] ?? 0);

        $wonLeads = (int) (Database::fetchOne(
            "SELECT COUNT(*) c FROM leads l JOIN lead_statuses s ON s.id = l.status_id
             WHERE l.business_id = ? AND l.deleted_at IS NULL AND s.is_won = 1",
            [$businessId]
        )['c'] ?? 0);

        $conversionRate = $totalLeads > 0 ? round(($wonLeads / $totalLeads) * 100, 1) : 0.0;

        $aiUsageThisMonth = (int) (Database::fetchOne(
            "SELECT COALESCE(SUM(total_tokens),0) c FROM ai_usage
             WHERE business_id = ? AND created_at >= DATE_FORMAT(NOW(), '%Y-%m-01')",
            [$businessId]
        )['c'] ?? 0);

        $emailsSent = (int) (Database::fetchOne(
            "SELECT COUNT(*) c FROM email_logs WHERE business_id = ? AND status = 'sent' AND created_at >= (NOW() - INTERVAL 30 DAY)",
            [$businessId]
        )['c'] ?? 0);

        $followupsDue = (int) (Database::fetchOne(
            "SELECT COUNT(*) c FROM followups WHERE business_id = ? AND status = 'pending' AND scheduled_at <= NOW()",
            [$businessId]
        )['c'] ?? 0);

        $revenue = (float) (Database::fetchOne(
            "SELECT COALESCE(SUM(amount_paid),0) c FROM invoices WHERE business_id = ? AND deleted_at IS NULL",
            [$businessId]
        )['c'] ?? 0);

        $pendingTasks = (int) (Database::fetchOne(
            "SELECT COUNT(*) c FROM tasks WHERE business_id = ? AND status IN ('pending','in_progress') AND deleted_at IS NULL",
            [$businessId]
        )['c'] ?? 0);

        return [
            'total_leads' => $totalLeads,
            'new_leads' => $newLeads,
            'qualified_leads' => $qualifiedLeads,
            'total_customers' => $totalCustomers,
            'conversion_rate' => $conversionRate,
            'ai_usage_tokens' => $aiUsageThisMonth,
            'emails_sent' => $emailsSent,
            'followups_due' => $followupsDue,
            'revenue' => $revenue,
            'pending_tasks' => $pendingTasks,
        ];
    }

    public function leadsOverTime(int $businessId, int $days = 30): array
    {
        $rows = Database::fetchAll(
            "SELECT DATE(created_at) d, COUNT(*) c FROM leads
             WHERE business_id = ? AND deleted_at IS NULL AND created_at >= (NOW() - INTERVAL ? DAY)
             GROUP BY DATE(created_at) ORDER BY d ASC",
            [$businessId, $days]
        );
        return $rows;
    }

    public function conversionFunnel(int $businessId): array
    {
        return Database::fetchAll(
            "SELECT s.name, s.slug, COUNT(l.id) c FROM lead_statuses s
             LEFT JOIN leads l ON l.status_id = s.id AND l.business_id = ? AND l.deleted_at IS NULL
             WHERE s.business_id IS NULL OR s.business_id = ?
             GROUP BY s.id ORDER BY s.sort_order ASC",
            [$businessId, $businessId]
        );
    }

    public function leadSources(int $businessId): array
    {
        return Database::fetchAll(
            "SELECT COALESCE(src.name, 'Unspecified') name, COUNT(l.id) c
             FROM leads l LEFT JOIN lead_sources src ON src.id = l.source_id
             WHERE l.business_id = ? AND l.deleted_at IS NULL
             GROUP BY src.id ORDER BY c DESC",
            [$businessId]
        );
    }

    public function revenueOverTime(int $businessId, int $months = 6): array
    {
        return Database::fetchAll(
            "SELECT DATE_FORMAT(invoice_date, '%Y-%m') ym, COALESCE(SUM(amount_paid),0) total
             FROM invoices WHERE business_id = ? AND deleted_at IS NULL
               AND invoice_date >= DATE_SUB(CURDATE(), INTERVAL ? MONTH)
             GROUP BY ym ORDER BY ym ASC",
            [$businessId, $months]
        );
    }

    public function aiUsageOverTime(int $businessId, int $days = 14): array
    {
        return Database::fetchAll(
            "SELECT DATE(created_at) d, COALESCE(SUM(total_tokens),0) tokens, COALESCE(SUM(estimated_cost),0) cost
             FROM ai_usage WHERE business_id = ? AND created_at >= (NOW() - INTERVAL ? DAY)
             GROUP BY DATE(created_at) ORDER BY d ASC",
            [$businessId, $days]
        );
    }

    public function recentActivity(int $businessId, int $limit = 12): array
    {
        // UNION recent leads, ai conversations, followups, customer activity
        // into a single unified activity feed.
        $limit = max(1, min(50, $limit));
        return Database::fetchAll(
            "(SELECT 'lead_created' AS type, l.name AS title, l.created_at AS occurred_at FROM leads l WHERE l.business_id = ? AND l.deleted_at IS NULL ORDER BY l.created_at DESC LIMIT ?)
             UNION ALL
             (SELECT 'ai_conversation' AS type, COALESCE(c.title, 'AI conversation') AS title, c.created_at AS occurred_at FROM ai_conversations c WHERE c.business_id = ? AND c.deleted_at IS NULL ORDER BY c.created_at DESC LIMIT ?)
             UNION ALL
             (SELECT 'followup' AS type, CONCAT('Follow-up scheduled') AS title, f.created_at AS occurred_at FROM followups f WHERE f.business_id = ? ORDER BY f.created_at DESC LIMIT ?)
             UNION ALL
             (SELECT 'customer_activity' AS type, ca.activity_type AS title, ca.created_at AS occurred_at FROM customer_activities ca
                JOIN customers cu ON cu.id = ca.customer_id WHERE cu.business_id = ? ORDER BY ca.created_at DESC LIMIT ?)
             ORDER BY occurred_at DESC LIMIT ?",
            [$businessId, $limit, $businessId, $limit, $businessId, $limit, $businessId, $limit, $limit]
        );
    }
}
