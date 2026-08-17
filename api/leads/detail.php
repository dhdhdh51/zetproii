<?php
/**
 * GET    /api/leads/detail.php?business_id=X&id=Y
 * PUT    /api/leads/detail.php   Body: { business_id, id, ...fields }
 * DELETE /api/leads/detail.php   Body: { business_id, id }
 */

require_once dirname(__DIR__, 2) . '/app/config/bootstrap.php';

$request = new Request();
$user = AuthMiddleware::user();
$service = new LeadService();

$businessId = $request->int('business_id');
$leadId = $request->int('id');

$role = AuthMiddleware::requireBusinessAccess((int) $user['id'], $businessId);

if ($request->method === 'GET') {
    PermissionMiddleware::require($role, 'leads.view');
    $lead = $service->find($businessId, $leadId);
    if ($lead === null) {
        Response::notFound('Lead not found.');
    }
    Response::success($lead);
}

if ($request->method === 'PUT' || $request->method === 'PATCH') {
    Security::requireCsrf();
    PermissionMiddleware::require($role, 'leads.edit');
    $lead = $service->update($businessId, $leadId, (int) $user['id'], $request->all());
    Response::success($lead, 'Lead updated successfully.');
}

if ($request->method === 'DELETE') {
    Security::requireCsrf();
    PermissionMiddleware::require($role, 'leads.delete');
    $service->delete($businessId, $leadId, (int) $user['id']);
    Response::success(null, 'Lead deleted successfully.');
}

Response::error('Method not allowed', [], 405);
