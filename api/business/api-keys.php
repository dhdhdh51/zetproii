<?php
require_once dirname(__DIR__, 2) . '/app/config/bootstrap.php';

$request = new Request();
$user = AuthMiddleware::user();
$businessId = $request->int('business_id');
$role = AuthMiddleware::requireBusinessAccess((int) $user['id'], $businessId);
PermissionMiddleware::require($role, 'api_keys.manage');
$service = new ApiKeyService();

if ($request->method === 'GET') {
    Response::success($service->list($businessId));
}

if ($request->method === 'POST') {
    Security::requireCsrf();
    Validator::make($request->all())->required('name', 'Name')->validateOrFail();
    $result = $service->create($businessId, $request->string('name'), $request->array('permissions'), $request->string('expires_at') ?: null, (int) $user['id']);
    Response::success($result, 'API key created. Copy it now - it will not be shown again.', 201);
}

if ($request->method === 'DELETE') {
    Security::requireCsrf();
    $service->revoke($businessId, $request->int('id'), (int) $user['id']);
    Response::success(null, 'API key revoked.');
}

Response::error('Method not allowed', [], 405);
