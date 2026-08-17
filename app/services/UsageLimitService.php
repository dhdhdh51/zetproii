<?php
/**
 * Enforces plan-based usage limits server-side (spec #28/#42: never rely
 * on frontend limits). Tracks consumption per business per billing
 * period in `usage_limits`, sourced from the business's active
 * subscription -> plan -> plan_features.
 */
final class UsageLimitService
{
    /**
     * Returns [allowed:bool, remaining:float|string, limit:float|string]
     * for a given metric (e.g. 'ai_credits', 'leads', 'documents').
     */
    public static function check(int $businessId, string $metric): array
    {
        $limit = self::planLimit($businessId, $metric);

        if ($limit === 'unlimited') {
            return ['allowed' => true, 'remaining' => 'unlimited', 'limit' => 'unlimited'];
        }

        $limitNum = (float) $limit;
        $used = self::currentUsage($businessId, $metric);

        return [
            'allowed' => $used < $limitNum,
            'remaining' => max(0, $limitNum - $used),
            'limit' => $limitNum,
        ];
    }

    public static function increment(int $businessId, string $metric, float $amount = 1): void
    {
        [$periodStart, $periodEnd] = self::currentPeriod($businessId);
        $limit = self::planLimit($businessId, $metric);
        $limitValue = $limit === 'unlimited' ? 999999999 : (float) $limit;

        Database::query(
            "INSERT INTO usage_limits (business_id, period_start, period_end, metric, used, allowed, created_at, updated_at)
             VALUES (?, ?, ?, ?, ?, ?, NOW(), NOW())
             ON DUPLICATE KEY UPDATE used = used + ?, updated_at = NOW()",
            [$businessId, $periodStart, $periodEnd, $metric, $amount, $limitValue, $amount]
        );
    }

    private static function currentUsage(int $businessId, string $metric): float
    {
        [$periodStart] = self::currentPeriod($businessId);
        $row = Database::fetchOne(
            "SELECT used FROM usage_limits WHERE business_id = ? AND period_start = ? AND metric = ?",
            [$businessId, $periodStart, $metric]
        );
        return (float) ($row['used'] ?? 0);
    }

    private static function planLimit(int $businessId, string $metric): string
    {
        $row = Database::fetchOne(
            "SELECT pf.feature_value FROM subscriptions s
             JOIN plan_features pf ON pf.plan_id = s.plan_id
             WHERE s.business_id = ? AND pf.feature_key = ?
             ORDER BY s.created_at DESC LIMIT 1",
            [$businessId, $metric]
        );
        return $row['feature_value'] ?? '0';
    }

    /** @return array{0:string,1:string} [period_start, period_end] as Y-m-d, calendar month based */
    private static function currentPeriod(int $businessId): array
    {
        return [date('Y-m-01'), date('Y-m-t')];
    }
}
