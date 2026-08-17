<?php
/**
 * Executes automation_rules matching a given trigger_event for a business.
 * Called synchronously from services (e.g. LeadService) for instant
 * triggers like lead.created, and from the cron job for time-based
 * triggers like "no response after 2 days" (see cron/run_automations.php).
 *
 * Supported action types (stored as JSON in automation_rules.actions):
 *   { "type": "send_email", "template": "welcome" }
 *   { "type": "create_task", "title": "...", "due_in_hours": 24 }
 *   { "type": "notify_user", "target": "assigned" }
 *   { "type": "create_followup", "channel": "call", "in_hours": 48 }
 */
final class AutomationService
{
    public static function trigger(int $businessId, string $event, array $context = []): void
    {
        try {
            $rules = Database::fetchAll(
                "SELECT * FROM automation_rules WHERE business_id = ? AND trigger_event = ? AND is_active = 1",
                [$businessId, $event]
            );

            foreach ($rules as $rule) {
                self::runRule($rule, $businessId, $context);
            }
        } catch (\Throwable $e) {
            Logger::error('AutomationService::trigger failed: ' . $e->getMessage(), ['event' => $event]);
        }
    }

    private static function runRule(array $rule, int $businessId, array $context): void
    {
        $actions = json_decode($rule['actions'], true) ?: [];
        $lead = !empty($context['lead_id'])
            ? Database::fetchOne("SELECT * FROM leads WHERE id = ?", [$context['lead_id']])
            : null;

        $status = 'success';
        $message = '';

        try {
            foreach ($actions as $action) {
                self::runAction($action, $businessId, $lead, $context);
            }
            $message = 'Executed ' . count($actions) . ' action(s).';
        } catch (\Throwable $e) {
            $status = 'failed';
            $message = $e->getMessage();
        }

        Database::query(
            "INSERT INTO automation_runs (automation_rule_id, related_type, related_id, status, result_message, run_at)
             VALUES (?, ?, ?, ?, ?, NOW())",
            [$rule['id'], $lead !== null ? 'lead' : null, $lead['id'] ?? null, $status, $message]
        );
    }

    private static function runAction(array $action, int $businessId, ?array $lead, array $context): void
    {
        switch ($action['type'] ?? '') {
            case 'send_email':
                if ($lead !== null && !empty($lead['email'])) {
                    EmailService::send($lead['email'], $action['template'] ?? 'lead_notification', [
                        'lead_name' => $lead['name'] ?? '',
                        'lead_email' => $lead['email'] ?? '',
                        'lead_phone' => $lead['phone'] ?? '',
                        'lead_source' => '',
                        'user_name' => '',
                    ], $businessId);
                }
                break;

            case 'create_task':
                Database::query(
                    "INSERT INTO tasks (business_id, related_type, related_id, title, description, status, priority, due_at, created_at)
                     VALUES (?, 'lead', ?, ?, ?, 'pending', 'medium', DATE_ADD(NOW(), INTERVAL ? HOUR), NOW())",
                    [
                        $businessId,
                        $lead['id'] ?? null,
                        $action['title'] ?? 'Follow up',
                        $action['description'] ?? '',
                        (int) ($action['due_in_hours'] ?? 24),
                    ]
                );
                break;

            case 'notify_user':
                $targetUserId = null;
                if (($action['target'] ?? '') === 'assigned' && $lead !== null) {
                    $targetUserId = $lead['assigned_user_id'];
                }
                if ($targetUserId !== null) {
                    Database::query(
                        "INSERT INTO notifications (user_id, business_id, type, title, body, created_at)
                         VALUES (?, ?, 'automation', ?, ?, NOW())",
                        [$targetUserId, $businessId, $action['title'] ?? 'Automation triggered', $action['body'] ?? '']
                    );
                }
                break;

            case 'create_followup':
                if ($lead !== null) {
                    Database::query(
                        "INSERT INTO followups (business_id, lead_id, assigned_user_id, scheduled_at, channel, status, created_at)
                         VALUES (?, ?, ?, DATE_ADD(NOW(), INTERVAL ? HOUR), ?, 'pending', NOW())",
                        [$businessId, $lead['id'], $lead['assigned_user_id'], (int) ($action['in_hours'] ?? 48), $action['channel'] ?? 'call']
                    );
                }
                break;

            default:
                // Unknown action type - skip silently but do not fail the whole rule.
                break;
        }
    }
}
