<?php
/**
 * GET    /api/business/proposal-detail.php?business_id=X&id=Y
 * PUT    /api/business/proposal-detail.php  Body: { business_id, id, ...fields }
 * DELETE /api/business/proposal-detail.php  Body: { business_id, id }
 */

require_once dirname(__DIR__, 2) . '/app/config/bootstrap.php';

$request = new Request();
$user = AuthMiddleware::user();
$businessId = $request->int('business_id');
$id = $request->int('id');
$role = AuthMiddleware::requireBusinessAccess((int) $user['id'], $businessId);
PermissionMiddleware::require($role, 'documents.manage');

$service = new ProposalService();

if ($request->method === 'GET') {
    $proposal = $service->find($businessId, $id);
    if ($proposal === null) {
        Response::notFound('Proposal not found.');
    }
    Response::success($proposal);
}

if ($request->method === 'PUT' || $request->method === 'PATCH') {
    Security::requireCsrf();
    Response::success($service->update($businessId, $id, $request->all()), 'Proposal updated.');
}

if ($request->method === 'DELETE') {
    Security::requireCsrf();
    $service->delete($businessId, $id);
    Response::success(null, 'Proposal deleted.');
}

Response::error('Method not allowed', [], 405);
