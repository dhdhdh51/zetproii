<?php
require_once dirname(__DIR__, 2) . '/app/config/bootstrap.php';

$admin = AuthMiddleware::requireRole(['ADMIN', 'SUPER_ADMIN']);
$request = new Request();
$service = new PlanAdminService();

if ($request->method === 'GET') {
    Response::success($service->list());
}

if ($request->method === 'POST') {
    Security::requireCsrf();
    Validator::make($request->all())->required('name', 'Plan name')->validateOrFail();
    Response::success($service->upsert($request->all(), (int) $admin['id']), 'Plan saved.');
}

Response::error('Method not allowed', [], 405);
