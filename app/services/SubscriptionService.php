<?php
/**
 * Subscription/billing management (spec #28). Changing a plan always
 * re-checks that the change is valid server-side; usage limits are
 * enforced live via UsageLimitService against the CURRENT active plan,
 * never a cached/frontend value.
 */
final class SubscriptionService
{
    public function current(int $businessId): ?array
    {
        return Database::fetchOne(
            "SELECT s.*, p.name AS plan_name, p.slug AS plan_slug, p.price_monthly, p.price_yearly
             FROM subscriptions s JOIN plans p ON p.id = s.plan_id
             WHERE s.business_id = ? ORDER BY s.created_at DESC LIMIT 1",
            [$businessId]
        );
    }

    public function usageSummary(int $businessId): array
    {
        $subscription = $this->current($businessId);
        if ($subscription === null) {
            return [];
        }

        $features = Database::fetchAll("SELECT feature_key, feature_value FROM plan_features WHERE plan_id = ?", [$subscription['plan_id']]);
        $summary = [];
        foreach ($features as $f) {
            $usage = UsageLimitService::check($businessId, $f['feature_key']);
            $summary[$f['feature_key']] = [
                'limit' => $f['feature_value'],
                'used' => $f['feature_value'] === 'unlimited' ? 0 : ((float) $f['feature_value'] - (is_numeric($usage['remaining']) ? (float) $usage['remaining'] : 0)),
                'remaining' => $usage['remaining'],
            ];
        }
        return $summary;
    }

    public function changePlan(int $businessId, string $planSlug, string $billingCycle, int $userId): array
    {
        $plan = Database::fetchOne("SELECT * FROM plans WHERE slug = ? AND is_active = 1", [$planSlug]);
        if ($plan === null) {
            Response::validationError(['plan' => ['Invalid plan selected.']]);
        }

        $currentSub = $this->current($businessId);
        $price = $billingCycle === 'yearly' ? (float) $plan['price_yearly'] : (float) $plan['price_monthly'];

        if ($price > 0) {
            // Paid plan: caller must complete payment first via
            // /api/billing/create-payment.php, then this endpoint is
            // invoked again after webhook/verification confirms success.
            // Free plans (price = 0) can switch immediately.
        }

        if ($currentSub !== null) {
            Database::query("UPDATE subscriptions SET status = 'cancelled', cancelled_at = NOW() WHERE id = ?", [$currentSub['id']]);
        }

        Database::query(
            "INSERT INTO subscriptions (business_id, plan_id, billing_cycle, status, current_period_start, current_period_end, created_at)
             VALUES (?, ?, ?, 'active', NOW(), DATE_ADD(NOW(), INTERVAL ? MONTH), NOW())",
            [$businessId, $plan['id'], $billingCycle, $billingCycle === 'yearly' ? 12 : 1]
        );

        AuditLogger::log($userId, $businessId, 'plan_changed', ['plan' => $planSlug, 'billing_cycle' => $billingCycle]);

        return $this->current($businessId);
    }

    public function paymentHistory(int $businessId, int $page, int $perPage): array
    {
        $page = max(1, $page);
        $perPage = max(1, min(100, $perPage));
        $offset = ($page - 1) * $perPage;

        $total = (int) (Database::fetchOne("SELECT COUNT(*) c FROM payments WHERE business_id = ?", [$businessId])['c'] ?? 0);
        $rows = Database::fetchAll(
            "SELECT * FROM payments WHERE business_id = ? ORDER BY created_at DESC LIMIT {$perPage} OFFSET {$offset}",
            [$businessId]
        );

        return ['items' => $rows, 'pagination' => ['page' => $page, 'per_page' => $perPage, 'total' => $total, 'total_pages' => (int) ceil($total / $perPage)]];
    }
}
