<?php
require_once dirname(__DIR__, 2) . '/app/config/bootstrap.php';

$request = new Request();
$user = AuthMiddleware::user();
$businessId = $request->int('business_id');
$id = $request->int('id');
$role = AuthMiddleware::requireBusinessAccess((int) $user['id'], $businessId);
PermissionMiddleware::require($role, 'documents.manage');
$service = new QuotationService();

if ($request->method === 'GET') {
    $quotation = $service->find($businessId, $id);
    if ($quotation === null) {
        Response::notFound('Quotation not found.');
    }
    Response::success($quotation);
}

if ($request->method === 'PUT' || $request->method === 'PATCH') {
    Security::requireCsrf();
    Response::success($service->update($businessId, $id, $request->all()), 'Quotation updated.');
}

if ($request->method === 'DELETE') {
    Security::requireCsrf();
    $service->delete($businessId, $id);
    Response::success(null, 'Quotation deleted.');
}

Response::error('Method not allowed', [], 405);
