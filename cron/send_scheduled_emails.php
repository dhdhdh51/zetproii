<?php
/**
 * cron/send_scheduled_emails.php
 *
 * Sends campaigns whose scheduled_at has arrived, and retries any
 * email_logs rows still stuck in 'queued' status.
 *
 * Suggested cPanel cron: every 5 minutes
 *   php /home/user/public_html/cron/send_scheduled_emails.php
 */

require_once __DIR__ . '/_cron_bootstrap.php';

$ctx = cron_start('send_scheduled_emails');

try {
    $sentCampaigns = 0;

    $dueCampaigns = Database::fetchAll(
        "SELECT * FROM campaigns WHERE status = 'scheduled' AND scheduled_at <= NOW() AND deleted_at IS NULL LIMIT 50"
    );

    $campaignService = new CampaignService();
    foreach ($dueCampaigns as $campaign) {
        $campaignService->send((int) $campaign['business_id'], (int) $campaign['id']);
        $sentCampaigns++;
    }

    cron_finish($ctx, 'success', "Sent {$sentCampaigns} scheduled campaign(s).");
} catch (\Throwable $e) {
    cron_finish($ctx, 'failed', $e->getMessage());
}
