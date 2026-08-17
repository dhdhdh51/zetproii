<?php
require_once dirname(__DIR__, 2) . '/app/config/bootstrap.php';

$request = new Request();
$user = AuthMiddleware::user();
$businessId = $request->int('business_id');
$role = AuthMiddleware::requireBusinessAccess((int) $user['id'], $businessId);
$service = new TaskService();

if ($request->method === 'GET') {
    Response::success($service->list($businessId, [
        'status' => $request->string('status'), 'assigned_user_id' => $request->int('assigned_user_id') ?: null, 'search' => $request->string('search'),
    ], $request->int('page', 1), $request->int('per_page', 20)));
}

if ($request->method === 'POST') {
    Security::requireCsrf();
    Validator::make($request->all())->required('title', 'Title')->validateOrFail();
    Response::success($service->create($businessId, (int) $user['id'], $request->all()), 'Task created.', 201);
}

if ($request->method === 'PUT' || $request->method === 'PATCH') {
    Security::requireCsrf();
    Validator::make($request->all())->required('id')->validateOrFail();
    Response::success($service->update($businessId, $request->int('id'), $request->all()), 'Task updated.');
}

if ($request->method === 'DELETE') {
    Security::requireCsrf();
    $service->delete($businessId, $request->int('id'));
    Response::success(null, 'Task deleted.');
}

Response::error('Method not allowed', [], 405);
