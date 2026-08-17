<?php
/**
 * POST /api/ai/generate-proposal.php  Body: { business_id, customer_id?, requirement, title? }
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
PermissionMiddleware::require($role, 'ai.use');
PermissionMiddleware::require($role, 'documents.manage');

$proposal = (new ProposalService())->generateWithAI($businessId, (int) $user['id'], $request->all());

Response::success($proposal, 'Proposal generated with AI.', 201);
