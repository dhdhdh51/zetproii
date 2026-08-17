<?php
/**
 * GET  /api/admin/businesses.php?search=&status=&page=1
 * POST /api/admin/businesses.php  Body: { id, status }
 */

require_once dirname(__DIR__, 2) . '/app/config/bootstrap.php';

$admin = AuthMiddleware::requireRole(['ADMIN', 'SUPER_ADMIN']);
$request = new Request();
$service = new AdminService();

if ($request->method === 'GET') {
    $result = $service->listBusinesses([
        'search' => $request->string('search'),
        'status' => $request->string('status'),
    ], $request->int('page', 1), $request->int('per_page', 20));
    Response::success($result);
}

if ($request->method === 'POST') {
    Security::requireCsrf();
    Validator::make($request->all())->required('id')->required('status')->validateOrFail();
    $service->setBusinessStatus($request->int('id'), $request->string('status'), (int) $admin['id']);
    Response::success(null, 'Business status updated.');
}

Response::error('Method not allowed', [], 405);
