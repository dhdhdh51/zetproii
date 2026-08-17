<?php
/**
 * POST /api/ai/review-reply.php  Body: { business_id, review_id, tone? }
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

Validator::make($request->all())->required('review_id')->validateOrFail();

$reply = (new ContentToolsService())->generateReviewReply(
    $businessId, (int) $user['id'], $request->int('review_id'), $request->string('tone', 'professional')
);

Response::success($reply, 'Reply generated.', 201);
