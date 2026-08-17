<?php
require_once dirname(__DIR__, 2) . '/app/config/bootstrap.php';

$request = new Request();
$user = AuthMiddleware::user();
$businessId = $request->int('business_id');
$role = AuthMiddleware::requireBusinessAccess((int) $user['id'], $businessId);
$service = new InvoiceService();

if ($request->method === 'GET') {
    PermissionMiddleware::require($role, 'documents.manage');
    Response::success($service->list($businessId, ['search' => $request->string('search'), 'status' => $request->string('status')], $request->int('page', 1), $request->int('per_page', 20)));
}

if ($request->method === 'POST') {
    Security::requireCsrf();
    PermissionMiddleware::require($role, 'documents.manage');
    if ($request->has('from_quotation_id')) {
        $invoice = $service->createFromQuotation($businessId, (int) $user['id'], $request->int('from_quotation_id'));
    } else {
        $invoice = $service->create($businessId, (int) $user['id'], $request->all());
    }
    Response::success($invoice, 'Invoice created.', 201);
}

Response::error('Method not allowed', [], 405);
