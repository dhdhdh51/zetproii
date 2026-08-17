<?php
/**
 * POST /api/business/invoice-payment.php  Body: { business_id, invoice_id, amount }
 * Manually record a payment against an invoice (e.g. bank transfer, cash).
 * Online gateway payments are recorded via PaymentService webhooks instead.
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

Validator::make($request->all())->required('invoice_id')->required('amount')->numeric('amount')->validateOrFail();

$invoice = (new InvoiceService())->recordPayment($businessId, $request->int('invoice_id'), $request->float('amount'));

AuditLogger::log((int) $user['id'], $businessId, 'invoice_payment_recorded', ['invoice_id' => $request->int('invoice_id'), 'amount' => $request->float('amount')]);

Response::success($invoice, 'Payment recorded.');
