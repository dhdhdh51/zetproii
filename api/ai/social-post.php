<?php
/**
 * GET  /api/ai/social-post.php?business_id=X               -> list drafts
 * POST /api/ai/social-post.php  Body: { business_id, platform, topic, tone, audience, language, cta, keywords }
 */

require_once dirname(__DIR__, 2) . '/app/config/bootstrap.php';

$request = new Request();
$user = AuthMiddleware::user();
$businessId = $request->int('business_id');
$role = AuthMiddleware::requireBusinessAccess((int) $user['id'], $businessId);
$service = new ContentToolsService();

if ($request->method === 'GET') {
    Response::success($service->listSocialPosts($businessId));
}

if ($request->method === 'POST') {
    Security::requireCsrf();
    PermissionMiddleware::require($role, 'ai.use');
    Validator::make($request->all())->required('topic', 'Topic')->validateOrFail();
    $post = $service->generateSocialPost($businessId, (int) $user['id'], $request->all());
    Response::success($post, 'Post generated.', 201);
}

Response::error('Method not allowed', [], 405);
