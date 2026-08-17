<?php
/**
 * POST /api/crm/customer-notes.php  Body: { business_id, customer_id, note }
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
PermissionMiddleware::require($role, 'customers.edit');

Validator::make($request->all())->required('customer_id')->required('note', 'Note')->validateOrFail();

$note = (new CustomerService())->addNote($businessId, $request->int('customer_id'), (int) $user['id'], $request->string('note'));

Response::success($note, 'Note added.', 201);
