<?php
/**
 * "AI Qualify Lead" feature (spec #17): analyzes lead info and produces
 * a score, intent, buying probability, priority recommendation,
 * suggested response and next follow-up date - persisted on the lead.
 */
final class LeadQualificationService
{
    public function qualify(int $businessId, int $leadId, ?int $userId): array
    {
        $lead = Database::fetchOne(
            "SELECT l.*, s.name AS status_name FROM leads l LEFT JOIN lead_statuses s ON s.id = l.status_id
             WHERE l.id = ? AND l.business_id = ? AND l.deleted_at IS NULL",
            [$leadId, $businessId]
        );
        if ($lead === null) {
            Response::notFound('Lead not found.');
        }

        $business = Database::fetchOne("SELECT name, industry, about FROM businesses WHERE id = ?", [$businessId]);

        $ai = new AIService();
        $prompt = "Business: {$business['name']} ({$business['industry']}). " . ($business['about'] ?? '') . "\n\n" .
            "Lead details:\n" .
            "Name: {$lead['name']}\nCompany: " . ($lead['company'] ?? 'N/A') . "\n" .
            "Requirement: " . ($lead['requirement'] ?? 'N/A') . "\nBudget: " . ($lead['budget'] ?? 'N/A') . "\n" .
            "Location: " . ($lead['location'] ?? 'N/A') . "\nCurrent status: " . ($lead['status_name'] ?? 'New');

        $schema = [
            'score' => 'integer 0-100',
            'intent' => 'string, short phrase describing buying intent',
            'buying_probability' => 'number 0-100',
            'priority' => 'one of: low, medium, high, urgent',
            'recommended_action' => 'string, 1-2 sentences',
            'suggested_response' => 'string, a ready-to-send message to the lead',
            'next_followup_days' => 'integer, days from now to follow up',
        ];

        try {
            $result = $ai->generateStructuredData($businessId, $userId, 'lead_qualify', $prompt, $schema);
        } catch (\Throwable $e) {
            Response::error('AI qualification failed: ' . $e->getMessage(), [], 502);
        }

        $score = max(0, min(100, (int) ($result['score'] ?? 0)));
        $priority = in_array($result['priority'] ?? '', ['low', 'medium', 'high', 'urgent'], true) ? $result['priority'] : 'medium';
        $followupDays = max(0, min(60, (int) ($result['next_followup_days'] ?? 3)));

        Database::query(
            "UPDATE leads SET ai_score = ?, ai_intent = ?, ai_buying_probability = ?, priority = ?,
                               ai_recommended_action = ?, ai_suggested_response = ?, next_followup_at = DATE_ADD(NOW(), INTERVAL ? DAY),
                               ai_qualified_at = NOW()
             WHERE id = ?",
            [
                $score,
                Security::cleanString($result['intent'] ?? ''),
                (float) ($result['buying_probability'] ?? 0),
                $priority,
                Security::cleanString($result['recommended_action'] ?? ''),
                $result['suggested_response'] ?? '',
                $followupDays,
                $leadId,
            ]
        );

        Database::query(
            "INSERT INTO lead_activities (lead_id, user_id, activity_type, description, metadata, created_at)
             VALUES (?, ?, 'ai_qualify', 'AI qualification completed', ?, NOW())",
            [$leadId, $userId, json_encode($result)]
        );

        AutomationService::trigger($businessId, 'lead.qualified', ['lead_id' => $leadId]);

        return Database::fetchOne("SELECT * FROM leads WHERE id = ?", [$leadId]);
    }
}
