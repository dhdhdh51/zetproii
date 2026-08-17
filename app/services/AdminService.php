<?php
/**
 * Platform-level admin operations: dashboard metrics, user/business
 * management (activate/suspend), audit + system log viewing.
 */
final class AdminService
{
    public function dashboardMetrics(): array
    {
        $totalUsers = (int) (Database::fetchOne("SELECT COUNT(*) c FROM users WHERE deleted_at IS NULL")['c'] ?? 0);
        $totalBusinesses = (int) (Database::fetchOne("SELECT COUNT(*) c FROM businesses WHERE deleted_at IS NULL")['c'] ?? 0);
        $activeSubscriptions = (int) (Database::fetchOne("SELECT COUNT(*) c FROM subscriptions WHERE status = 'active'")['c'] ?? 0);
        $totalRevenue = (float) (Database::fetchOne("SELECT COALESCE(SUM(amount),0) c FROM payments WHERE status = 'success'")['c'] ?? 0);
        $aiRequestsToday = (int) (Database::fetchOne("SELECT COUNT(*) c FROM ai_usage WHERE DATE(created_at) = CURDATE()")['c'] ?? 0);
        $openTickets = (int) (Database::fetchOne("SELECT COUNT(*) c FROM support_tickets WHERE status IN ('open','in_progress')")['c'] ?? 0);
        $newSignups7d = (int) (Database::fetchOne("SELECT COUNT(*) c FROM users WHERE created_at >= (NOW() - INTERVAL 7 DAY)")['c'] ?? 0);
        $failedAiRequests24h = (int) (Database::fetchOne("SELECT COUNT(*) c FROM ai_usage WHERE status = 'failed' AND created_at >= (NOW() - INTERVAL 1 DAY)")['c'] ?? 0);

        $signupsOverTime = Database::fetchAll(
            "SELECT DATE(created_at) d, COUNT(*) c FROM users WHERE created_at >= (NOW() - INTERVAL 30 DAY) GROUP BY DATE(created_at) ORDER BY d ASC"
        );
        $revenueOverTime = Database::fetchAll(
            "SELECT DATE_FORMAT(created_at, '%Y-%m') ym, COALESCE(SUM(amount),0) total FROM payments
             WHERE status = 'success' AND created_at >= (NOW() - INTERVAL 6 MONTH) GROUP BY ym ORDER BY ym ASC"
        );
        $planDistribution = Database::fetchAll(
            "SELECT p.name, COUNT(s.id) c FROM plans p LEFT JOIN subscriptions s ON s.plan_id = p.id AND s.status IN ('active','trialing')
             GROUP BY p.id ORDER BY c DESC"
        );

        return [
            'total_users' => $totalUsers,
            'total_businesses' => $totalBusinesses,
            'active_subscriptions' => $activeSubscriptions,
            'total_revenue' => $totalRevenue,
            'ai_requests_today' => $aiRequestsToday,
            'open_tickets' => $openTickets,
            'new_signups_7d' => $newSignups7d,
            'failed_ai_requests_24h' => $failedAiRequests24h,
            'signups_over_time' => $signupsOverTime,
            'revenue_over_time' => $revenueOverTime,
            'plan_distribution' => $planDistribution,
        ];
    }

    public function listUsers(array $filters, int $page, int $perPage): array
    {
        $page = max(1, $page);
        $perPage = max(1, min(100, $perPage));
        $offset = ($page - 1) * $perPage;

        $where = ["deleted_at IS NULL"];
        $params = [];
        if (!empty($filters['search'])) {
            $where[] = "(name LIKE ? OR email LIKE ?)";
            $term = '%' . $filters['search'] . '%';
            array_push($params, $term, $term);
        }
        if (!empty($filters['role'])) {
            $where[] = "role = ?";
            $params[] = $filters['role'];
        }
        if (!empty($filters['status'])) {
            $where[] = "status = ?";
            $params[] = $filters['status'];
        }
        $whereSql = implode(' AND ', $where);

        $total = (int) (Database::fetchOne("SELECT COUNT(*) c FROM users WHERE {$whereSql}", $params)['c'] ?? 0);
        $rows = Database::fetchAll(
            "SELECT id, uuid, name, email, phone, role, status, email_verified_at, last_login_at, created_at
             FROM users WHERE {$whereSql} ORDER BY created_at DESC LIMIT {$perPage} OFFSET {$offset}",
            $params
        );

        return ['items' => $rows, 'pagination' => ['page' => $page, 'per_page' => $perPage, 'total' => $total, 'total_pages' => (int) ceil($total / $perPage)]];
    }

