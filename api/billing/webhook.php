<?php
/**
 * POST /api/billing/webhook.php?gateway=razorpay|stripe|cashfree
 * Public endpoint called by the payment gateway itself. Signature is
 * verified inside PaymentService::handleWebhook() BEFORE any payload
 * data is trusted (spec #29).
 */

require_once dirname(__DIR__, 2) . '/app/config/bootstrap.php';

$request = new Request();
if ($request->method !== 'POST') {
    Response::error('Method not allowed', [], 405);
}

$gateway = $request->string('gateway');
if ($gateway === '') {
    Response::error('Missing gateway parameter.', [], 400);
}

$rawBody = file_get_contents('php://input') ?: '';
$headers = [];
foreach ($_SERVER as $key => $value) {
    if (str_starts_with($key, 'HTTP_')) {
        $headerName = str_replace('_', '-', substr($key, 5));
        $headers[$headerName] = $value;
    }
}

try {
    (new PaymentService())->handleWebhook($gateway, $rawBody, $headers);
} catch (\Throwable $e) {
    Logger::payment('Webhook handling failed: ' . $e->getMessage());
    Response::serverError('Webhook processing failed.');
}

// Always acknowledge with 200 so the gateway does not endlessly retry
// once we've successfully logged/processed the event.
Response::success(null, 'Webhook received.');
