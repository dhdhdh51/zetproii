<?php
require_once dirname(__DIR__, 2) . '/app/config/bootstrap.php';

$admin = AuthMiddleware::requireRole(['ADMIN', 'SUPER_ADMIN']);
$request = new Request();
$service = new PlanAdminService();

if ($request->method === 'GET') {
    Response::success($service->listCoupons());
}

if ($request->method === 'POST') {
    Security::requireCsrf();
    Validator::make($request->all())->required('code', 'Code')->required('discount_value')->numeric('discount_value')->validateOrFail();
    Response::success($service->createCoupon($request->all(), (int) $admin['id']), 'Coupon created.', 201);
}

if ($request->method === 'DELETE') {
    Security::requireCsrf();
    $service->deleteCoupon($request->int('id'), (int) $admin['id']);
    Response::success(null, 'Coupon deleted.');
}

Response::error('Method not allowed', [], 405);
