<?php
/**
 * POST /api/leads/convert.php   Body: { business_id, lead_id }
 * Converts a lead to a customer record.
 */

require_once dirname(__DIR__, 2) . '/app/config/bootstrap.php';

$request = new Request();
if ($request->method !== 'POST') {
    Response::error('Method not allowed', [], 405);
}
Security::requireCsrf();

$user = AuthMiddleware::user();
$businessId = $request->int('business_id');
$role = AuthMiddleware::requireBusinessAccess((int) $user['id'], $businessId);
PermissionMiddleware::require($role, 'customers.create');

Validator::make($request->all())->required('lead_id')->validateOrFail();

$customer = (new LeadService())->convertToCustomer($businessId, $request->int('lead_id'), (int) $user['id']);

Response::success($customer, 'Lead converted to customer.', 201);
