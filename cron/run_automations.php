<?php
/**
 * cron/run_automations.php
 *
 * Handles TIME-BASED automation triggers that can't fire synchronously
 * from a request (e.g. "no response after 2 days"). Event-based
 * triggers (lead.created, etc.) already fire immediately from the
 * relevant service - this job only covers scheduled/polling checks.
 *
 * Suggested cPanel cron: every 15 minutes
 *   php /home/user/public_html/cron/run_automations.php
 */

require_once __DIR__ . '/_cron_bootstrap.php';

$ctx = cron_start('run_automations');

try {
    $processed = 0;

    // "No response after 2 days" - leads still in their initial status
    // (source-agnostic: any status not flagged is_won/is_lost) with no
    // activity in the last 48 hours.
    $staleLeads = Database::fetchAll(
        "SELECT l.id, l.business_id FROM leads l
         LEFT JOIN lead_statuses s ON s.id = l.status_id
         WHERE l.deleted_at IS NULL AND (s.is_won IS NULL OR s.is_won = 0) AND (s.is_lost IS NULL OR s.is_lost = 0)
           AND l.updated_at <= (NOW() - INTERVAL 2 DAY)
           AND NOT EXISTS (
               SELECT 1 FROM lead_activities la WHERE la.lead_id = l.id AND la.activity_type = 'automation_no_response_2d'
           )
         LIMIT 500"
    );

    foreach ($staleLeads as $lead) {
        AutomationService::trigger((int) $lead['business_id'], 'lead.no_response_2d', ['lead_id' => $lead['id']]);
        Database::query(
            "INSERT INTO lead_activities (lead_id, activity_type, description, created_at) VALUES (?, 'automation_no_response_2d', 'No-response automation triggered', NOW())",
            [$lead['id']]
        );
        $processed++;
    }

    // Overdue followups -> mark missed and notify assigned user.
    $overdue = Database::fetchAll(
        "SELECT * FROM followups WHERE status = 'pending' AND scheduled_at < (NOW() - INTERVAL 1 DAY) LIMIT 500"
    );
    foreach ($overdue as $f) {
        Database::query("UPDATE followups SET status = 'missed' WHERE id = ?", [$f['id']]);
        if ($f['assigned_user_id'] !== null) {
            Database::query(
                "INSERT INTO notifications (user_id, business_id, type, title, body, created_at) VALUES (?, ?, 'followup_missed', 'Follow-up missed', 'A scheduled follow-up was not completed in time.', NOW())",
                [$f['assigned_user_id'], $f['business_id']]
            );
        }
        $processed++;
    }

    // Overdue invoices -> mark as overdue.
    Database::query(
        "UPDATE invoices SET status = 'overdue' WHERE status IN ('sent','partially_paid') AND due_date IS NOT NULL AND due_date < CURDATE()"
    );

    cron_finish($ctx, 'success', "Processed {$processed} automation checks.");
} catch (\Throwable $e) {
    cron_finish($ctx, 'failed', $e->getMessage());
}
