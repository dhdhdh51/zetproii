<?php
/**
 * GET  /api/crm/customers.php?business_id=X&page=1&search=...
 * POST /api/crm/customers.php   Body: { business_id, name, email, ... }
 */

require_once dirname(__DIR__, 2) . '/app/config/bootstrap.php';

$request = new Request();
$user = AuthMiddleware::user();
$service = new CustomerService();

$businessId = $request->int('business_id');
$role = AuthMiddleware::requireBusinessAccess((int) $user['id'], $businessId);

if ($request->method === 'GET') {
    PermissionMiddleware::require($role, 'customers.view');
    $filters = [
        'search' => $request->string('search'),
        'date_from' => $request->string('date_from'),
        'date_to' => $request->string('date_to'),
        'sort' => $request->string('sort'),
        'dir' => $request->string('dir'),
    ];
    $result = $service->list($businessId, $filters, $request->int('page', 1), $request->int('per_page', 20));
    Response::success($result);
}

if ($request->method === 'POST') {
    Security::requireCsrf();
    PermissionMiddleware::require($role, 'customers.create');
    Validator::make($request->all())->required('name', 'Name')->maxLength('name', 190)->validateOrFail();
    $customer = $service->create($businessId, $request->all());
    Response::success($customer, 'Customer created successfully.', 201);
}

Response::error('Method not allowed', [], 405);
