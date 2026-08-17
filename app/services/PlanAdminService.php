<?php
/**
 * Admin management of subscription plans/features (spec #28: "Each plan
 * can define AI credits, users, businesses, ... Enforce limits
 * server-side.") and coupons.
 */
final class PlanAdminService
{
    public function list(): array
    {
        $plans = Database::fetchAll("SELECT * FROM plans ORDER BY sort_order ASC");
        foreach ($plans as &$p) {
            $p['features'] = Database::fetchAll("SELECT feature_key, feature_value FROM plan_features WHERE plan_id = ?", [$p['id']]);
            $p['subscriber_count'] = (int) (Database::fetchOne("SELECT COUNT(*) c FROM subscriptions WHERE plan_id = ? AND status IN ('active','trialing')", [$p['id']])['c'] ?? 0);
        }
        return $plans;
    }

    public function upsert(array $data, int $adminUserId): array
    {
        if (!empty($data['id'])) {
            Database::query(
                "UPDATE plans SET name = ?, description = ?, price_monthly = ?, price_yearly = ?, currency = ?, is_active = ? WHERE id = ?",
                [$data['name'], $data['description'] ?? null, $data['price_monthly'] ?? 0, $data['price_yearly'] ?? 0, $data['currency'] ?? 'INR', !empty($data['is_active']) ? 1 : 0, $data['id']]
            );
            $planId = (int) $data['id'];
        } else {
            $slug = strtolower(trim(preg_replace('/[^a-zA-Z0-9]+/', '-', $data['name']), '-'));
            Database::query(
                "INSERT INTO plans (name, slug, description, price_monthly, price_yearly, currency, is_active, sort_order, created_at)
                 VALUES (?, ?, ?, ?, ?, ?, 1, (SELECT COALESCE(MAX(sort_order),0)+1 FROM plans), NOW())",
                [$data['name'], $slug, $data['description'] ?? null, $data['price_monthly'] ?? 0, $data['price_yearly'] ?? 0, $data['currency'] ?? 'INR']
            );
            $planId = (int) Database::lastInsertId();
        }

        if (!empty($data['features']) && is_array($data['features'])) {
            foreach ($data['features'] as $key => $value) {
                Database::query(
                    "INSERT INTO plan_features (plan_id, feature_key, feature_value) VALUES (?, ?, ?)
                     ON DUPLICATE KEY UPDATE feature_value = VALUES(feature_value)",
                    [$planId, $key, $value]
                );
            }
        }

        AuditLogger::admin($adminUserId, 'plan_saved', '', ['plan_id' => $planId]);

        return Database::fetchOne("SELECT * FROM plans WHERE id = ?", [$planId]);
    }

    public function listSubscriptions(int $page, int $perPage): array
    {
        $page = max(1, $page);
        $perPage = max(1, min(100, $perPage));
        $offset = ($page - 1) * $perPage;

        $total = (int) (Database::fetchOne("SELECT COUNT(*) c FROM subscriptions")['c'] ?? 0);
        $rows = Database::fetchAll(
            "SELECT s.*, b.name AS business_name, p.name AS plan_name
             FROM subscriptions s JOIN businesses b ON b.id = s.business_id JOIN plans p ON p.id = s.plan_id
             ORDER BY s.created_at DESC LIMIT {$perPage} OFFSET {$offset}"
        );
        return ['items' => $rows, 'pagination' => ['page' => $page, 'per_page' => $perPage, 'total' => $total, 'total_pages' => (int) ceil($total / $perPage)]];
    }

    public function listPayments(int $page, int $perPage): array
    {
        $page = max(1, $page);
        $perPage = max(1, min(100, $perPage));
        $offset = ($page - 1) * $perPage;

        $total = (int) (Database::fetchOne("SELECT COUNT(*) c FROM payments")['c'] ?? 0);
        $rows = Database::fetchAll(
            "SELECT p.*, b.name AS business_name FROM payments p JOIN businesses b ON b.id = p.business_id
             ORDER BY p.created_at DESC LIMIT {$perPage} OFFSET {$offset}"
        );
        return ['items' => $rows, 'pagination' => ['page' => $page, 'per_page' => $perPage, 'total' => $total, 'total_pages' => (int) ceil($total / $perPage)]];
    }

    public function listCoupons(): array
    {
        return Database::fetchAll("SELECT * FROM coupons ORDER BY created_at DESC");
    }

    public function createCoupon(array $data, int $adminUserId): array
    {
        Database::query(
            "INSERT INTO coupons (code, discount_type, discount_value, max_redemptions, valid_from, valid_until, is_active, created_at)
             VALUES (?, ?, ?, ?, ?, ?, 1, NOW())",
            [
                strtoupper(Security::cleanString($data['code'])), $data['discount_type'] ?? 'percent', $data['discount_value'] ?? 0,
                $data['max_redemptions'] ?? null, $data['valid_from'] ?? null, $data['valid_until'] ?? null,
            ]
        );
        // Capture the id BEFORE AuditLogger writes to admin_logs, otherwise
        // lastInsertId() returns the audit row's id and this returns null.
        $couponId = (int) Database::lastInsertId();

        AuditLogger::admin($adminUserId, 'coupon_created', '', ['code' => $data['code']]);

        return Database::fetchOne("SELECT * FROM coupons WHERE id = ?", [$couponId]);
    }

    public function deleteCoupon(int $id, int $adminUserId): void
    {
        Database::query("DELETE FROM coupons WHERE id = ?", [$id]);
        AuditLogger::admin($adminUserId, 'coupon_deleted', '', ['coupon_id' => $id]);
    }
}
