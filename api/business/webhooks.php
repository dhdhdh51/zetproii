<?php
require_once dirname(__DIR__, 2) . '/app/config/bootstrap.php';

$request = new Request();
$user = AuthMiddleware::user();
$businessId = $request->int('business_id');
$role = AuthMiddleware::requireBusinessAccess((int) $user['id'], $businessId);
PermissionMiddleware::require($role, 'webhooks.manage');
$service = new ApiKeyService();

if ($request->method === 'GET') {
    Response::success($service->listWebhooks($businessId));
}

if ($request->method === 'POST') {
    Security::requireCsrf();
    Validator::make($request->all())->required('target_url', 'Target URL')->required('events', 'Events')->validateOrFail();
    $webhook = $service->createWebhook($businessId, $request->string('target_url'), $request->array('events'), (int) $user['id']);
    Response::success($webhook, 'Webhook created.', 201);
}

if ($request->method === 'DELETE') {
    Security::requireCsrf();
    $service->deleteWebhook($businessId, $request->int('id'));
    Response::success(null, 'Webhook deleted.');
}

Response::error('Method not allowed', [], 405);
