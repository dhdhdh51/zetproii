<?php
/**
 * GET  /api/leads/index.php?business_id=X&page=1&per_page=20&search=...&status_id=...
 * POST /api/leads/index.php   Body: { business_id, name, email, phone, ... }
 */

require_once dirname(__DIR__, 2) . '/app/config/bootstrap.php';

$request = new Request();
$user = AuthMiddleware::user();
$service = new LeadService();

if ($request->method === 'GET') {
    $businessId = $request->int('business_id');
    AuthMiddleware::requireBusinessAccess((int) $user['id'], $businessId);

    $filters = [
        'search' => $request->string('search'),
        'status_id' => $request->int('status_id') ?: null,
        'source_id' => $request->int('source_id') ?: null,
        'priority' => $request->string('priority'),
        'assigned_user_id' => $request->int('assigned_user_id') ?: null,
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
    $businessId = $request->int('business_id');
    $role = AuthMiddleware::requireBusinessAccess((int) $user['id'], $businessId);
    PermissionMiddleware::require($role, 'leads.create');

    Validator::make($request->all())->required('name', 'Name')->maxLength('name', 190)->validateOrFail();
    if ($request->string('email') !== '') {
        Validator::make($request->all())->email('email')->validateOrFail();
    }

    $lead = $service->create($businessId, (int) $user['id'], $request->all());
    Response::success($lead, 'Lead created successfully.', 201);
}

Response::error('Method not allowed', [], 405);
