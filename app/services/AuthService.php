<?php
/**
 * Core authentication business logic: registration, login, logout,
 * password reset, email verification. Controllers stay thin and call
 * into this service; this keeps the same rules enforced whether the
 * request came from the web app or (in future) a CLI/admin tool.
 */
final class AuthService
{
    public function register(string $name, string $email, string $password, ?string $phone = null): array
    {
        $email = Security::cleanEmail($email);
        $name = Security::cleanString($name);

        $existing = Database::fetchOne("SELECT id FROM users WHERE email = ?", [$email]);
        if ($existing !== null) {
            Response::validationError(['email' => ['An account with this email already exists.']]);
        }

        Database::beginTransaction();
        try {
            $uuid = $this->uuid4();
            Database::query(
                "INSERT INTO users (uuid, name, email, phone, password_hash, role, status, created_at)
                 VALUES (?, ?, ?, ?, ?, 'BUSINESS_OWNER', 'pending', NOW())",
                [$uuid, $name, $email, $phone, Security::hashPassword($password)]
            );
            $userId = (int) Database::lastInsertId();

            Database::query(
                "INSERT INTO user_profiles (user_id, created_at) VALUES (?, NOW())",
                [$userId]
            );

            $token = Security::randomToken(32);
            $tokenHash = hash('sha256', $token);
            $ttlHours = (int) config('auth.email_verify_ttl_hours', 48);
            Database::query(
                "INSERT INTO email_verifications (user_id, token_hash, expires_at, created_at)
                 VALUES (?, ?, DATE_ADD(NOW(), INTERVAL ? HOUR), NOW())",
                [$userId, $tokenHash, $ttlHours]
            );

            AuditLogger::log($userId, null, 'user_registered', ['email' => $email]);

            Database::commit();

            $sent = EmailService::send(
                $email,
                'email_verification',
                [
                    'name' => $name,
                    'verification_link' => rtrim((string) config('app.url'), '/') . '/auth/verify-email.php?token=' . $token,
                    'expiry_hours' => (string) $ttlHours,
                ]
            );

            // If this server cannot deliver email at all, the user could never
            // receive a verification link - and since login refuses 'pending'
            // accounts, they would be locked out of their own account forever.
            // A fresh install has no SMTP configured, so that is the default
            // state. Activate the account instead of creating a dead one.
            //
            // Note this is deliberately keyed on "email delivery is not set up"
            // rather than "the send failed": a transient SMTP failure on a
            // properly configured server should still require verification,
            // and the user can request a new link.
            $requiresVerification = true;
            if (!$sent && !EmailService::isConfigured()) {
                Database::query(
                    "UPDATE users SET status = 'active', email_verified_at = NOW() WHERE id = ?",
                    [$userId]
                );
                $requiresVerification = false;
                Logger::systemInfo(
                    'Account activated without email verification: no SMTP is configured on this server',
                    ['user_id' => $userId, 'email' => $email]
                );
            }

            return [
                'id' => $userId,
                'uuid' => $uuid,
                'name' => $name,
                'email' => $email,
                'requires_verification' => $requiresVerification,
            ];
        } catch (\Throwable $e) {
            Database::rollBack();
            Logger::error('Registration failed: ' . $e->getMessage());
            Response::serverError('Registration failed. Please try again.');
        }
    }

    public function login(string $email, string $password): array
    {
        $email = Security::cleanEmail($email);
        $ip = Security::clientIp();

        RateLimitMiddleware::checkLogin($email, $ip);

        $user = Database::fetchOne(
            "SELECT id, uuid, name, email, password_hash, role, status FROM users WHERE email = ? AND deleted_at IS NULL",
            [$email]
        );

        if ($user === null || !Security::verifyPassword($password, $user['password_hash'])) {
            RateLimitMiddleware::recordLoginAttempt($email, $ip, false);
            Logger::security('Failed login attempt', ['email' => $email, 'ip' => $ip]);
            Response::error('Invalid email or password.', [], 401);
        }

        if ($user['status'] === 'suspended') {
            RateLimitMiddleware::recordLoginAttempt($email, $ip, false);
            Response::forbidden('Your account has been suspended. Contact support.');
        }

        if ($user['status'] === 'pending') {
            // The password was already verified above, so this is genuinely the
            // account owner - they are only blocked by email verification.
            if (!EmailService::isConfigured()) {
                // Nothing on this server can deliver a verification email, so
                // this account can never be verified through the normal flow.
                // Refusing the login would lock the owner out permanently for a
                // reason they cannot act on. Activate and let them in instead.
                // This also heals accounts that were created before SMTP was
                // recognised as unavailable.
                Database::query(
                    "UPDATE users SET status = 'active', email_verified_at = COALESCE(email_verified_at, NOW()) WHERE id = ?",
                    [$user['id']]
                );
                $user['status'] = 'active';
                Logger::systemInfo(
                    'Activated a pending account at login: no SMTP is configured, so email verification is impossible',
                    ['user_id' => (int) $user['id'], 'email' => $email]
                );
            } else {
                RateLimitMiddleware::recordLoginAttempt($email, $ip, false);
                Response::forbidden(
                    'Please verify your email before logging in. Check your inbox for the '
                    . 'verification link, or resend it using the link below.'
                );
            }
        }

        RateLimitMiddleware::recordLoginAttempt($email, $ip, true);

        if (Security::passwordNeedsRehash($user['password_hash'])) {
            Database::query("UPDATE users SET password_hash = ? WHERE id = ?", [
                Security::hashPassword($password), $user['id'],
            ]);
        }

        session_regenerate_id(true);
        $_SESSION['user_id'] = (int) $user['id'];
        $_SESSION['user_role'] = $user['role'];
        $_SESSION['_last_regen'] = time();

        Database::query(
            "UPDATE users SET last_login_at = NOW(), last_login_ip = ? WHERE id = ?",
            [$ip, $user['id']]
        );

        AuditLogger::log((int) $user['id'], null, 'login', ['ip' => $ip]);

        unset($user['password_hash']);
        return $user;
    }

