<?php
/**
 * POST /api/ai/qualify-lead.php   Body: { business_id, lead_id }
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

Validator::make($request->all())->required('lead_id')->validateOrFail();

$lead = (new LeadQualificationService())->qualify($businessId, $request->int('lead_id'), (int) $user['id']);

Response::success($lead, 'Lead qualified successfully.');
