<?php
/**
 * POST /api/billing/verify-payment.php  Body: { business_id, payment_id, ...gateway-specific fields }
 */

require_once dirname(__DIR__, 2) . '/app/config/bootstrap.php';

$request = new Request();
if ($request->method !== 'POST') {
    Response::error('Method not allowed', [], 405);
}
Security::requireCsrf();

$user = AuthMiddleware::user();
$businessId = $request->int('business_id');
AuthMiddleware::requireBusinessAccess((int) $user['id'], $businessId);

Validator::make($request->all())->required('payment_id')->validateOrFail();

$verified = (new PaymentService())->verifyPayment($request->int('payment_id'), $request->all());

if (!$verified) {
    Response::error('Payment verification failed.', [], 400);
}

Response::success(['verified' => true], 'Payment verified successfully.');
