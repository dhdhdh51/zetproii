<?php
require_once dirname(__DIR__, 2) . '/app/config/bootstrap.php';

$request = new Request();
$user = AuthMiddleware::user();
$businessId = $request->int('business_id');
$role = AuthMiddleware::requireBusinessAccess((int) $user['id'], $businessId);
$service = new QuotationService();

if ($request->method === 'GET') {
    PermissionMiddleware::require($role, 'documents.manage');
    Response::success($service->list($businessId, ['search' => $request->string('search'), 'status' => $request->string('status')], $request->int('page', 1), $request->int('per_page', 20)));
}

if ($request->method === 'POST') {
    Security::requireCsrf();
    PermissionMiddleware::require($role, 'documents.manage');
    $quotation = $service->create($businessId, (int) $user['id'], $request->all());
    Response::success($quotation, 'Quotation created.', 201);
}

Response::error('Method not allowed', [], 405);
