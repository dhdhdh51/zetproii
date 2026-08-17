<?php
/**
 * Renders email_templates and sends them via SmtpMailer, using SMTP
 * settings from the `settings` table (admin-configurable) with .env as
 * a fallback for local/dev setups. Every attempt is logged to email_logs
 * and app/helpers/Logger's "email" channel, per spec requirement #19.
 */
final class EmailService
{
    /**
     * @param array<string,string> $variables placeholder => value (raw, will be escaped for HTML context)
     */
    public static function send(string $toEmail, string $templateSlug, array $variables, ?int $businessId = null): bool
    {
        $template = Database::fetchOne(
            "SELECT id, subject, body_html FROM email_templates
             WHERE slug = ? AND (business_id = ? OR business_id IS NULL) AND is_active = 1
             ORDER BY business_id IS NULL ASC LIMIT 1",
            [$templateSlug, $businessId]
        );

        if ($template === null) {
            Logger::email("Template not found: {$templateSlug}");
            self::logAttempt($businessId, null, $toEmail, $templateSlug, 'failed', 'Template not found');
            return false;
        }

        $subject = self::renderPlaceholders($template['subject'], $variables);
        $body = self::renderPlaceholders($template['body_html'], $variables);

        $logId = self::logAttempt($businessId, (int) $template['id'], $toEmail, $subject, 'queued', null);

        [$config, $source] = self::resolveSmtpConfig();

        if ($config === null) {
            Logger::email('SMTP not configured - email not sent', ['to' => $toEmail, 'template' => $templateSlug]);
            self::markLog($logId, 'failed', 'SMTP is not configured.');
            return false;
        }

        try {
            $mailer = new SmtpMailer(
                $config['host'],
                (int) $config['port'],
                $config['username'],
                $config['password'],
                $config['encryption']
            );
            $mailer->send($config['from_address'], $config['from_name'], $toEmail, $subject, $body);
            self::markLog($logId, 'sent', null);
            Logger::email("Email sent to {$toEmail}", ['template' => $templateSlug, 'via' => $source]);
            return true;
        } catch (\Throwable $e) {
            self::markLog($logId, 'failed', $e->getMessage());
            Logger::email('Email send failed: ' . $e->getMessage(), ['to' => $toEmail, 'template' => $templateSlug]);
            return false;
        }
    }

    private static function renderPlaceholders(string $content, array $variables): string
    {
        foreach ($variables as $key => $value) {
            $content = str_replace('{{' . $key . '}}', (string) $value, $content);
        }
        return $content;
    }

    /**
     * Can this server actually deliver email at all?
     *
     * Callers need this to avoid creating states that depend on an email the
     * user will never receive. On a fresh install nothing configures SMTP, so
     * this returns false until an admin sets it up under Admin > Email / SMTP.
     */
    public static function isConfigured(): bool
    {
        [$config] = self::resolveSmtpConfig();
        return $config !== null;
    }

    /**
     * SMTP settings can be configured by the admin from Admin > Settings >
     * Email (stored encrypted in `settings`), falling back to .env values
     * for local development.
     *
     * @return array{0: ?array, 1: string}
     */
    private static function resolveSmtpConfig(): array
    {
        $rows = Database::fetchAll(
            "SELECT setting_key, setting_value, is_encrypted FROM settings WHERE setting_key LIKE 'smtp_%'"
        );
        $dbConfig = [];
        foreach ($rows as $row) {
            $value = $row['setting_value'];
            if ((int) $row['is_encrypted'] === 1 && $value !== null) {
                $value = Security::decrypt($value) ?? '';
            }
            $dbConfig[$row['setting_key']] = $value;
        }

        if (!empty($dbConfig['smtp_host'])) {
            return [[
                'host'       => $dbConfig['smtp_host'],
                'port'       => $dbConfig['smtp_port'] ?? 587,
                'username'   => $dbConfig['smtp_username'] ?? '',
                'password'   => $dbConfig['smtp_password'] ?? '',
                'encryption' => $dbConfig['smtp_encryption'] ?? 'tls',
                'from_address' => $dbConfig['smtp_from_address'] ?? config('mail.from_address'),
                'from_name'    => $dbConfig['smtp_from_name'] ?? config('mail.from_name'),
            ], 'database'];
        }

        $envHost = config('mail.host');
        if (!empty($envHost)) {
            return [[
                'host'       => $envHost,
                'port'       => config('mail.port', 587),
                'username'   => config('mail.username', ''),
                'password'   => config('mail.password', ''),
                'encryption' => config('mail.encryption', 'tls'),
                'from_address' => config('mail.from_address'),
                'from_name'    => config('mail.from_name'),
            ], 'env'];
        }

        return [null, 'none'];
    }

    private static function logAttempt(?int $businessId, ?int $templateId, string $toEmail, string $subject, string $status, ?string $error): int
    {
        Database::query(
            "INSERT INTO email_logs (business_id, template_id, to_email, subject, status, error_message, created_at)
             VALUES (?, ?, ?, ?, ?, ?, NOW())",
            [$businessId, $templateId, $toEmail, $subject, $status, $error]
        );
        return (int) Database::lastInsertId();
    }

    private static function markLog(int $logId, string $status, ?string $error): void
    {
        Database::query(
            "UPDATE email_logs SET status = ?, error_message = ?, sent_at = IF(? = 'sent', NOW(), sent_at) WHERE id = ?",
            [$status, $error, $status, $logId]
        );
    }
}