    public function setUserStatus(int $userId, string $status, int $adminUserId): void
    {
        if (!in_array($status, ['active', 'inactive', 'suspended'], true)) {
            Response::validationError(['status' => ['Invalid status.']]);
        }
        Database::query("UPDATE users SET status = ? WHERE id = ?", [$status, $userId]);
        AuditLogger::admin($adminUserId, 'user_status_changed', "Set user #{$userId} to {$status}", ['user_id' => $userId, 'status' => $status]);
    }

    public function listBusinesses(array $filters, int $page, int $perPage): array
    {
        $page = max(1, $page);
        $perPage = max(1, min(100, $perPage));
        $offset = ($page - 1) * $perPage;

        $where = ["b.deleted_at IS NULL"];
        $params = [];
        if (!empty($filters['search'])) {
            $where[] = "b.name LIKE ?";
            $params[] = '%' . $filters['search'] . '%';
        }
        if (!empty($filters['status'])) {
            $where[] = "b.status = ?";
            $params[] = $filters['status'];
        }
        $whereSql = implode(' AND ', $where);

        $total = (int) (Database::fetchOne("SELECT COUNT(*) c FROM businesses b WHERE {$whereSql}", $params)['c'] ?? 0);
        $rows = Database::fetchAll(
            "SELECT b.*, u.name AS owner_name, u.email AS owner_email, p.name AS plan_name
             FROM businesses b
             LEFT JOIN users u ON u.id = b.owner_id
             LEFT JOIN subscriptions s ON s.business_id = b.id AND s.status IN ('active','trialing')
             LEFT JOIN plans p ON p.id = s.plan_id
             WHERE {$whereSql} ORDER BY b.created_at DESC LIMIT {$perPage} OFFSET {$offset}",
            $params
        );

        return ['items' => $rows, 'pagination' => ['page' => $page, 'per_page' => $perPage, 'total' => $total, 'total_pages' => (int) ceil($total / $perPage)]];
    }

    public function setBusinessStatus(int $businessId, string $status, int $adminUserId): void
    {
        if (!in_array($status, ['active', 'suspended', 'trial', 'cancelled'], true)) {
            Response::validationError(['status' => ['Invalid status.']]);
        }
        Database::query("UPDATE businesses SET status = ? WHERE id = ?", [$status, $businessId]);
        AuditLogger::admin($adminUserId, 'business_status_changed', "Set business #{$businessId} to {$status}", ['business_id' => $businessId, 'status' => $status]);
    }

    public function auditLogs(int $page, int $perPage): array
    {
        $page = max(1, $page);
        $perPage = max(1, min(100, $perPage));
        $offset = ($page - 1) * $perPage;
        $total = (int) (Database::fetchOne("SELECT COUNT(*) c FROM audit_logs")['c'] ?? 0);
        $rows = Database::fetchAll(
            "SELECT al.*, u.name AS user_name FROM audit_logs al LEFT JOIN users u ON u.id = al.user_id
             ORDER BY al.created_at DESC LIMIT {$perPage} OFFSET {$offset}"
        );
        return ['items' => $rows, 'pagination' => ['page' => $page, 'per_page' => $perPage, 'total' => $total, 'total_pages' => (int) ceil($total / $perPage)]];
    }

    public function systemLogs(?string $channel, int $page, int $perPage): array
    {
        $page = max(1, $page);
        $perPage = max(1, min(100, $perPage));
        $offset = ($page - 1) * $perPage;

        $where = '1=1';
        $params = [];
        if ($channel !== null && $channel !== '') {
            $where = 'channel = ?';
            $params[] = $channel;
        }

        $total = (int) (Database::fetchOne("SELECT COUNT(*) c FROM system_logs WHERE {$where}", $params)['c'] ?? 0);
        $rows = Database::fetchAll(
            "SELECT * FROM system_logs WHERE {$where} ORDER BY created_at DESC LIMIT {$perPage} OFFSET {$offset}",
            $params
        );
        return ['items' => $rows, 'pagination' => ['page' => $page, 'per_page' => $perPage, 'total' => $total, 'total_pages' => (int) ceil($total / $perPage)]];
    }
}
