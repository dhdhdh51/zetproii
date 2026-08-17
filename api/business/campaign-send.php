<?php
/**
 * POST /api/business/campaign-send.php  Body: { business_id, campaign_id }
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
PermissionMiddleware::require($role, 'settings.manage');

Validator::make($request->all())->required('campaign_id')->validateOrFail();

$result = (new CampaignService())->send($businessId, $request->int('campaign_id'));

Response::success($result, 'Campaign sent.');
