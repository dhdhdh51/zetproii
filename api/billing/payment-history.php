<?php
/**
 * GET /api/billing/payment-history.php?business_id=X&page=1
 */

require_once dirname(__DIR__, 2) . '/app/config/bootstrap.php';

$request = new Request();
$user = AuthMiddleware::user();
$businessId = $request->int('business_id');
$role = AuthMiddleware::requireBusinessAccess((int) $user['id'], $businessId);
PermissionMiddleware::require($role, 'billing.view');

Response::success((new SubscriptionService())->paymentHistory($businessId, $request->int('page', 1), $request->int('per_page', 20)));
