<?php
/**
 * GET /api/analytics/dashboard.php?business_id=X
 * Returns all widget + chart data for the main dashboard in one call.
 */

require_once dirname(__DIR__, 2) . '/app/config/bootstrap.php';

$request = new Request();
$user = AuthMiddleware::user();

$businessId = $request->int('business_id');
AuthMiddleware::requireBusinessAccess((int) $user['id'], $businessId);

$service = new DashboardService();

Response::success([
    'widgets' => $service->widgets($businessId),
    'leads_over_time' => $service->leadsOverTime($businessId),
    'conversion_funnel' => $service->conversionFunnel($businessId),
    'lead_sources' => $service->leadSources($businessId),
    'revenue_over_time' => $service->revenueOverTime($businessId),
    'ai_usage_over_time' => $service->aiUsageOverTime($businessId),
    'recent_activity' => $service->recentActivity($businessId),
]);
