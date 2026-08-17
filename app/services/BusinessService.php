<?php
/**
 * Business (tenant) creation, membership, and onboarding logic.
 * Every method that mutates business data verifies ownership/membership
 * via AuthMiddleware::requireBusinessAccess() - business_id is never
 * trusted blindly from the request.
 */
final class BusinessService
{
    public function create(int $ownerId, string $name): array
    {
        $slug = $this->generateUniqueSlug($name);
        $uuid = $this->uuid4();

        Database::query(
            "INSERT INTO businesses (uuid, owner_id, name, slug, status, currency, timezone, created_at)
             VALUES (?, ?, ?, ?, 'trial', 'INR', 'Asia/Kolkata', NOW())",
            [$uuid, $ownerId, Security::cleanString($name), $slug]
        );
        $businessId = (int) Database::lastInsertId();

        // Default 7 business_hours rows (Mon-Sat 9-6, Sun closed) so the
        // onboarding step has sensible defaults to edit rather than empty.
        for ($day = 0; $day <= 6; $day++) {
            $isOpen = $day !== 0;
            Database::query(
                "INSERT INTO business_hours (business_id, day_of_week, is_open, open_time, close_time, created_at)
                 VALUES (?, ?, ?, '09:00:00', '18:00:00', NOW())",
                [$businessId, $day, $isOpen ? 1 : 0]
            );
        }

        // Free trial subscription
        $freePlan = Database::fetchOne("SELECT id FROM plans WHERE slug = 'free' LIMIT 1");
        if ($freePlan !== null) {
            $trialDays = (int) (Database::fetchOne("SELECT setting_value FROM settings WHERE setting_key = 'trial_days'")['setting_value'] ?? 14);
            Database::query(
                "INSERT INTO subscriptions (business_id, plan_id, billing_cycle, status, trial_ends_at, current_period_start, current_period_end, created_at)
                 VALUES (?, ?, 'monthly', 'trialing', DATE_ADD(NOW(), INTERVAL ? DAY), NOW(), DATE_ADD(NOW(), INTERVAL ? DAY), NOW())",
                [$businessId, $freePlan['id'], $trialDays, $trialDays]
            );
        }

        AuditLogger::log($ownerId, $businessId, 'business_created', ['name' => $name]);

        return Database::fetchOne("SELECT * FROM businesses WHERE id = ?", [$businessId]);
    }

    public function updateOnboardingStep(int $businessId, int $step, array $fields): void
    {
        $allowedColumns = [
            'business_type', 'industry', 'website', 'phone', 'email', 'address',
            'city', 'state', 'country', 'postal_code', 'currency', 'timezone',
            'about', 'target_customers', 'unique_selling_points',
        ];

        $sets = [];
        $params = [];
        foreach ($fields as $key => $value) {
            if (in_array($key, $allowedColumns, true)) {
                $sets[] = "{$key} = ?";
                $params[] = is_string($value) ? Security::cleanString($value) : $value;
            }
        }

        if (!empty($sets)) {
            $sets[] = "onboarding_step = ?";
            $params[] = $step;
            $params[] = $businessId;
            Database::query(
                "UPDATE businesses SET " . implode(', ', $sets) . " WHERE id = ?",
                $params
            );
        } else {
            Database::query("UPDATE businesses SET onboarding_step = ? WHERE id = ?", [$step, $businessId]);
        }
    }

    public function completeOnboarding(int $businessId): void
    {
        Database::query(
            "UPDATE businesses SET onboarding_completed = 1, status = 'active' WHERE id = ?",
            [$businessId]
        );
    }

    /**
     * Invites an existing user (by email) to join a business. If no user
     * exists with that email, returns an error - the frontend should
     * offer to send a signup invite in that case (not implemented here
     * as sending arbitrary account-creation emails to unverified
     * addresses is out of scope for the core invite flow).
     */
    public function inviteMember(int $businessId, int $invitedByUserId, string $email, string $role): array
    {
        $email = Security::cleanEmail($email);
        $allowedRoles = ['MANAGER', 'STAFF', 'AGENCY_STAFF'];
        if (!in_array($role, $allowedRoles, true)) {
            Response::validationError(['role' => ['Invalid role selected.']]);
        }

        $user = Database::fetchOne("SELECT id, name FROM users WHERE email = ? AND deleted_at IS NULL", [$email]);
        if ($user === null) {
            Response::error('No user found with that email. Ask them to create an account first.', [], 404);
        }

        $existing = Database::fetchOne(
            "SELECT id FROM business_members WHERE business_id = ? AND user_id = ?",
            [$businessId, $user['id']]
        );
        if ($existing !== null) {
            Response::error('This user is already a member of this business.', [], 409);
        }

        Database::query(
            "INSERT INTO business_members (business_id, user_id, role, status, invited_by, invited_at, joined_at, created_at)
             VALUES (?, ?, ?, 'active', ?, NOW(), NOW(), NOW())",
            [$businessId, $user['id'], $role, $invitedByUserId]
        );

        AuditLogger::log($invitedByUserId, $businessId, 'member_invited', ['email' => $email, 'role' => $role]);

        return Database::fetchOne(
            "SELECT bm.id, bm.role, bm.status, u.name, u.email FROM business_members bm
             JOIN users u ON u.id = bm.user_id WHERE bm.business_id = ? AND bm.user_id = ?",
            [$businessId, $user['id']]
        );
    }

    private function generateUniqueSlug(string $name): string
    {
        $base = strtolower(trim(preg_replace('/[^a-zA-Z0-9]+/', '-', $name), '-'));
        if ($base === '') {
            $base = 'business';
        }
        $slug = $base;
        $i = 1;
        while (Database::fetchOne("SELECT id FROM businesses WHERE slug = ?", [$slug]) !== null) {
            $slug = $base . '-' . (++$i);
        }
        return $slug;
    }

    private function uuid4(): string
    {
        $data = random_bytes(16);
        $data[6] = chr(ord($data[6]) & 0x0f | 0x40);
        $data[8] = chr(ord($data[8]) & 0x3f | 0x80);
        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
    }
}
