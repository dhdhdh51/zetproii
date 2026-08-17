<?php
/**
 * POST /api/billing/create-payment.php  Body: { business_id, plan_slug, billing_cycle }
 * Creates a gateway order for the given plan and returns checkout data
 * to the frontend (order id, key, etc.) - no gateway secret ever reaches
 * the browser.
 */

require_once dirname(__DIR__, 2) . '/app/config/bootstrap.php';

$request = new Request();
if ($request->method !== 'POST') {
    Response::error('Method not allowed', [], 405);
}
Security::requireCsrf();

$user = AuthMiddleware::user();
$businessId = $request->int('business_id');
$role = AuthMiddleware::requireBusinessAccess((int) $user['id'], $businessId);
PermissionMiddleware::require($role, 'billing.manage');

Validator::make($request->all())->required('plan_slug')->validateOrFail();

$plan = Database::fetchOne("SELECT * FROM plans WHERE slug = ? AND is_active = 1", [$request->string('plan_slug')]);
if ($plan === null) {
    Response::validationError(['plan_slug' => ['Invalid plan.']]);
}

$cycle = $request->string('billing_cycle', 'monthly');
$amount = (float) ($cycle === 'yearly' ? $plan['price_yearly'] : $plan['price_monthly']);

if ($amount <= 0) {
    Response::error('This plan does not require payment.', [], 400);
}

try {
    $orderData = (new PaymentService())->createPayment($businessId, $amount, $plan['currency'], [
        'plan_slug' => $plan['slug'],
        'billing_cycle' => $cycle,
        'user_id' => $user['id'],
    ]);
} catch (\Throwable $e) {
    Logger::error('Payment order creation failed: ' . $e->getMessage());
    Response::serverError('Unable to initiate payment right now. Please try again shortly.');
}

Response::success($orderData, 'Payment order created.');
