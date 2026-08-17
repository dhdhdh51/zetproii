<?php
/**
 * GET  /api/business/proposals.php?business_id=X&page=1
 * POST /api/business/proposals.php  Body: { business_id, ...fields }
 */

require_once dirname(__DIR__, 2) . '/app/config/bootstrap.php';

$request = new Request();
$user = AuthMiddleware::user();
$businessId = $request->int('business_id');
$role = AuthMiddleware::requireBusinessAccess((int) $user['id'], $businessId);

$service = new ProposalService();

if ($request->method === 'GET') {
    PermissionMiddleware::require($role, 'documents.manage');
    $result = $service->list($businessId, ['search' => $request->string('search'), 'status' => $request->string('status')], $request->int('page', 1), $request->int('per_page', 20));
    Response::success($result);
}

if ($request->method === 'POST') {
    Security::requireCsrf();
    PermissionMiddleware::require($role, 'documents.manage');
    Validator::make($request->all())->required('title', 'Title')->validateOrFail();
    $proposal = $service->create($businessId, (int) $user['id'], $request->all());
    Response::success($proposal, 'Proposal created.', 201);
}

Response::error('Method not allowed', [], 405);
