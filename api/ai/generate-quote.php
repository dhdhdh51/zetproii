<?php
/**
 * POST /api/ai/generate-quote.php  Body: { business_id, customer_id?, requirement }
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

$quotation = (new QuotationService())->generateWithAI($businessId, (int) $user['id'], $request->all());

Response::success($quotation, 'Quote generated with AI.', 201);
