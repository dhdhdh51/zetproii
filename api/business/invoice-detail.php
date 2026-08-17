<?php
require_once dirname(__DIR__, 2) . '/app/config/bootstrap.php';

$request = new Request();
$user = AuthMiddleware::user();
$businessId = $request->int('business_id');
$id = $request->int('id');
$role = AuthMiddleware::requireBusinessAccess((int) $user['id'], $businessId);
PermissionMiddleware::require($role, 'documents.manage');
$service = new InvoiceService();

if ($request->method === 'GET') {
    $invoice = $service->find($businessId, $id);
    if ($invoice === null) {
        Response::notFound('Invoice not found.');
    }
    Response::success($invoice);
}

if ($request->method === 'DELETE') {
    Security::requireCsrf();
    $service->delete($businessId, $id);
    Response::success(null, 'Invoice deleted.');
}

Response::error('Method not allowed', [], 405);
