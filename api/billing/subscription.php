<?php
/**
 * GET  /api/billing/subscription.php?business_id=X          -> current subscription + usage
 * POST /api/billing/subscription.php  Body: { business_id, plan_slug, billing_cycle }
 */

require_once dirname(__DIR__, 2) . '/app/config/bootstrap.php';

$request = new Request();
$user = AuthMiddleware::user();
$businessId = $request->int('business_id');
$role = AuthMiddleware::requireBusinessAccess((int) $user['id'], $businessId);
$service = new SubscriptionService();

if ($request->method === 'GET') {
    PermissionMiddleware::require($role, 'billing.view');
    Response::success([
        'subscription' => $service->current($businessId),
        'usage' => $service->usageSummary($businessId),
        'plans' => Database::fetchAll("SELECT * FROM plans WHERE is_active = 1 ORDER BY sort_order ASC"),
    ]);
}

if ($request->method === 'POST') {
    Security::requireCsrf();
    PermissionMiddleware::require($role, 'billing.manage');
    Validator::make($request->all())->required('plan_slug')->in('billing_cycle', ['monthly', 'yearly'])->validateOrFail();

    $plan = Database::fetchOne("SELECT price_monthly, price_yearly FROM plans WHERE slug = ?", [$request->string('plan_slug')]);
    $cycle = $request->string('billing_cycle', 'monthly');
    $price = $plan !== null ? (float) ($cycle === 'yearly' ? $plan['price_yearly'] : $plan['price_monthly']) : 0;

    if ($price > 0 && !$request->bool('payment_confirmed')) {
        Response::error('This plan requires payment. Please complete checkout first.', [], 402);
    }

    $subscription = $service->changePlan($businessId, $request->string('plan_slug'), $cycle, (int) $user['id']);
    Response::success($subscription, 'Plan updated successfully.');
}

Response::error('Method not allowed', [], 405);
