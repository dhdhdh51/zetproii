<?php
require_once dirname(__DIR__, 2) . '/app/config/bootstrap.php';

$request = new Request();
$user = AuthMiddleware::user();
$businessId = $request->int('business_id');
$role = AuthMiddleware::requireBusinessAccess((int) $user['id'], $businessId);
$service = new CampaignService();

if ($request->method === 'GET') {
    Response::success($service->list($businessId));
}

if ($request->method === 'POST') {
    Security::requireCsrf();
    PermissionMiddleware::require($role, 'settings.manage');
    Validator::make($request->all())->required('name', 'Campaign name')->validateOrFail();
    Response::success($service->create($businessId, (int) $user['id'], $request->all()), 'Campaign created.', 201);
}

if ($request->method === 'DELETE') {
    Security::requireCsrf();
    PermissionMiddleware::require($role, 'settings.manage');
    $service->delete($businessId, $request->int('id'));
    Response::success(null, 'Campaign deleted.');
}

Response::error('Method not allowed', [], 405);