    public function logout(int $userId): void
    {
        AuditLogger::log($userId, null, 'logout', []);
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
        }
        session_destroy();
    }

    /**
     * Issues a fresh email-verification link for an account still pending.
     *
     * Returns the outcome so the caller can tell the user something truthful:
     *   'sent'           a new link is on its way
     *   'activated'      email delivery isn't set up here, so the account was
     *                    activated directly instead of promising an email
     *   'not_applicable' nothing to do (unknown email, or already verified) -
     *                    reported to the user identically to 'sent' so this
     *                    endpoint can't be used to discover registered emails
     *   'failed'         SMTP is configured but the send failed
     */
    public function resendVerificationEmail(string $email): string
    {
        $email = Security::cleanEmail($email);
        $user = Database::fetchOne(
            "SELECT id, name, status FROM users WHERE email = ? AND deleted_at IS NULL",
            [$email]
        );

        if ($user === null || $user['status'] !== 'pending') {
            return 'not_applicable';
        }

        if (!EmailService::isConfigured()) {
            Database::query(
                "UPDATE users SET status = 'active', email_verified_at = COALESCE(email_verified_at, NOW()) WHERE id = ?",
                [$user['id']]
            );
            Logger::systemInfo(
                'Activated a pending account on verification resend: no SMTP is configured',
                ['user_id' => (int) $user['id'], 'email' => $email]
            );
            return 'activated';
        }

        $token = Security::randomToken(32);
        $ttlHours = (int) config('auth.email_verify_ttl_hours', 48);
        Database::query(
            "INSERT INTO email_verifications (user_id, token_hash, expires_at, created_at)
             VALUES (?, ?, DATE_ADD(NOW(), INTERVAL ? HOUR), NOW())",
            [$user['id'], hash('sha256', $token), $ttlHours]
        );

        $sent = EmailService::send($email, 'email_verification', [
            'name' => $user['name'],
            'verification_link' => rtrim((string) config('app.url'), '/') . '/auth/verify-email.php?token=' . $token,
            'expiry_hours' => (string) $ttlHours,
        ]);

        AuditLogger::log((int) $user['id'], null, 'verification_email_resent', []);

        return $sent ? 'sent' : 'failed';
    }

    public function requestPasswordReset(string $email): void
    {
        $email = Security::cleanEmail($email);
        $user = Database::fetchOne("SELECT id, name FROM users WHERE email = ? AND deleted_at IS NULL", [$email]);

        // Always behave the same whether or not the email exists, to avoid
        // leaking which emails are registered.
        if ($user === null) {
            return;
        }

        $token = Security::randomToken(32);
        $tokenHash = hash('sha256', $token);
        $ttl = (int) config('auth.password_reset_ttl_min', 60);

        Database::query(
            "INSERT INTO password_resets (user_id, token_hash, expires_at, created_at)
             VALUES (?, ?, DATE_ADD(NOW(), INTERVAL ? MINUTE), NOW())",
            [$user['id'], $tokenHash, $ttl]
        );

        EmailService::send($email, 'password_reset', [
            'name' => $user['name'],
            'reset_link' => rtrim((string) config('app.url'), '/') . '/auth/reset-password.php?token=' . $token,
        ]);

        AuditLogger::log((int) $user['id'], null, 'password_reset_requested', []);
    }

    public function resetPassword(string $token, string $newPassword): void
    {
        $tokenHash = hash('sha256', $token);

        // Expiry is evaluated by MySQL (expires_at > NOW()) rather than in PHP,
        // so the check can never be skewed by a PHP/MySQL timezone difference.
        $reset = Database::fetchOne(
            "SELECT id, user_id FROM password_resets
             WHERE token_hash = ? AND used_at IS NULL AND expires_at > NOW()
             ORDER BY id DESC LIMIT 1",
            [$tokenHash]
        );

        if ($reset === null) {
            throw new InvalidArgumentException('This password reset link is invalid or has expired.');
        }

        Database::query("UPDATE users SET password_hash = ? WHERE id = ?", [
            Security::hashPassword($newPassword), $reset['user_id'],
        ]);
        Database::query("UPDATE password_resets SET used_at = NOW() WHERE id = ?", [$reset['id']]);

        AuditLogger::log((int) $reset['user_id'], null, 'password_reset_completed', []);
    }

    public function verifyEmail(string $token): void
    {
        $tokenHash = hash('sha256', $token);

        // Expiry evaluated by MySQL, for the same timezone-safety reason as above.
        $verification = Database::fetchOne(
            "SELECT id, user_id, verified_at FROM email_verifications
             WHERE token_hash = ? AND expires_at > NOW()
             ORDER BY id DESC LIMIT 1",
            [$tokenHash]
        );

        if ($verification === null) {
            throw new InvalidArgumentException('This verification link is invalid or has expired.');
        }

        if ($verification['verified_at'] === null) {
            Database::query("UPDATE email_verifications SET verified_at = NOW() WHERE id = ?", [$verification['id']]);
            Database::query(
                "UPDATE users SET email_verified_at = NOW(), status = 'active' WHERE id = ?",
                [$verification['user_id']]
            );
            AuditLogger::log((int) $verification['user_id'], null, 'email_verified', []);
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
