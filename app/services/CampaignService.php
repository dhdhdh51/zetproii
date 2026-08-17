<?php
/**
 * Email campaign management: create/send campaigns to a segment of
 * leads/customers using email_templates, tracked via campaign_recipients.
 */
final class CampaignService
{
    public function list(int $businessId): array
    {
        return Database::fetchAll(
            "SELECT c.*, (SELECT COUNT(*) FROM campaign_recipients cr WHERE cr.campaign_id = c.id) AS recipient_count
             FROM campaigns c WHERE c.business_id = ? AND c.deleted_at IS NULL ORDER BY c.created_at DESC",
            [$businessId]
        );
    }

    public function create(int $businessId, int $userId, array $data): array
    {
        Database::query(
            "INSERT INTO campaigns (business_id, name, type, subject, body, status, scheduled_at, created_by, created_at)
             VALUES (?, ?, ?, ?, ?, 'draft', ?, ?, NOW())",
            [
                $businessId, Security::cleanString($data['name']), $data['type'] ?? 'email',
                $data['subject'] ?? null, $data['body'] ?? null, $data['scheduled_at'] ?? null, $userId,
            ]
        );
        $campaignId = (int) Database::lastInsertId();

        $recipientType = $data['recipient_type'] ?? 'customers';
        $recipients = $recipientType === 'leads'
            ? Database::fetchAll("SELECT id AS ref_id, email FROM leads WHERE business_id = ? AND email IS NOT NULL AND deleted_at IS NULL", [$businessId])
            : Database::fetchAll("SELECT id AS ref_id, email FROM customers WHERE business_id = ? AND email IS NOT NULL AND deleted_at IS NULL", [$businessId]);

        foreach ($recipients as $r) {
            Database::query(
                "INSERT INTO campaign_recipients (campaign_id, " . ($recipientType === 'leads' ? 'lead_id' : 'customer_id') . ", email, status, created_at)
                 VALUES (?, ?, ?, 'pending', NOW())",
                [$campaignId, $r['ref_id'], $r['email']]
            );
        }

        return $this->find($businessId, $campaignId);
    }

    public function find(int $businessId, int $id): ?array
    {
        $campaign = Database::fetchOne("SELECT * FROM campaigns WHERE id = ? AND business_id = ? AND deleted_at IS NULL", [$id, $businessId]);
        if ($campaign === null) {
            return null;
        }
        $campaign['recipient_count'] = (int) (Database::fetchOne("SELECT COUNT(*) c FROM campaign_recipients WHERE campaign_id = ?", [$id])['c'] ?? 0);
        return $campaign;
    }

    /**
     * Sends a campaign immediately (or is invoked by the scheduled-email
     * cron for campaigns with a future scheduled_at).
     */
    public function send(int $businessId, int $campaignId): array
    {
        $campaign = $this->find($businessId, $campaignId);
        if ($campaign === null) {
            Response::notFound('Campaign not found.');
        }
        if ($campaign['status'] === 'sent') {
            Response::error('This campaign has already been sent.', [], 409);
        }

        Database::query("UPDATE campaigns SET status = 'sending' WHERE id = ?", [$campaignId]);

        $recipients = Database::fetchAll("SELECT * FROM campaign_recipients WHERE campaign_id = ? AND status = 'pending'", [$campaignId]);
        $sentCount = 0;

        foreach ($recipients as $r) {
            $sent = EmailService::send($r['email'], 'campaign_default', [
                'recipient_name' => 'there',
                'campaign_body' => $campaign['body'] ?? '',
                'subject' => $campaign['subject'] ?? $campaign['name'],
            ], $businessId);

            Database::query(
                "UPDATE campaign_recipients SET status = ?, sent_at = NOW() WHERE id = ?",
                [$sent ? 'sent' : 'failed', $r['id']]
            );
            if ($sent) {
                $sentCount++;
            }
        }

        Database::query("UPDATE campaigns SET status = 'sent', sent_at = NOW() WHERE id = ?", [$campaignId]);

        return array_merge($this->find($businessId, $campaignId), ['sent_count' => $sentCount]);
    }

    public function delete(int $businessId, int $id): void
    {
        Database::query("UPDATE campaigns SET deleted_at = NOW() WHERE id = ? AND business_id = ?", [$id, $businessId]);
    }
}
