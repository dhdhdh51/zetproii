<?php
/**
 * GET    /api/crm/customer-detail.php?business_id=X&id=Y
 * PUT    /api/crm/customer-detail.php   Body: { business_id, id, ...fields }
 * DELETE /api/crm/customer-detail.php   Body: { business_id, id }
 */

require_once dirname(__DIR__, 2) . '/app/config/bootstrap.php';

$request = new Request();
$user = AuthMiddleware::user();
$service = new CustomerService();

$businessId = $request->int('business_id');
$customerId = $request->int('id');
$role = AuthMiddleware::requireBusinessAccess((int) $user['id'], $businessId);

if ($request->method === 'GET') {
    PermissionMiddleware::require($role, 'customers.view');
    $customer = $service->find($businessId, $customerId);
    if ($customer === null) {
        Response::notFound('Customer not found.');
    }
    Response::success($customer);
}

if ($request->method === 'PUT' || $request->method === 'PATCH') {
    Security::requireCsrf();
    PermissionMiddleware::require($role, 'customers.edit');
    $customer = $service->update($businessId, $customerId, $request->all());
    Response::success($customer, 'Customer updated successfully.');
}

if ($request->method === 'DELETE') {
    Security::requireCsrf();
    PermissionMiddleware::require($role, 'customers.delete');
    $service->delete($businessId, $customerId, (int) $user['id']);
    Response::success(null, 'Customer deleted successfully.');
}

Response::error('Method not allowed', [], 405);
